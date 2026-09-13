<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\{Company, Customer, Employee, Service, User};
use App\Services\{AppointmentService, AvailabilityService, CustomerRestrictionService};
use App\Whatsapp\{ConversationEngine, ConversationResponse, InboundMessage, InboundMessageProcessor};
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WhatsappFoundationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Customer $customer;
    private Service $service;
    private Employee $employee;
    private Employee $second;
    private int $sequence = 0;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('WhatsApp tests require isolated SQLite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Carbon::setTestNow(Carbon::parse('2030-01-07 09:00', 'Europe/Istanbul'));
        $this->company = $this->company('A');
        $this->customer = $this->company->customers()->create(['name' => 'Client', 'phone' => '+905551112233', 'active' => true]);
        $this->service = $this->company->services()->create(['name' => 'Haircut', 'duration_minutes' => 45, 'price_minor_units' => 25000, 'active' => true]);
        $this->employee = $this->company->employees()->create(['name' => 'First', 'username' => 'first', 'password' => 'test-password', 'active' => true]);
        $this->second = $this->company->employees()->create(['name' => 'Second', 'username' => 'second', 'password' => 'test-password', 'active' => true]);
        $this->service->employees()->attach([$this->second->id, $this->employee->id]);
        foreach ([$this->service, $this->employee, $this->second] as $model) {
            for ($day = 1; $day <= 7; $day++) {
                $model->weeklyAvailabilities()->create(['day_of_week' => $day, 'start_time' => '09:00', 'end_time' => '18:00']);
            }
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function company(string $name): Company
    {
        return Company::create(['name' => $name, 'code' => 'SLN-'.$name, 'phone' => '+905551112233',
            'manager_id' => User::factory()->create(['role' => User::ROLE_COMPANY_MANAGER])->id,
            'status' => Company::STATUS_ACTIVE, 'booking_days_ahead' => 2, 'late_cancellation_hours' => 24,
            'violation_limit' => 3, 'block_duration_days' => 7, 'whatsapp_enabled' => true]);
    }

    private function input(string $payload, ?string $id = null, ?Company $company = null, string $phone = '+905551112233', string $type = 'text'): InboundMessage
    {
        return new InboundMessage('test', $id ?? 'message-'.++$this->sequence, $company ?? $this->company,
            $phone, $type, $payload, CarbonImmutable::instance(now()));
    }

    private function send(string $payload, ?string $id = null): ConversationResponse
    {
        return app(InboundMessageProcessor::class)->handle($this->input($payload, $id));
    }

    private function state(): string
    {
        return $this->company->whatsappConversations()->where('customer_id', $this->customer->id)->sole()->state;
    }

    private function confirmation(bool $any = true, string $day = '2030-01-08'): ConversationResponse
    {
        $this->send('menu');
        $this->send('book');
        $this->send('service:'.$this->service->id);
        $this->send($any ? 'any' : 'employee:'.$this->employee->id);
        $this->send('day:'.$day);
        $response = $this->send('time:12:00');
        $this->assertSame('confirm_booking', $this->state());
        $this->assertSame('confirmation', $response->type);
        return $response;
    }

    private function book(string $day = '2030-01-08', ?Customer $customer = null)
    {
        return app(AppointmentService::class)->create(['customer_id' => ($customer ?? $this->customer)->id,
            'service_id' => $this->service->id, 'employee_id' => $this->employee->id,
            'starts_at' => Carbon::parse($day.' 12:00', 'Europe/Istanbul')], $this->company);
    }

    public function test_new_customer_name_and_persistent_menu(): void
    {
        $processor = app(InboundMessageProcessor::class);
        $reply = $processor->handle($this->input('Hi', phone: '0555 444 33 22'));
        $this->assertStringContainsString('name', $reply->text);
        $processor->handle($this->input('!', phone: '+905554443322'));
        $this->assertDatabaseHas('whatsapp_conversations', ['state' => 'awaiting_name']);
        $reply = app(InboundMessageProcessor::class)->handle($this->input('Jane Doe', phone: '0090 555 4443322'));
        $this->assertCount(3, $reply->choices);
        $this->assertSame('Jane Doe', $this->company->customers()->where('phone', '+905554443322')->sole()->name);
        $this->assertSame(2, $this->company->customers()->count());
    }

    public function test_known_customer_and_invalid_input_preserve_state(): void
    {
        $reply = $this->send('hello');
        $this->assertSame('main_menu', $this->state());
        $this->assertSame($reply->choices, $this->send('nonsense')->choices);
        $this->assertCount(3, $reply->choices);
    }

    public function test_expiry_and_unknown_state_reset(): void
    {
        $this->confirmation();
        $c = $this->company->whatsappConversations()->sole();
        $c->update(['expires_at' => now()->subSecond()]);
        $this->send('confirm');
        $this->assertSame('main_menu', $this->state());
        $c->update(['state' => 'invalid', 'context' => ['garbage' => true]]);
        $this->send('anything');
        $this->assertSame('main_menu', $this->state());
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_phone_format_variations_reuse_customer_and_preserve_country_code(): void
    {
        foreach (['0555 111 22 33', '+90 (555) 1112233', '00905551112233'] as $phone) {
            app(InboundMessageProcessor::class)->handle($this->input('hello', phone: $phone));
        }
        $this->assertSame(1, $this->company->customers()->count());
        $this->assertSame('+5512345678', $this->input('hello', phone: '+55 12345678')->customerPhone);
    }

    public function test_same_phone_and_external_id_are_isolated_by_company(): void
    {
        $this->send('hello', 'shared');
        $other = $this->company('B');
        app(InboundMessageProcessor::class)->handle($this->input('hello', 'shared', $other));
        $this->assertSame(1, $other->customers()->count());
        $this->assertNotSame($this->customer->id, $other->customers()->sole()->id);
        $this->assertDatabaseCount('whatsapp_conversations', 2);
        $this->assertDatabaseCount('whatsapp_inbound_messages', 2);
    }

    public function test_message_replay_does_not_advance_state_and_changed_content_is_rejected(): void
    {
        $reply = $this->send('hello', 'same');
        $revision = $this->company->whatsappConversations()->sole()->revision;
        $this->assertEquals($reply, $this->send('hello', 'same'));
        $this->assertSame($revision, $this->company->whatsappConversations()->sole()->revision);
        $this->assertTrue($this->send('book', 'same')->data['retryable']);
        $this->assertSame('main_menu', $this->state());
        $this->assertDatabaseCount('whatsapp_inbound_messages', 1);
    }

    public function test_active_service_and_assigned_barber_choices_only(): void
    {
        $this->company->services()->create(['name' => 'Hidden', 'duration_minutes' => 30, 'price_minor_units' => 0, 'active' => false]);
        $this->second->update(['active' => false]);
        $this->send('hi');
        $this->assertSame(['Haircut'], array_column($this->send('book')->choices, 'label'));
        $this->assertSame(['Any Available Barber', 'First'], array_column($this->send('service:'.$this->service->id)->choices, 'label'));
    }

    public function test_days_exclude_closures_and_horizon_and_times_match_availability_service(): void
    {
        $this->company->availabilityExceptions()->create(['type' => 'company', 'date' => '2030-01-08', 'is_closed' => true]);
        $this->send('hi'); $this->send('book'); $this->send('service:'.$this->service->id);
        $days = $this->send('any');
        $this->assertCount(2, $days->choices);
        $ids = implode(',', array_column($days->choices, 'id'));
        $this->assertStringNotContainsString('2030-01-08', $ids);
        $this->assertStringNotContainsString('2030-01-10', $ids);
        $times = $this->send('day:2030-01-09');
        $this->assertSame(app(AvailabilityService::class)->getBookableSlots($this->service, Carbon::parse('2030-01-09', 'Europe/Istanbul')), array_column($times->choices, 'label'));
    }

    public function test_confirmation_creates_once_with_snapshots_utc_and_customer_actor(): void
    {
        $summary = $this->confirmation()->data['summary'];
        $this->assertSame('Any Available Barber', $summary['barber']);
        $this->assertSame('12:00', $summary['time']);
        $reply = $this->send('confirm', 'confirmation');
        $this->assertSame('booking_complete', $this->state());
        $a = $this->company->appointments()->sole();
        $this->assertSame($this->employee->id, $a->employee_id);
        $this->assertSame('2030-01-08 09:00:00', $a->getRawOriginal('starts_at'));
        $this->assertSame('Haircut', $a->service_name_snapshot);
        $this->assertSame(25000, $a->service_price_minor_units_snapshot);
        $this->assertSame('customer', $a->events()->sole()->actor_type);
        $this->assertSame($a->booking_code, $reply->data['appointment']['booking_code']);
        $this->assertEquals($reply, $this->send('confirm', 'confirmation'));
        $this->send('confirm');
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('appointment_events', 1);
        $this->assertSame(1, $this->company->auditLogs()->where('action', 'whatsapp.booking.created')->count());
    }

    public function test_explicit_decline_does_not_book(): void
    {
        $this->confirmation(); $this->send('decline');
        $this->assertDatabaseCount('appointments', 0);
        $this->assertSame('main_menu', $this->state());
    }

    public function test_any_barber_uses_backend_fallback_after_race(): void
    {
        $this->confirmation(); $this->book();
        $this->send('confirm');
        $this->assertSame('booking_complete', $this->state());
        $this->assertSame($this->second->id, $this->company->appointments()->latest('id')->first()->employee_id);
    }

    public function test_specific_barber_slot_race_recovers_without_duplicate(): void
    {
        $this->confirmation(false); $this->book();
        $reply = $this->send('confirm');
        $this->assertSame('select_time', $this->state());
        $this->assertNotContains('12:00', array_column($reply->choices, 'label'));
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_blocked_customer_can_inquire_but_cannot_book(): void
    {
        $this->book();
        app(CustomerRestrictionService::class)->manualBlock($this->customer, 'Test');
        $this->send('hi');
        $this->assertStringContainsString('blocked', $this->send('book')->text);
        $this->assertCount(1, $this->send('inquiry')->data['appointments']);
    }

    public function test_block_added_at_confirmation_is_revalidated(): void
    {
        $this->confirmation();
        app(CustomerRestrictionService::class)->manualBlock($this->customer, 'Test');
        $this->assertStringContainsString('blocked', $this->send('confirm')->text);
        $this->assertDatabaseCount('appointments', 0);
    }

    public static function staleSelections(): array
    {
        return [['service'], ['employee'], ['price'], ['context'], ['foreign_service'], ['foreign_employee'], ['suspended']];
    }

    #[DataProvider('staleSelections')]
    public function test_final_confirmation_revalidates_stored_selection(string $change): void
    {
        $this->confirmation(false);
        $c = $this->company->whatsappConversations()->sole();
        if ($change === 'service') $this->service->update(['active' => false]);
        if ($change === 'employee') $this->employee->update(['active' => false]);
        if ($change === 'price') $this->service->update(['price_minor_units' => 99999]);
        if ($change === 'suspended') $this->company->update(['status' => Company::STATUS_SUSPENDED]);
        if ($change === 'context') $c->update(['context' => array_replace($c->context, ['day' => ['invalid']])]);
        if (str_starts_with($change, 'foreign_')) {
            $other = $this->company('B');
            $model = $change === 'foreign_service'
                ? $other->services()->create(['name' => 'Foreign', 'active' => true, 'duration_minutes' => 30, 'price_minor_units' => 0])
                : $other->employees()->create(['name' => 'Foreign', 'username' => 'foreign', 'password' => 'test-password', 'active' => true]);
            $key = $change === 'foreign_service' ? 'service_id' : 'employee_id';
            $c->update(['context' => array_replace($c->context, [$key => $model->id])]);
        }
        $reply = $this->send('confirm');
        $this->assertDatabaseCount('appointments', 0);
        $this->assertArrayNotHasKey('retryable', $reply->data);
    }

    public function test_stale_button_cannot_apply_to_new_prompt(): void
    {
        $reply = $this->send('hi');
        $old = $reply->choices[0]['id'];
        $this->send('menu');
        app(InboundMessageProcessor::class)->handle($this->input($old, type: 'button'));
        $this->assertSame('main_menu', $this->state());
    }

    public function test_inquiry_is_customer_scoped_and_uses_snapshots(): void
    {
        $own = $this->book();
        $other = $this->company->customers()->create(['name' => 'Other', 'phone' => '+905559998877', 'active' => true]);
        $this->book('2030-01-09', $other);
        $this->service->update(['name' => 'Renamed']);
        $this->send('hi');
        $items = $this->send('inquiry')->data['appointments'];
        $this->assertCount(1, $items);
        $this->assertSame($own->booking_code, $items[0]['booking_code']);
        $this->assertSame('Haircut', $items[0]['service']);
        $this->assertSame('12:00', $items[0]['time']);
        $this->assertSame('Europe/Istanbul', $items[0]['timezone']);
    }

    public static function cancellationTimes(): array { return [['2030-01-07', 1], ['2030-01-09', 0]]; }

    #[DataProvider('cancellationTimes')]
    public function test_cancellation_delegates_violation_rule_and_replays_safely(string $day, int $violations): void
    {
        $a = $this->book($day);
        $this->send('hi'); $this->send('cancel'); $this->send('appointment:'.$a->id);
        $this->assertSame('cancel_confirm', $this->state());
        $reply = $this->send('confirm', 'cancel-confirm');
        $this->assertSame(AppointmentStatus::CANCELLED_BY_CUSTOMER, $a->fresh()->status);
        $this->assertDatabaseCount('customer_violations', $violations);
        $this->assertEquals($reply, $this->send('confirm', 'cancel-confirm'));
        $this->send('cancel');
        $this->assertDatabaseCount('customer_violations', $violations);
        $this->assertSame(2, $a->events()->count());
    }

    public function test_terminal_and_foreign_customer_cancellation_are_rejected(): void
    {
        $a = $this->book();
        $this->send('hi'); $this->send('cancel'); $this->send('appointment:'.$a->id);
        app(AppointmentService::class)->cancelByCustomer($a, $this->company, 'customer', $this->customer->id);
        $this->send('confirm');
        $this->assertSame(2, $a->events()->count());
        $other = $this->company->customers()->create(['name' => 'Other', 'phone' => '+905559998877', 'active' => true]);
        $foreign = $this->book('2030-01-09', $other);
        $c = $this->company->whatsappConversations()->sole();
        $c->update(['state' => 'cancel_confirm', 'context' => ['appointment_id' => $foreign->id, 'choices' => [['id' => 'forged', 'action' => 'confirm', 'label' => 'Confirm']]]]);
        $this->send('confirm');
        $this->assertSame(AppointmentStatus::CONFIRMED, $foreign->fresh()->status);
    }

    public function test_failed_processing_rolls_back_booking_and_retry_can_finish_once(): void
    {
        $this->confirmation();
        $engine = new class(app(AppointmentService::class), app(AvailabilityService::class), app(CustomerRestrictionService::class)) extends ConversationEngine {
            public function handle(\App\Models\WhatsappConversation $conversation, Customer $customer, Company $company, InboundMessage $message): ConversationResponse
            {
                parent::handle($conversation, $customer, $company, $message);
                throw new \RuntimeException('Simulated failure after domain writes');
            }
        };
        $input = $this->input('confirm', 'retry-booking');
        $reply = (new InboundMessageProcessor($engine, app(CustomerRestrictionService::class)))->handle($input);
        $this->assertTrue($reply->data['retryable']);
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('appointment_events', 0);
        $this->assertSame(0, $this->company->auditLogs()->where('action', 'whatsapp.booking.created')->count());
        $this->assertSame('confirm_booking', $this->state());
        $this->assertDatabaseHas('whatsapp_inbound_messages', ['external_message_id' => 'retry-booking', 'status' => 'failed', 'attempts' => 1]);
        app(InboundMessageProcessor::class)->handle($input);
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseHas('whatsapp_inbound_messages', ['external_message_id' => 'retry-booking', 'status' => 'processed', 'attempts' => 2]);
    }

    public function test_disabled_company_creates_no_receipts_or_customers(): void
    {
        $this->company->update(['whatsapp_enabled' => false]);
        $reply = app(InboundMessageProcessor::class)->handle($this->input('hi', phone: '+905559998877'));
        $this->assertStringContainsString('not enabled', $reply->text);
        $this->assertDatabaseCount('whatsapp_inbound_messages', 0);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_failed_old_confirmation_cannot_confirm_later_selection(): void
    {
        $this->confirmation();
        $engine = \Mockery::mock(ConversationEngine::class);
        $engine->shouldReceive('handle')->once()->andThrow(new \RuntimeException('Test failure'));
        $input = $this->input('confirm', 'old-failure');
        (new InboundMessageProcessor($engine, app(CustomerRestrictionService::class)))->handle($input);
        $this->confirmation(false, '2030-01-09');
        $reply = app(InboundMessageProcessor::class)->handle($input);
        $this->assertStringContainsString('older conversation', $reply->text);
        $this->assertDatabaseCount('appointments', 0);
        $this->assertSame('confirm_booking', $this->state());
    }

    public function test_provider_destination_resolution_is_enabled_and_server_configured(): void
    {
        $this->company->forceFill(['whatsapp_phone_number_id' => 'destination-123'])->save();
        $resolver = app(\App\Whatsapp\CompanyResolver::class);
        $this->assertSame($this->company->id, $resolver->byPhoneNumberId('destination-123')->id);
        $this->assertArrayNotHasKey('whatsapp_phone_number_id', $this->company->toArray());
        $this->company->update(['whatsapp_enabled' => false]);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $resolver->byPhoneNumberId('destination-123');
    }

    public function test_no_available_employees_returns_safe_menu(): void
    {
        $this->employee->update(['active' => false]);
        $this->second->update(['active' => false]);
        $this->send('hi'); $this->send('book');
        $this->assertStringContainsString('No barbers', $this->send('service:'.$this->service->id)->text);
        $this->assertSame('main_menu', $this->state());
    }

    public function test_full_horizon_closure_returns_safe_menu(): void
    {
        foreach (['2030-01-07', '2030-01-08', '2030-01-09'] as $date) {
            $this->company->availabilityExceptions()->create(['type' => 'company', 'date' => $date, 'is_closed' => true]);
        }
        $this->send('hi'); $this->send('book'); $this->send('service:'.$this->service->id);
        $this->assertStringContainsString('No available times', $this->send('any')->text);
        $this->assertSame('main_menu', $this->state());
    }

    public function test_settings_number_uniqueness_and_suspension(): void
    {
        $other = $this->company('B');
        $other->update(['whatsapp_phone_number' => '+905551234567']);
        $data = ['name' => 'A', 'phone' => '+905551112233', 'booking_days_ahead' => 2,
            'late_cancellation_hours' => 24, 'violation_limit' => 3, 'block_duration_days' => 7,
            'whatsapp_enabled' => 1, 'whatsapp_phone_number' => '0555 123 45 67'];
        $this->actingAs($this->company->manager)->put('/company/settings', $data)->assertSessionHasErrors('whatsapp_phone_number');
        $this->company->update(['status' => Company::STATUS_SUSPENDED]);
        $this->actingAs($this->company->manager->fresh())->put('/company/settings', $data)->assertForbidden();
    }

    public function test_settings_accept_number_but_reject_provider_ids(): void
    {
        $data = ['name' => 'A', 'phone' => '+905551112233', 'booking_days_ahead' => 2,
            'late_cancellation_hours' => 24, 'violation_limit' => 3, 'block_duration_days' => 7,
            'whatsapp_enabled' => 1, 'whatsapp_phone_number' => '0555 123 45 67'];
        $this->actingAs($this->company->manager)->put('/company/settings', $data)->assertSessionHasNoErrors();
        $this->assertSame('+905551234567', $this->company->fresh()->whatsapp_phone_number);
        $this->put('/company/settings', $data + ['whatsapp_phone_number_id' => 'forged', 'whatsapp_business_account_id' => 'forged'])
            ->assertSessionHasErrors(['whatsapp_phone_number_id', 'whatsapp_business_account_id']);
        $this->assertNull($this->company->fresh()->whatsapp_phone_number_id);
        $this->assertFalse($this->company->isFillable('whatsapp_phone_number_id'));
    }
}
