<?php

namespace Tests\Feature;

use App\Models\{Company, Customer, CustomerBlock, Employee, Service, User};
use App\Services\{AppointmentService, AvailabilityService, CustomerRestrictionService};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompanyCompletionFixesTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Customer $customer;
    private Employee $employee;
    private Service $service;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Completion tests require isolated SQLite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2030-01-07 09:00', 'Europe/Istanbul'));
        $this->company = $this->tenant('ALPHA');
        $this->customer = $this->company->customers()->create(['name' => 'Client', 'phone' => '+905551112233', 'active' => true]);
        $this->employee = $this->company->employees()->create(['name' => 'Barber', 'username' => 'barber', 'password' => 'secret123', 'active' => true]);
        $this->service = $this->company->services()->create(['name' => 'Haircut', 'duration_minutes' => 30, 'price_minor_units' => 15000, 'active' => true]);
        $this->service->employees()->attach($this->employee);
        foreach ([$this->service, $this->employee] as $target) {
            $target->weeklyAvailabilities()->create(['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '18:00']);
        }
        $this->actingAs($this->company->manager);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function tenant(string $name): Company
    {
        return Company::create(['code' => 'SLN-'.$name, 'name' => $name, 'phone' => '+905551112233',
            'status' => Company::STATUS_ACTIVE, 'booking_days_ahead' => 7,
            'manager_id' => User::factory()->create(['role' => User::ROLE_COMPANY_MANAGER])->id]);
    }

    private function booking()
    {
        return app(AppointmentService::class)->create(['customer_id' => $this->customer->id,
            'service_id' => $this->service->id, 'employee_id' => $this->employee->id,
            'starts_at' => Carbon::parse('2030-01-08 12:00', 'Europe/Istanbul')], $this->company);
    }

    public function test_customer_checkbox_submits_false_and_can_be_reactivated_without_changing_history(): void
    {
        $booking = $this->booking();
        $before = $booking->fresh()->getAttributes();
        $page = $this->get(route('company.customers.show', $this->customer))->assertOk();
        $page->assertSeeInOrder(['type="hidden" name="active" value="0"', 'type="checkbox" name="active" value="1"'], false);
        $url = route('company.customers.update', $this->customer);
        $this->put($url, ['active' => '0', 'notes' => 'Retain history', 'company_id' => 999])->assertRedirect();
        $this->assertFalse($this->customer->fresh()->active);
        $this->assertSame($this->company->id, $this->customer->fresh()->company_id);
        $this->put($url, ['notes' => 'Notes only'])->assertRedirect();
        $this->assertFalse($this->customer->fresh()->active);
        try {
            $this->booking();
            $this->fail('Inactive customer was allowed to book.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('inactive', $exception->getMessage());
        }
        $this->put($url, ['active' => '1'])->assertRedirect();
        $this->assertTrue($this->customer->fresh()->active);
        $this->assertSame($before, $booking->fresh()->getAttributes());
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('appointment_events', 1);
        $this->assertDatabaseCount('customer_violations', 0);
    }

    public function test_customer_activation_cannot_cross_tenants_and_invalid_boolean_is_rejected(): void
    {
        $foreign = $this->tenant('BETA')->customers()->create(['name' => 'Foreign', 'phone' => '+905551112233', 'active' => true]);
        $this->put(route('company.customers.update', $foreign), ['active' => '0', 'company_id' => $foreign->company_id])->assertNotFound();
        $this->assertTrue($foreign->fresh()->active);
        $this->putJson(route('company.customers.update', $this->customer), ['active' => 'invalid'])->assertUnprocessable();
        $this->assertTrue($this->customer->fresh()->active);
    }

    public function test_eligibility_includes_inactive_and_blocked_states_and_unblock_never_activates(): void
    {
        $url = route('company.customers.show', $this->customer);
        $this->get($url)->assertSee('Allowed to book');
        $block = app(CustomerRestrictionService::class)->manualBlock($this->customer, 'Test block', 1);
        $this->get($url)->assertSee('Client is Blocked')->assertSee('Jan 08, 2030 09:00')->assertDontSee('permitted to book');
        $this->customer->update(['active' => false]);
        $this->get($url)->assertSee('Inactive — not allowed to book')->assertSee('Client is Blocked')->assertDontSee('permitted to book');
        $this->post(route('company.customers.unblock', $this->customer))->assertRedirect();
        $this->assertFalse($this->customer->fresh()->active);
        $this->assertNotNull($block->fresh()->lifted_at);
        $this->get($url)->assertSee('Inactive — not allowed to book')->assertDontSee('permitted to book');
        $this->assertDatabaseCount('customer_blocks', 1);
        $this->customer->update(['active' => true]);
        $this->get($url)->assertSee('Allowed to book');
    }

    public function test_restriction_timestamps_render_in_istanbul_without_changing_utc(): void
    {
        $block = $this->company->customerBlocks()->create(['customer_id' => $this->customer->id,
            'source' => CustomerBlock::SOURCE_MANUAL, 'reason' => 'Current',
            'starts_at' => Carbon::parse('2030-01-06 22:15', 'UTC'), 'ends_at' => Carbon::parse('2030-01-07 22:45', 'UTC')]);
        $lifted = $this->company->customerBlocks()->create(['customer_id' => $this->customer->id,
            'source' => CustomerBlock::SOURCE_MANUAL, 'reason' => 'History',
            'starts_at' => Carbon::parse('2030-01-05 22:10', 'UTC'), 'ends_at' => Carbon::parse('2030-01-06 23:00', 'UTC'),
            'lifted_at' => Carbon::parse('2030-01-06 22:30', 'UTC')]);
        $violation = $this->company->customerViolations()->create(['customer_id' => $this->customer->id,
            'type' => 'no_show', 'occurred_at' => Carbon::parse('2030-01-06 22:20', 'UTC')]);
        $before = [$block->fresh()->getAttributes(), $lifted->fresh()->getAttributes(), $violation->fresh()->getAttributes()];
        $this->get(route('company.customers.show', $this->customer))->assertOk()->assertSee('Europe/Istanbul')
            ->assertSee('Jan 07, 2030 01:15')->assertSee('Jan 08, 2030 01:45')
            ->assertSee('Jan 06, 2030 01:10')->assertSee('Lifted Jan 07, 01:30')->assertSee('Jan 07, 2030 01:20');
        $this->get(route('company.customers.index'))->assertSee('Until Jan 08, 01:45 (Europe/Istanbul)');
        $this->assertSame($before, [$block->fresh()->getAttributes(), $lifted->fresh()->getAttributes(), $violation->fresh()->getAttributes()]);
    }

    #[DataProvider('targets')]
    public function test_empty_schedule_is_explicit_restorable_and_preserves_bookings_and_exceptions(string $kind): void
    {
        $target = $kind === 'employees' ? $this->employee : $this->service;
        $booking = $this->booking();
        $before = $booking->fresh()->getAttributes();
        $exception = $this->company->availabilityExceptions()->create(['type' => $kind === 'employees' ? 'employee' : 'service',
            $kind === 'employees' ? 'employee_id' : 'service_id' => $target->id,
            'date' => '2030-01-08', 'is_closed' => false, 'reason' => 'Special hours']);
        $exception->windows()->create(['start_time' => '14:00', 'end_time' => '16:00']);
        $url = route('company.'.$kind.'.schedule.update', $target);
        $this->get(route('company.'.$kind.'.schedule.edit', $target))->assertOk()->assertSee('name="schedule_submitted" value="1"', false);
        $this->put($url, ['schedule_submitted' => '1'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(0, $target->weeklyAvailabilities()->count());
        $availability = app(AvailabilityService::class);
        // Weekly windows are gone, but the explicit date exception remains authoritative.
        $this->assertContains('14:00', $availability->getBookableSlots($this->service->fresh(), Carbon::parse('2030-01-08', 'Europe/Istanbul'), $this->employee->id));
        $this->assertSame(1, $exception->windows()->count());
        $this->assertSame($before, $booking->fresh()->getAttributes());
        $this->assertDatabaseCount('appointment_events', 1);
        // On a day without an exception, the cleared target is unavailable.
        $otherTarget = $kind === 'employees' ? $this->service : $this->employee;
        $otherTarget->weeklyAvailabilities()->create(['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '18:00']);
        $day = Carbon::parse('2030-01-09', 'Europe/Istanbul');
        $this->assertSame([], $availability->getBookableSlots($this->service->fresh(), $day, $this->employee->id));
        $this->put($url, ['days' => [3 => [['start_time' => '10:00', 'end_time' => '11:00']]]])->assertSessionHasNoErrors();
        $this->assertContains('10:00', $availability->getBookableSlots($this->service->fresh(), $day, $this->employee->id));
        $this->putJson($url, ['days' => []])->assertRedirect();
        $this->assertSame([], $availability->getBookableSlots($this->service->fresh(), $day, $this->employee->id));
    }

    public static function targets(): array { return [['employees'], ['services']]; }

    #[DataProvider('malformedSchedules')]
    public function test_malformed_schedule_cannot_clear_existing_windows(array $input): void
    {
        foreach (['employees' => $this->employee, 'services' => $this->service] as $kind => $target) {
            $before = $target->weeklyAvailabilities()->get()->toArray();
            $this->putJson(route('company.'.$kind.'.schedule.update', $target), $input)->assertUnprocessable();
            $this->assertSame($before, $target->weeklyAvailabilities()->get()->toArray());
        }
    }

    public static function malformedSchedules(): array
    {
        return [
            [[]], [['schedule_submitted' => '0']], [['schedule_submitted' => '1', 'days' => null]],
            [['schedule_submitted' => '1', 'days' => 'oops']], [['days' => [2 => null]]],
            [['days' => [2 => ['oops']]]], [['days' => [2 => [[]]]]],
            [['days' => ['2oops' => [['start_time' => '09:00', 'end_time' => '10:00']]]]],
            [['days' => [2 => [['start_time' => '09:00']]]]],
            [['days' => [2 => [['start_time' => '10:00', 'end_time' => '09:00']]]]],
            [['days' => [2 => [['start_time' => '09:00', 'end_time' => '11:00'], ['start_time' => '10:00', 'end_time' => '12:00']]]]],
        ];
    }

    public function test_other_manager_cannot_clear_schedules_or_change_customer_and_suspension_is_enforced(): void
    {
        $this->actingAs($this->tenant('OTHER')->manager);
        foreach (['employees' => $this->employee, 'services' => $this->service] as $kind => $target) {
            $this->put(route('company.'.$kind.'.schedule.update', $target), ['schedule_submitted' => '1'])->assertNotFound();
            $this->assertSame(1, $target->weeklyAvailabilities()->count());
        }
        $this->company->update(['status' => Company::STATUS_SUSPENDED]);
        $this->actingAs($this->company->manager->fresh());
        $this->put(route('company.customers.update', $this->customer), ['active' => '0'])->assertForbidden();
        $this->put(route('company.employees.schedule.update', $this->employee), ['days' => []])->assertForbidden();
    }

    public function test_navigation_exposes_existing_manager_sections_and_highlights_restored_links(): void
    {
        $destinations = ['company.dashboard', 'company.appointments.index', 'company.employees.index',
            'company.services.index', 'company.customers.index', 'company.availability.index', 'company.settings.edit'];
        foreach ($destinations as $name) {
            $response = $this->get(route($name))->assertOk();
            $dom = new \DOMDocument();
            @$dom->loadHTML($response->getContent());
            $xpath = new \DOMXPath($dom);
            $links = $xpath->query('//aside[@id="app-sidebar"]//a[@href]');
            $hrefs = [];
            foreach ($links as $link) $hrefs[] = $link->getAttribute('href');
            $this->assertEqualsCanonicalizing(array_map(fn ($route) => route($route), $destinations), $hrefs);
            if (in_array($name, ['company.services.index', 'company.customers.index', 'company.availability.index'], true)) {
                $active = $xpath->query('//aside//a[@aria-current="page"]');
                $this->assertCount(1, $active);
                $this->assertSame(route($name), $active->item(0)->getAttribute('href'));
                $this->assertStringContainsString('bg-blue-600', $active->item(0)->getAttribute('class'));
            }
            $response->assertSee('mobile-sidebar-toggle')->assertSee('sidebar-backdrop');
        }
    }
}
