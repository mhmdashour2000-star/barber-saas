<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\{Company, Appointment, User};
use App\Services\{AppointmentService, AvailabilityService};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompanyCompletionPartTwoTest extends TestCase
{
    use RefreshDatabase;
    private Company $company;
    private Company $other;
    private Appointment $appointment;
    private Appointment $foreign;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') throw new \RuntimeException('Isolated SQLite required.');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2030-01-07 09:00', 'Europe/Istanbul'));
        [$this->company, $this->appointment] = $this->tenant('ALPHA');
        [$this->other, $this->foreign] = $this->tenant('BETA');
        $this->actingAs($this->company->manager);
    }

    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    private function tenant(string $name): array
    {
        $company = Company::create(['code' => 'SLN-'.$name, 'name' => $name, 'status' => 'active', 'phone' => '05551112233',
            'manager_id' => User::factory()->create(['role' => User::ROLE_COMPANY_MANAGER])->id]);
        $customer = $company->customers()->create(['name' => $name.' Client', 'phone' => '+905551112233', 'active' => true]);
        $service = $company->services()->create(['name' => $name.' Haircut', 'duration_minutes' => 30, 'price_minor_units' => 10000, 'active' => true]);
        $employee = $company->employees()->create(['name' => $name.' Barber', 'username' => 'barber', 'password' => 'Password123', 'active' => true]);
        $service->employees()->attach($employee);
        foreach ([$service, $employee] as $target) {
            for ($day = 1; $day <= 7; $day++) $target->weeklyAvailabilities()->create(['day_of_week' => $day, 'start_time' => '09:00', 'end_time' => '18:00']);
        }
        $appointment = app(AppointmentService::class)->create(['customer_id' => $customer->id, 'service_id' => $service->id,
            'employee_id' => $employee->id, 'starts_at' => Carbon::parse('2030-01-08 12:00', 'Europe/Istanbul')], $company);
        return [$company, $appointment];
    }

    private function copyBooking(array $overrides): Appointment
    {
        $copy = $this->appointment->replicate();
        $copy->fill(array_replace(['booking_code' => 'AP-'.Str::upper(Str::random(8))], $overrides));
        $copy->save();
        return $copy;
    }

    private function change(string $kind): array
    {
        if (str_starts_with($kind, 'weekly')) {
            $group = $kind === 'weekly_employee' ? 'employees' : 'services';
            $id = $group === 'employees' ? $this->appointment->employee_id : $this->appointment->service_id;
            return ['PUT', route('company.'.$group.'.schedule.update', $id), ['days' => []]];
        }
        return ['POST', route('company.availability.exceptions.store'), ['type' => $kind, 'date' => '2030-01-08', 'is_closed' => '1'] + match ($kind) {
            'employee' => ['employee_id' => $this->appointment->employee_id],
            'service' => ['service_id' => $this->appointment->service_id],
            default => [],
        }];
    }

    #[DataProvider('changes')]
    public function test_changes_require_explicit_acknowledgement_and_preserve_appointments(string $kind): void
    {
        [$method, $url, $input] = $this->change($kind);
        $terminal = $this->copyBooking(['status' => AppointmentStatus::COMPLETED]);
        $before = $this->appointment->fresh()->getAttributes();
        $events = $this->appointment->events()->count();
        $response = $this->call($method, $url, $input)->assertOk()->assertSee('Existing appointments are affected')
            ->assertSee($this->appointment->booking_code)->assertSee('ALPHA Client')->assertSee('ALPHA Haircut')->assertSee('ALPHA Barber')
            ->assertSee('08 Jan 2030 12:00')->assertDontSee($this->foreign->booking_code)->assertDontSee($terminal->booking_code);
        if (str_starts_with($kind, 'weekly')) {
            $target = $kind === 'weekly_employee' ? $this->appointment->employee : $this->appointment->service;
            $this->assertSame(7, $target->weeklyAvailabilities()->count());
        } else $this->assertDatabaseCount('availability_exceptions', 0);
        // Replay the actual hidden fields, including the empty weekly-schedule marker.
        $encoded = [];
        foreach ($response->viewData('fields') as $name => $value) $encoded[] = urlencode($name).'='.urlencode($value);
        parse_str(implode('&', $encoded), $submitted);
        $this->call($method, $url, $submitted + ['change_confirmation' => $response->viewData('confirmation')])->assertRedirect();
        $this->assertSame($before, $this->appointment->fresh()->getAttributes());
        $this->assertSame($events, $this->appointment->events()->count());
        $this->assertDatabaseCount('customer_violations', 0);
        $this->assertDatabaseCount('whatsapp_outbound_messages', 0);
        $this->assertSame([], app(AvailabilityService::class)->getBookableSlots($this->appointment->service->fresh(), Carbon::parse('2030-01-08', 'Europe/Istanbul'), $this->appointment->employee_id));
    }

    public static function changes(): array { return [['company'], ['service'], ['employee'], ['weekly_service'], ['weekly_employee']]; }

    public function test_warning_acknowledgement_cannot_be_reused_for_changed_input_or_new_affected_bookings(): void
    {
        [$method, $url, $input] = $this->change('company');
        $response = $this->post($url, $input)->assertOk();
        $token = $response->viewData('confirmation');
        $new = $this->copyBooking(['starts_at' => Carbon::parse('2030-01-08 14:00', 'Europe/Istanbul')->utc(),
            'ends_at' => Carbon::parse('2030-01-08 14:30', 'Europe/Istanbul')->utc()]);
        $this->post($url, $input + ['change_confirmation' => $token])->assertOk()->assertSee($new->booking_code);
        $this->post($url, $input + ['reason' => 'Different change', 'change_confirmation' => $token])->assertOk()->assertSee('has not been saved');
        $this->travel(16)->minutes();
        $this->post($url, $input + ['change_confirmation' => $token])->assertOk();
        $this->assertDatabaseCount('availability_exceptions', 0);
    }

    public function test_custom_windows_only_warn_for_newly_excluded_relevant_bookings(): void
    {
        $unaffected = $this->copyBooking(['starts_at' => Carbon::parse('2030-01-08 15:00', 'Europe/Istanbul')->utc(),
            'ends_at' => Carbon::parse('2030-01-08 15:30', 'Europe/Istanbul')->utc()]);
        $response = $this->post(route('company.availability.exceptions.store'), ['type' => 'company', 'date' => '2030-01-08', 'is_closed' => '0',
            'windows' => [['start_time' => '14:00', 'end_time' => '17:00']]])->assertOk();
        $response->assertSee($this->appointment->booking_code)->assertDontSee($unaffected->booking_code);
    }

    public function test_schedule_change_with_no_impact_saves_without_warning_and_cross_tenant_change_is_denied(): void
    {
        $this->post(route('company.availability.exceptions.store'), ['type' => 'company', 'date' => '2030-01-09', 'is_closed' => '1'])->assertRedirect();
        $this->put(route('company.employees.schedule.update', $this->foreign->employee_id), ['days' => []])->assertNotFound();
        $this->assertSame(7, $this->foreign->employee->weeklyAvailabilities()->count());
    }

    public function test_customer_history_is_scoped_paginated_and_shows_current_employee_and_local_time(): void
    {
        $otherCustomer = $this->company->customers()->create(['name' => 'Other client', 'phone' => '+905559998877']);
        $excluded = $this->copyBooking(['customer_id' => $otherCustomer->id]);
        $current = $this->company->employees()->create(['name' => 'Current Barber', 'username' => 'current', 'password' => 'Secret123']);
        $this->appointment->update(['employee_id' => $current->id]);
        $this->get(route('company.customers.show', $this->appointment->customer_id))->assertOk()->assertSee('Appointment history')
            ->assertSee($this->appointment->booking_code)->assertSee('Current Barber')->assertSee('08 Jan 2030 12:00')
            ->assertDontSee($this->foreign->booking_code)->assertDontSee($excluded->booking_code);
        for ($i = 0; $i < 12; $i++) $this->copyBooking([]);
        $response = $this->get(route('company.customers.show', $this->appointment->customer_id));
        $this->assertSame(13, $response->viewData('appointments')->total());
        $this->assertCount(10, $response->viewData('appointments')->items());
        $this->get(route('company.customers.show', $this->foreign->customer_id))->assertNotFound();
        $this->assertSame('ALPHA Barber', $this->appointment->fresh()->employee_name_snapshot);
    }

    public function test_dashboard_counts_use_istanbul_start_day_and_are_tenant_scoped(): void
    {
        // Today in Istanbul starts on the previous UTC date at 21:00.
        $this->copyBooking(['starts_at' => Carbon::parse('2030-01-06 21:00', 'UTC'), 'status' => AppointmentStatus::COMPLETED]);
        $this->copyBooking(['starts_at' => Carbon::parse('2030-01-07 20:59', 'UTC'), 'status' => AppointmentStatus::NO_SHOW]);
        $this->copyBooking(['starts_at' => Carbon::parse('2030-01-07 10:00', 'UTC'), 'status' => AppointmentStatus::CANCELLED_BY_COMPANY]);
        $this->copyBooking(['starts_at' => Carbon::parse('2030-01-06 20:59', 'UTC'), 'status' => AppointmentStatus::COMPLETED]);
        $response = $this->get('/company/dashboard')->assertOk()->assertSee('Upcoming confirmed');
        $this->assertSame(['Today’s appointments' => 3, 'Completed today' => 1, 'Cancelled today' => 1, 'No-show today' => 1, 'Upcoming confirmed' => 1], $response->viewData('appointmentStats'));
    }

    public function test_booking_switch_defaults_to_enabled_and_settings_ignore_spoofed_company_id(): void
    {
        $this->assertTrue($this->company->fresh()->accepting_new_bookings);
        $input = ['name' => 'ALPHA', 'phone' => '05551112233', 'booking_days_ahead' => 7, 'late_cancellation_hours' => 24,
            'violation_limit' => 3, 'block_duration_days' => 7, 'accepting_new_bookings' => '0', 'company_id' => $this->other->id];
        $this->put('/company/settings', $input)->assertRedirect();
        $this->assertFalse($this->company->fresh()->accepting_new_bookings);
        $this->assertTrue($this->other->fresh()->accepting_new_bookings);
        $this->assertSame(AppointmentStatus::CONFIRMED, $this->appointment->fresh()->status);
        // Pausing creation must not prevent existing appointment operations.
        app(AppointmentService::class)->reschedule($this->appointment, $this->company, Carbon::parse('2030-01-08 14:00', 'Europe/Istanbul'));
        $this->assertSame('11:00', $this->appointment->fresh()->starts_at->format('H:i'));
    }
}
