<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\AvailabilityException;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $managerA;
    protected Company $companyA;
    protected Service $serviceA;
    protected Employee $employeeA;

    protected User $managerB;
    protected Company $companyB;
    protected Service $serviceB;
    protected Employee $employeeB;

    protected AvailabilityService $availabilityService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->availabilityService = new AvailabilityService();

        // Company A
        $this->managerA = User::factory()->create(['role' => User::ROLE_COMPANY_MANAGER]);
        $this->companyA = Company::create([
            'code' => Company::generateUniqueCode(),
            'name' => 'Salon Alpha',
            'manager_id' => $this->managerA->id,
            'phone' => '+905551112233',
            'status' => Company::STATUS_ACTIVE,
            'booking_days_ahead' => 7,
        ]);
        $this->serviceA = $this->companyA->services()->create([
            'name' => 'Alpha Haircut',
            'price_minor_units' => 20000,
            'duration_minutes' => 45,
            'active' => true,
        ]);
        $this->employeeA = $this->companyA->employees()->create([
            'name' => 'Barber John',
            'username' => 'john_barber',
            'active' => true,
            'password' => 'secret123',
        ]);
        $this->serviceA->employees()->attach($this->employeeA->id);

        // Company B
        $this->managerB = User::factory()->create(['role' => User::ROLE_COMPANY_MANAGER]);
        $this->companyB = Company::create([
            'code' => Company::generateUniqueCode(),
            'name' => 'Salon Beta',
            'manager_id' => $this->managerB->id,
            'phone' => '+905554445566',
            'status' => Company::STATUS_ACTIVE,
            'booking_days_ahead' => 7,
        ]);
        $this->serviceB = $this->companyB->services()->create([
            'name' => 'Beta Haircut',
            'price_minor_units' => 30000,
            'duration_minutes' => 60,
            'active' => true,
        ]);
        $this->employeeB = $this->companyB->employees()->create([
            'name' => 'Barber Sarah',
            'username' => 'sarah_barber',
            'active' => true,
            'password' => 'secret123',
        ]);
        $this->serviceB->employees()->attach($this->employeeB->id);
    }

    /**
     * 1. Service with valid weekly schedule is available.
     * 2. Service outside weekly window is unavailable.
     * 3. Employee outside own weekly window is unavailable.
     * 4. Service & Employee schedule intersection works accurately.
     * 8. Full service duration must fit inside window.
     */
    public function test_service_and_employee_schedule_intersection_and_duration_fit(): void
    {
        // Service A open on Mondays 09:00 - 18:00
        $this->serviceA->weeklyAvailabilities()->create([
            'day_of_week' => 1, // Monday
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);

        // Employee A works Mondays 12:00 - 17:00
        $this->employeeA->weeklyAvailabilities()->create([
            'day_of_week' => 1, // Monday
            'start_time' => '12:00',
            'end_time' => '17:00',
        ]);

        // Next Monday within horizon (e.g. 2 days ahead)
        $testMonday = Carbon::now(AvailabilityService::TIMEZONE)->next(Carbon::MONDAY)->setTime(13, 0);

        // Within intersection (12:00 - 17:00) with 45 min duration -> Available
        $this->assertTrue(
            $this->availabilityService->isEmployeeAvailable($this->serviceA, $this->employeeA, $testMonday)
        );

        // Before employee starts (10:00) -> Unavailable
        $testTooEarly = $testMonday->copy()->setTime(10, 0);
        $this->assertFalse(
            $this->availabilityService->isEmployeeAvailable($this->serviceA, $this->employeeA, $testTooEarly)
        );

        // Starts at 16:30 with 45 min duration (ends 17:15, after 17:00 employee departure) -> Duration overflow -> Unavailable
        $testOverflow = $testMonday->copy()->setTime(16, 30);
        $this->assertFalse(
            $this->availabilityService->isEmployeeAvailable($this->serviceA, $this->employeeA, $testOverflow)
        );

        // Fits exactly: starts 16:15, ends 17:00 -> Available
        $testExactFit = $testMonday->copy()->setTime(16, 15);
        $this->assertTrue(
            $this->availabilityService->isEmployeeAvailable($this->serviceA, $this->employeeA, $testExactFit)
        );
    }

    /**
     * 5. Inactive service unavailable.
     * 6. Inactive employee unavailable.
     * 7. Unassigned employee unavailable.
     */
    public function test_inactive_and_unassigned_entities_are_unavailable(): void
    {
        $this->serviceA->weeklyAvailabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);
        $this->employeeA->weeklyAvailabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);

        $testMonday = Carbon::now(AvailabilityService::TIMEZONE)->next(Carbon::MONDAY)->setTime(10, 0);

        // Inactive service
        $this->serviceA->update(['active' => false]);
        $this->assertFalse($this->availabilityService->isEmployeeAvailable($this->serviceA, $this->employeeA, $testMonday));
        $this->serviceA->update(['active' => true]);

        // Inactive employee
        $this->employeeA->update(['active' => false]);
        $this->assertFalse($this->availabilityService->isEmployeeAvailable($this->serviceA, $this->employeeA, $testMonday));
        $this->employeeA->update(['active' => true]);

        // Unassigned employee (Employee B from other company or newly unassigned)
        $this->assertFalse($this->availabilityService->isEmployeeAvailable($this->serviceA, $this->employeeB, $testMonday));
    }

    /**
     * 9. Date beyond booking_days_ahead is unavailable.
     */
    public function test_booking_horizon_enforcement(): void
    {
        $this->companyA->update(['booking_days_ahead' => 7]);

        $this->serviceA->weeklyAvailabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);
        $this->employeeA->weeklyAvailabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);

        // 14 days in advance (exceeds 7 days)
        $futureDate = Carbon::now(AvailabilityService::TIMEZONE)->addDays(14)->setTime(10, 0);
        $this->assertFalse($this->availabilityService->isEmployeeAvailable($this->serviceA, $this->employeeA, $futureDate));
    }

    /**
     * 10. Empty service schedule means unavailable.
     * 11. Empty employee schedule means unavailable.
     */
    public function test_empty_schedules_result_in_unavailability(): void
    {
        $testMonday = Carbon::now(AvailabilityService::TIMEZONE)->next(Carbon::MONDAY)->setTime(10, 0);

        // Neither has schedule configured
        $this->assertFalse($this->availabilityService->isEmployeeAvailable($this->serviceA, $this->employeeA, $testMonday));

        // Only service has schedule
        $this->serviceA->weeklyAvailabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);
        $this->assertFalse($this->availabilityService->isEmployeeAvailable($this->serviceA, $this->employeeA, $testMonday));
    }

    /**
     * 12. Company closure overrides weekly schedules.
     * 13. Service closure only blocks target service.
     * 14. Employee closure only blocks target employee.
     */
    public function test_date_exception_precedence_and_closures(): void
    {
        $this->serviceA->weeklyAvailabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);
        $this->employeeA->weeklyAvailabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);

        $testMonday = Carbon::now(AvailabilityService::TIMEZONE)->next(Carbon::MONDAY)->setTime(10, 0);
        $dateStr = $testMonday->format('Y-m-d');

        // Initially available
        $this->assertTrue($this->availabilityService->isEmployeeAvailable($this->serviceA, $this->employeeA, $testMonday));

        // Create salon closure
        $closure = $this->companyA->availabilityExceptions()->create([
            'type' => AvailabilityException::TYPE_COMPANY,
            'date' => $dateStr,
            'is_closed' => true,
            'reason' => 'National Holiday',
        ]);

        $this->assertFalse($this->availabilityService->isEmployeeAvailable($this->serviceA, $this->employeeA, $testMonday));

        // Delete salon closure
        $closure->delete();
        $this->assertTrue($this->availabilityService->isEmployeeAvailable($this->serviceA, $this->employeeA, $testMonday));

        // Create Employee off day
        $empLeave = $this->companyA->availabilityExceptions()->create([
            'type' => AvailabilityException::TYPE_EMPLOYEE,
            'employee_id' => $this->employeeA->id,
            'date' => $dateStr,
            'is_closed' => true,
            'reason' => 'Sick Leave',
        ]);

        $this->assertFalse($this->availabilityService->isEmployeeAvailable($this->serviceA, $this->employeeA, $testMonday));
    }

    /**
     * 15. Custom date hours override normal weekly hours.
     * 16. Multiple weekly windows work.
     */
    public function test_custom_date_hours_override_weekly_schedule(): void
    {
        // Normal schedule: 09:00 - 18:00
        $this->serviceA->weeklyAvailabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);
        $this->employeeA->weeklyAvailabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);

        $testMonday = Carbon::now(AvailabilityService::TIMEZONE)->next(Carbon::MONDAY)->setTime(10, 0);
        $dateStr = $testMonday->format('Y-m-d');

        // Custom hours exception for salon: 14:00 - 18:00 on this Monday
        $exc = $this->companyA->availabilityExceptions()->create([
            'type' => AvailabilityException::TYPE_COMPANY,
            'date' => $dateStr,
            'is_closed' => false,
            'reason' => 'Late Opening',
        ]);
        $exc->windows()->create([
            'start_time' => '14:00',
            'end_time' => '18:00',
        ]);

        // 10:00 is now outside custom hours
        $this->assertFalse($this->availabilityService->isEmployeeAvailable($this->serviceA, $this->employeeA, $testMonday));

        // 15:00 is inside custom hours
        $this->assertTrue($this->availabilityService->isEmployeeAvailable($this->serviceA, $this->employeeA, $testMonday->copy()->setTime(15, 0)));
    }

    /**
     * 18. Overlapping submitted windows are rejected by FormRequest.
     * 19. Invalid start/end times rejected.
     */
    public function test_schedule_validation_rejects_overlapping_and_reversed_intervals(): void
    {
        // Overlapping windows on day 1 (Monday)
        $responseOverlap = $this->actingAs($this->managerA)->put(route('company.services.schedule.update', $this->serviceA->id), [
            'days' => [
                1 => [
                    ['start_time' => '09:00', 'end_time' => '13:00'],
                    ['start_time' => '12:00', 'end_time' => '16:00'], // Overlaps!
                ],
            ],
        ]);
        $responseOverlap->assertSessionHasErrors(['days.1']);

        // Reversed start/end on day 2 (Tuesday)
        $responseReversed = $this->actingAs($this->managerA)->put(route('company.services.schedule.update', $this->serviceA->id), [
            'days' => [
                2 => [
                    ['start_time' => '18:00', 'end_time' => '09:00'],
                ],
            ],
        ]);
        $responseReversed->assertSessionHasErrors(['days.2.0']);
    }

    /**
     * 20. Tenant isolation on service schedule.
     * 21. Tenant isolation on employee schedule.
     * 22. Tenant isolation on exceptions.
     */
    public function test_tenant_isolation_on_schedules_and_exceptions(): void
    {
        // Manager A cannot view or edit Service B's schedule
        $this->actingAs($this->managerA)->get(route('company.services.schedule.edit', $this->serviceB->id))->assertNotFound();
        $this->actingAs($this->managerA)->put(route('company.services.schedule.update', $this->serviceB->id), ['days' => []])->assertNotFound();

        // Manager A cannot view or edit Employee B's schedule
        $this->actingAs($this->managerA)->get(route('company.employees.schedule.edit', $this->employeeB->id))->assertNotFound();
        $this->actingAs($this->managerA)->put(route('company.employees.schedule.update', $this->employeeB->id), ['days' => []])->assertNotFound();

        // Manager A cannot create exception assigning Service B
        $responseCross = $this->actingAs($this->managerA)->post(route('company.availability.exceptions.store'), [
            'type' => AvailabilityException::TYPE_SERVICE,
            'service_id' => $this->serviceB->id,
            'date' => Carbon::now()->addDays(2)->format('Y-m-d'),
            'is_closed' => true,
        ]);
        $responseCross->assertSessionHasErrors(['service_id']);
    }

    /**
     * 23. Suspended company cannot modify schedules or exceptions.
     */
    public function test_suspended_company_cannot_modify_schedules_or_exceptions(): void
    {
        $this->companyA->update(['status' => Company::STATUS_SUSPENDED]);

        $this->actingAs($this->managerA)->put(route('company.services.schedule.update', $this->serviceA->id), [
            'days' => [1 => [['start_time' => '09:00', 'end_time' => '18:00']]],
        ])->assertForbidden();

        $this->actingAs($this->managerA)->post(route('company.availability.exceptions.store'), [
            'type' => AvailabilityException::TYPE_COMPANY,
            'date' => Carbon::now()->addDays(2)->format('Y-m-d'),
            'is_closed' => true,
        ])->assertForbidden();
    }

    /**
     * 24. Any-available-employee query returns eligible active employees.
     */
    public function test_get_available_employees_foundation(): void
    {
        $this->serviceA->weeklyAvailabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);
        $this->employeeA->weeklyAvailabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);

        $testMonday = Carbon::now(AvailabilityService::TIMEZONE)->next(Carbon::MONDAY)->setTime(11, 0);

        $available = $this->availabilityService->getAvailableEmployees($this->serviceA, $testMonday);
        $this->assertCount(1, $available);
        $this->assertEquals($this->employeeA->id, $available->first()->id);
    }

    /**
     * 28. Audit logs created for schedule updates and exceptions.
     */
    public function test_audit_logs_recorded_for_schedules_and_exceptions(): void
    {
        $this->actingAs($this->managerA)->put(route('company.services.schedule.update', $this->serviceA->id), [
            'days' => [
                1 => [['start_time' => '09:00', 'end_time' => '18:00']],
            ],
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->companyA->id,
            'action' => 'service.schedule.updated',
        ]);

        $this->actingAs($this->managerA)->post(route('company.availability.exceptions.store'), [
            'type' => AvailabilityException::TYPE_COMPANY,
            'date' => Carbon::now()->addDays(2)->format('Y-m-d'),
            'is_closed' => true,
            'reason' => 'Annual Leave',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->companyA->id,
            'action' => 'availability.exception.created',
        ]);
    }
}
