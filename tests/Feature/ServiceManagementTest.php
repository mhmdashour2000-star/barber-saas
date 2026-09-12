<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $managerA;
    protected Company $companyA;
    protected Employee $employeeA1;
    protected Employee $employeeA2;

    protected User $managerB;
    protected Company $companyB;
    protected Employee $employeeB;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup Company A
        $this->managerA = User::factory()->create(['role' => User::ROLE_COMPANY_MANAGER]);
        $this->companyA = Company::create([
            'code' => Company::generateUniqueCode(),
            'name' => 'Salon Alpha',
            'manager_id' => $this->managerA->id,
            'phone' => '+905551112233',
            'status' => Company::STATUS_ACTIVE,
        ]);
        $this->employeeA1 = $this->companyA->employees()->create([
            'name' => 'Barber John',
            'username' => 'john_barber',
            'active' => true,
            'password' => 'secret123',
        ]);
        $this->employeeA2 = $this->companyA->employees()->create([
            'name' => 'Barber Mike',
            'username' => 'mike_barber',
            'active' => true,
            'password' => 'secret123',
        ]);

        // Setup Company B
        $this->managerB = User::factory()->create(['role' => User::ROLE_COMPANY_MANAGER]);
        $this->companyB = Company::create([
            'code' => Company::generateUniqueCode(),
            'name' => 'Salon Beta',
            'manager_id' => $this->managerB->id,
            'phone' => '+905554445566',
            'status' => Company::STATUS_ACTIVE,
        ]);
        $this->employeeB = $this->companyB->employees()->create([
            'name' => 'Barber Sarah',
            'username' => 'sarah_barber',
            'active' => true,
            'password' => 'secret123',
        ]);
    }

    /**
     * 1. Authenticated company manager can view own services list and empty state.
     */
    public function test_manager_can_view_services_list(): void
    {
        $response = $this->actingAs($this->managerA)->get(route('company.services.index'));

        $response->assertOk();
        $response->assertSee('Services &amp; Catalog', false);
        $response->assertSee('No Services Found');
    }

    /**
     * 2. Manager can create a service with price, duration, and staff assignments.
     * 3. Service belongs automatically to authenticated company.
     * 4. Submitted company_id cannot spoof ownership.
     * 5. Price is stored correctly in minor units (250.50 -> 25050).
     * 6. Duration is stored correctly.
     */
    public function test_manager_can_create_service_with_correct_minor_units_and_ownership(): void
    {
        $response = $this->actingAs($this->managerA)->post(route('company.services.store'), [
            'name' => 'Classic Haircut',
            'description' => 'Precision scissor cut and wash.',
            'price' => '250.50',
            'duration_minutes' => 45,
            'company_id' => $this->companyB->id, // Attempt to spoof company_id
            'employee_ids' => [$this->employeeA1->id, $this->employeeA2->id],
        ]);

        $response->assertRedirect(route('company.services.index'));
        $response->assertSessionHas('status', "Service 'Classic Haircut' created successfully.");

        $service = Service::where('name', 'Classic Haircut')->first();
        $this->assertNotNull($service);
        $this->assertEquals($this->companyA->id, $service->company_id, 'Service must belong to Company A despite submitted company_id');
        $this->assertEquals(25050, $service->price_minor_units);
        $this->assertEquals(250.50, $service->price_decimal);
        $this->assertEquals('250,50 ₺', $service->formatted_price);
        $this->assertEquals(45, $service->duration_minutes);
        $this->assertTrue($service->active);

        // Verify employees assigned
        $this->assertCount(2, $service->employees);
        $this->assertTrue($service->employees->contains($this->employeeA1));
        $this->assertTrue($service->employees->contains($this->employeeA2));
    }

    /**
     * 7. Manager can edit own service and reassign staff.
     */
    public function test_manager_can_edit_own_service(): void
    {
        $service = $this->companyA->services()->create([
            'name' => 'Beard Trim',
            'price_minor_units' => 10000,
            'duration_minutes' => 20,
            'active' => true,
        ]);
        $service->employees()->sync([$this->employeeA1->id]);

        $response = $this->actingAs($this->managerA)->put(route('company.services.update', $service->id), [
            'name' => 'Deluxe Beard Trim & Shaping',
            'description' => 'Includes hot towel and oil treatment.',
            'price' => '150.00',
            'duration_minutes' => 30,
            'employee_ids' => [$this->employeeA2->id],
        ]);

        $response->assertRedirect(route('company.services.index'));

        $service->refresh();
        $this->assertEquals('Deluxe Beard Trim & Shaping', $service->name);
        $this->assertEquals(15000, $service->price_minor_units);
        $this->assertEquals(30, $service->duration_minutes);
        $this->assertCount(1, $service->employees);
        $this->assertTrue($service->employees->contains($this->employeeA2));
        $this->assertFalse($service->employees->contains($this->employeeA1));
    }

    /**
     * 8. Manager can activate and deactivate own service via dedicated endpoint.
     * 9. Inactive service remains in database.
     */
    public function test_manager_can_deactivate_and_activate_service(): void
    {
        $service = $this->companyA->services()->create([
            'name' => 'Hair Coloring',
            'price_minor_units' => 50000,
            'duration_minutes' => 90,
            'active' => true,
        ]);

        // Deactivate
        $deactResponse = $this->actingAs($this->managerA)->patch(route('company.services.status', $service->id), [
            'active' => false,
        ]);

        $deactResponse->assertRedirect(route('company.services.index'));
        $service->refresh();
        $this->assertFalse($service->active);
        $this->assertDatabaseHas('services', ['id' => $service->id, 'active' => false]);

        // Reactivate
        $actResponse = $this->actingAs($this->managerA)->patch(route('company.services.status', $service->id), [
            'active' => true,
        ]);

        $actResponse->assertRedirect(route('company.services.index'));
        $service->refresh();
        $this->assertTrue($service->active);
    }

    /**
     * 10. Employee can be assigned to service.
     * 11. Multiple employees can be assigned.
     * 12. Same employee may provide multiple services.
     */
    public function test_employee_service_many_to_many_relationships(): void
    {
        $service1 = $this->companyA->services()->create([
            'name' => 'Service 1',
            'price_minor_units' => 10000,
            'duration_minutes' => 30,
        ]);
        $service2 = $this->companyA->services()->create([
            'name' => 'Service 2',
            'price_minor_units' => 20000,
            'duration_minutes' => 45,
        ]);

        $service1->employees()->attach([$this->employeeA1->id, $this->employeeA2->id]);
        $service2->employees()->attach([$this->employeeA1->id]);

        $this->assertCount(2, $service1->fresh()->employees);
        $this->assertCount(1, $service2->fresh()->employees);
        $this->assertCount(2, $this->employeeA1->fresh()->services);
        $this->assertCount(1, $this->employeeA2->fresh()->services);
    }

    /**
     * 13. Employee from another company cannot be assigned to Salon A service.
     */
    public function test_cannot_assign_employee_from_another_company(): void
    {
        $response = $this->actingAs($this->managerA)->post(route('company.services.store'), [
            'name' => 'Cross Company Attempt',
            'price' => '200.00',
            'duration_minutes' => 30,
            'employee_ids' => [$this->employeeB->id], // Employee from Company B!
        ]);

        $response->assertSessionHasErrors(['employee_ids.0']);
        $this->assertDatabaseMissing('services', ['name' => 'Cross Company Attempt']);
    }

    /**
     * 14. Manager cannot access another company's service edit page.
     * 15. Manager cannot update another company's service.
     * 16. Manager cannot change another company's service status.
     */
    public function test_tenant_isolation_prevents_cross_company_service_access(): void
    {
        $serviceB = $this->companyB->services()->create([
            'name' => 'Beta Secret Haircut',
            'price_minor_units' => 30000,
            'duration_minutes' => 60,
            'active' => true,
        ]);

        // Manager A tries to edit Service B
        $responseEdit = $this->actingAs($this->managerA)->get(route('company.services.edit', $serviceB->id));
        $responseEdit->assertNotFound();

        // Manager A tries to update Service B
        $responseUpdate = $this->actingAs($this->managerA)->put(route('company.services.update', $serviceB->id), [
            'name' => 'Hacked Name',
            'price' => '10.00',
            'duration_minutes' => 15,
        ]);
        $responseUpdate->assertNotFound();

        // Manager A tries to change Service B status
        $responseStatus = $this->actingAs($this->managerA)->patch(route('company.services.status', $serviceB->id), [
            'active' => false,
        ]);
        $responseStatus->assertNotFound();

        $serviceB->refresh();
        $this->assertEquals('Beta Secret Haircut', $serviceB->name);
        $this->assertTrue($serviceB->active);
    }

    /**
     * 17. Duplicate pivot assignment is prevented by database unique constraint.
     */
    public function test_duplicate_employee_service_pivot_is_prevented(): void
    {
        $service = $this->companyA->services()->create([
            'name' => 'Test Unique Pivot',
            'price_minor_units' => 10000,
            'duration_minutes' => 30,
        ]);

        $service->employees()->attach($this->employeeA1->id);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $service->employees()->attach($this->employeeA1->id);
    }

    /**
     * 18. Suspended company cannot create service.
     * 19. Suspended company cannot edit service.
     * 20. Suspended company cannot change service status.
     */
    public function test_suspended_company_cannot_perform_service_modifications(): void
    {
        $this->companyA->update(['status' => Company::STATUS_SUSPENDED]);

        $service = $this->companyA->services()->create([
            'name' => 'Suspended Salon Cut',
            'price_minor_units' => 20000,
            'duration_minutes' => 30,
        ]);

        // Create page forbidden
        $this->actingAs($this->managerA)->get(route('company.services.create'))->assertForbidden();

        // Store action forbidden
        $this->actingAs($this->managerA)->post(route('company.services.store'), [
            'name' => 'New Cut',
            'price' => '100.00',
            'duration_minutes' => 30,
        ])->assertForbidden();

        // Edit page forbidden
        $this->actingAs($this->managerA)->get(route('company.services.edit', $service->id))->assertForbidden();

        // Update action forbidden
        $this->actingAs($this->managerA)->put(route('company.services.update', $service->id), [
            'name' => 'Modified Cut',
            'price' => '150.00',
            'duration_minutes' => 30,
        ])->assertForbidden();

        // Status change forbidden
        $this->actingAs($this->managerA)->patch(route('company.services.status', $service->id), [
            'active' => false,
        ])->assertForbidden();
    }

    /**
     * 21. Service audit events are created for creation, update, and status toggle.
     */
    public function test_service_audit_events_are_recorded(): void
    {
        // Create
        $this->actingAs($this->managerA)->post(route('company.services.store'), [
            'name' => 'Audited Service',
            'price' => '200.00',
            'duration_minutes' => 45,
            'employee_ids' => [$this->employeeA1->id],
        ]);

        $service = Service::where('name', 'Audited Service')->first();
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->companyA->id,
            'user_id' => $this->managerA->id,
            'action' => 'service.created',
        ]);

        // Update
        $this->actingAs($this->managerA)->put(route('company.services.update', $service->id), [
            'name' => 'Audited Service Renamed',
            'price' => '250.00',
            'duration_minutes' => 50,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->companyA->id,
            'user_id' => $this->managerA->id,
            'action' => 'service.updated',
        ]);

        // Status Toggle
        $this->actingAs($this->managerA)->patch(route('company.services.status', $service->id), [
            'active' => false,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->companyA->id,
            'user_id' => $this->managerA->id,
            'action' => 'service.deactivated',
        ]);
    }

    /**
     * 22. Company dashboard reflects accurate service statistics.
     */
    public function test_company_dashboard_reflects_service_statistics(): void
    {
        $this->companyA->services()->create([
            'name' => 'Service Active 1',
            'price_minor_units' => 10000,
            'duration_minutes' => 30,
            'active' => true,
        ]);
        $this->companyA->services()->create([
            'name' => 'Service Active 2',
            'price_minor_units' => 15000,
            'duration_minutes' => 30,
            'active' => true,
        ]);
        $this->companyA->services()->create([
            'name' => 'Service Inactive',
            'price_minor_units' => 20000,
            'duration_minutes' => 30,
            'active' => false,
        ]);

        $response = $this->actingAs($this->managerA)->get(route('company.dashboard'));

        $response->assertOk();
        $response->assertSee('Salon Services &amp; Catalog', false);
        $response->assertSee('3'); // Total services
        $response->assertSee('2'); // Active services
        $response->assertSee('1'); // Inactive services
    }

    /**
     * 23. Sidebar renders Services link for Company Manager.
     */
    public function test_sidebar_renders_services_navigation_link(): void
    {
        $response = $this->actingAs($this->managerA)->get(route('company.dashboard'));

        $response->assertOk();
        $response->assertSee(route('company.services.index'));
        $response->assertSee('Services');
    }
}
