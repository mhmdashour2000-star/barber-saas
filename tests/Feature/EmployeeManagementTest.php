<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createCompanyManager(array $userAttributes = [], array $companyAttributes = []): array
    {
        $manager = User::factory()->create(array_merge([
            'role' => User::ROLE_COMPANY_MANAGER,
        ], $userAttributes));

        $company = Company::create(array_merge([
            'code' => Company::generateUniqueCode(),
            'name' => 'Barbar Salon Studio',
            'manager_id' => $manager->id,
            'phone' => '+90 555 111 2233',
            'status' => Company::STATUS_ACTIVE,
            'address' => 'Nisantasi, Istanbul',
            'map_url' => 'https://maps.google.com/?q=nisantasi',
            'booking_days_ahead' => 7,
            'late_cancellation_hours' => 24,
            'violation_limit' => 3,
            'block_duration_days' => 7,
        ], $companyAttributes));

        return [$manager, $company];
    }

    /**
     * 1. Unauthenticated guests are redirected to login.
     */
    public function test_guest_cannot_access_employee_pages(): void
    {
        $this->get('/company/employees')->assertRedirect(route('login'));
        $this->get('/company/employees/create')->assertRedirect(route('login'));
        $this->post('/company/employees', [])->assertRedirect(route('login'));
        $this->get('/company/employees/1/edit')->assertRedirect(route('login'));
        $this->put('/company/employees/1', [])->assertRedirect(route('login'));
        $this->patch('/company/employees/1/status', [])->assertRedirect(route('login'));
    }

    /**
     * 2. Non-company managers (System Admin, Customer) receive 403 Forbidden.
     */
    public function test_non_company_managers_are_forbidden(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SYSTEM_ADMIN]);
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($admin)->get('/company/employees')->assertForbidden();
        $this->actingAs($customer)->get('/company/employees')->assertForbidden();
        $this->actingAs($admin)->get('/company/employees/create')->assertForbidden();
        $this->actingAs($admin)->post('/company/employees', [])->assertForbidden();
    }

    /**
     * 3. Company Manager can view employee listing with empty state.
     */
    public function test_company_manager_can_view_employee_index_empty_state(): void
    {
        [$manager, $company] = $this->createCompanyManager();

        $response = $this->actingAs($manager)->get('/company/employees');

        $response->assertOk();
        $response->assertSee('Staff & Employee Management');
        $response->assertSee('No employees registered yet');
        $response->assertSee(route('company.employees.create'));
    }

    /**
     * 4. Company Manager can create employee through company relationship.
     */
    public function test_company_manager_can_create_employee(): void
    {
        [$manager, $company] = $this->createCompanyManager();

        $response = $this->actingAs($manager)->post('/company/employees', [
            'name' => 'Tarik Berber',
            'username' => 'tarik_master',
            'phone' => '+90 532 999 8877',
            'password' => 'SuperSecret123',
        ]);

        $response->assertRedirect(route('company.employees.index'));
        $response->assertSessionHas('status', 'Employee created successfully.');

        $this->assertDatabaseHas('employees', [
            'company_id' => $company->id,
            'name' => 'Tarik Berber',
            'username' => 'tarik_master',
            'phone' => '+90 532 999 8877',
            'active' => true,
            'must_change_password' => true,
        ]);
    }

    /**
     * 5. Password is automatically hashed and plain text is never stored.
     */
    public function test_employee_password_is_properly_hashed(): void
    {
        [$manager, $company] = $this->createCompanyManager();

        $this->actingAs($manager)->post('/company/employees', [
            'name' => 'Hakan Berber',
            'username' => 'hakan_pro',
            'phone' => '+90 532 111 2233',
            'password' => 'PlainPassword88!',
        ]);

        $employee = Employee::where('username', 'hakan_pro')->first();
        $this->assertNotNull($employee);
        $this->assertNotEquals('PlainPassword88!', $employee->password);
        $this->assertTrue(Hash::check('PlainPassword88!', $employee->password));
    }

    /**
     * 6. company_id spoof prevention: input company_id is ignored.
     */
    public function test_company_id_cannot_be_spoofed_on_creation(): void
    {
        [$managerA, $companyA] = $this->createCompanyManager(['email' => 'mgr_a@example.com']);
        [$managerB, $companyB] = $this->createCompanyManager(['email' => 'mgr_b@example.com']);

        $this->actingAs($managerA)->post('/company/employees', [
            'company_id' => $companyB->id,
            'name' => 'Spoof Attempt',
            'username' => 'spoof_user',
            'phone' => null,
            'password' => 'Password123',
        ]);

        $employee = Employee::where('username', 'spoof_user')->first();
        $this->assertNotNull($employee);
        $this->assertEquals($companyA->id, $employee->company_id);
        $this->assertNotEquals($companyB->id, $employee->company_id);
    }

    /**
     * 7. Username uniqueness is strictly scoped per company.
     */
    public function test_tenant_scoped_username_uniqueness_prevents_duplicates_in_same_company(): void
    {
        [$manager, $company] = $this->createCompanyManager();

        $company->employees()->create([
            'name' => 'Existing Barber',
            'username' => 'master_cut',
            'phone' => null,
            'password' => 'Password123',
            'active' => true,
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($manager)
            ->from(route('company.employees.create'))
            ->post('/company/employees', [
                'name' => 'Another Barber',
                'username' => 'master_cut',
                'phone' => null,
                'password' => 'Password123',
            ]);

        $response->assertRedirect(route('company.employees.create'));
        $response->assertSessionHasErrors(['username']);
    }

    /**
     * 8. Same username is allowed in different companies.
     */
    public function test_same_username_allowed_in_different_companies(): void
    {
        [$managerA, $companyA] = $this->createCompanyManager(['email' => 'm1@example.com']);
        [$managerB, $companyB] = $this->createCompanyManager(['email' => 'm2@example.com']);

        $companyA->employees()->create([
            'name' => 'Barber A',
            'username' => 'barber_one',
            'password' => 'Password123',
            'active' => true,
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($managerB)->post('/company/employees', [
            'name' => 'Barber B',
            'username' => 'barber_one',
            'password' => 'Password456',
        ]);

        $response->assertRedirect(route('company.employees.index'));
        $this->assertCount(2, Employee::where('username', 'barber_one')->get());
    }

    /**
     * 9. Tenant isolation: Manager A cannot view edit page of Manager B's employee.
     */
    public function test_tenant_isolation_prevents_manager_a_from_viewing_manager_b_employee(): void
    {
        [$managerA, $companyA] = $this->createCompanyManager(['email' => 'a@example.com']);
        [$managerB, $companyB] = $this->createCompanyManager(['email' => 'b@example.com']);

        $employeeB = $companyB->employees()->create([
            'name' => 'Company B Barber',
            'username' => 'b_barber',
            'password' => 'Secret123',
            'active' => true,
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($managerA)->get("/company/employees/{$employeeB->id}/edit");

        $response->assertNotFound();
    }

    /**
     * 10. Tenant isolation: Manager A cannot update Manager B's employee.
     */
    public function test_tenant_isolation_prevents_cross_company_edit(): void
    {
        [$managerA, $companyA] = $this->createCompanyManager(['email' => 'a1@example.com']);
        [$managerB, $companyB] = $this->createCompanyManager(['email' => 'b1@example.com']);

        $employeeB = $companyB->employees()->create([
            'name' => 'Original B Barber',
            'username' => 'orig_b_barber',
            'phone' => '123',
            'password' => 'Secret123',
            'active' => true,
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($managerA)->put("/company/employees/{$employeeB->id}", [
            'name' => 'Malicious Hack Attempt',
            'username' => 'hacked_barber',
            'phone' => '999',
        ]);

        $response->assertNotFound();

        $employeeB->refresh();
        $this->assertEquals('Original B Barber', $employeeB->name);
        $this->assertEquals('orig_b_barber', $employeeB->username);
    }

    /**
     * 11. Tenant isolation: Manager A cannot toggle status of Manager B's employee.
     */
    public function test_tenant_isolation_prevents_cross_company_status_update(): void
    {
        [$managerA, $companyA] = $this->createCompanyManager(['email' => 'a2@example.com']);
        [$managerB, $companyB] = $this->createCompanyManager(['email' => 'b2@example.com']);

        $employeeB = $companyB->employees()->create([
            'name' => 'Target B Barber',
            'username' => 'target_b',
            'password' => 'Secret123',
            'active' => true,
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($managerA)->patch("/company/employees/{$employeeB->id}/status", [
            'active' => false,
        ]);

        $response->assertNotFound();

        $employeeB->refresh();
        $this->assertTrue($employeeB->active);
    }

    /**
     * 12. Standard employee edit updates only allowed attributes (name, username, phone).
     */
    public function test_manager_can_edit_own_employee_allowed_fields_only(): void
    {
        [$manager, $company] = $this->createCompanyManager();

        $employee = $company->employees()->create([
            'name' => 'Old Name',
            'username' => 'old_user',
            'phone' => '+90 555 111 0000',
            'password' => 'OriginalPassword123',
            'active' => true,
            'must_change_password' => true,
        ]);

        $oldPasswordHash = $employee->password;

        $response = $this->actingAs($manager)->put("/company/employees/{$employee->id}", [
            'name' => 'New Name',
            'username' => 'new_user',
            'phone' => '+90 555 222 3333',
            // Attempt to maliciously modify uneditable fields:
            'company_id' => 9999,
            'password' => 'ChangedPassword123',
            'active' => false,
            'must_change_password' => false,
        ]);

        $response->assertRedirect(route('company.employees.index'));
        $response->assertSessionHas('status', 'Employee updated successfully.');

        $employee->refresh();
        $this->assertEquals('New Name', $employee->name);
        $this->assertEquals('new_user', $employee->username);
        $this->assertEquals('+90 555 222 3333', $employee->phone);
        $this->assertEquals($company->id, $employee->company_id);
        $this->assertEquals($oldPasswordHash, $employee->password);
        $this->assertTrue($employee->active);
        $this->assertTrue($employee->must_change_password);
    }

    /**
     * 13. Manager can deactivate and activate employee via dedicated status endpoint.
     */
    public function test_manager_can_deactivate_and_activate_employee(): void
    {
        [$manager, $company] = $this->createCompanyManager();

        $employee = $company->employees()->create([
            'name' => 'Active Barber',
            'username' => 'active_barber',
            'password' => 'Password123',
            'active' => true,
            'must_change_password' => true,
        ]);

        // Deactivate
        $response = $this->actingAs($manager)->patch("/company/employees/{$employee->id}/status", [
            'active' => false,
        ]);
        $response->assertRedirect(route('company.employees.index'));
        $response->assertSessionHas('status', 'Employee deactivated successfully.');

        $employee->refresh();
        $this->assertFalse($employee->active);

        // Activate
        $response2 = $this->actingAs($manager)->patch("/company/employees/{$employee->id}/status", [
            'active' => true,
        ]);
        $response2->assertRedirect(route('company.employees.index'));
        $response2->assertSessionHas('status', 'Employee activated successfully.');

        $employee->refresh();
        $this->assertTrue($employee->active);
    }

    /**
     * 14. Employee is never deleted when deactivated.
     */
    public function test_employee_is_not_deleted_when_deactivated(): void
    {
        [$manager, $company] = $this->createCompanyManager();

        $employee = $company->employees()->create([
            'name' => 'Permanent Record',
            'username' => 'perm_rec',
            'password' => 'Password123',
            'active' => true,
            'must_change_password' => true,
        ]);

        $this->actingAs($manager)->patch("/company/employees/{$employee->id}/status", [
            'active' => false,
        ]);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'active' => false,
        ]);
    }

    /**
     * 15. Audit logs recorded for employee actions, and password is never logged.
     */
    public function test_audit_logs_recorded_and_passwords_never_logged(): void
    {
        [$manager, $company] = $this->createCompanyManager();

        // 1. Create employee
        $this->actingAs($manager)->post('/company/employees', [
            'name' => 'Audit Test Staff',
            'username' => 'audit_staff',
            'phone' => '+90 555 444 3322',
            'password' => 'SecretPass_998877',
        ]);

        $employee = Employee::where('username', 'audit_staff')->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'user_id' => $manager->id,
            'action' => 'employee.created',
        ]);

        // 2. Update employee
        $this->actingAs($manager)->put("/company/employees/{$employee->id}", [
            'name' => 'Audit Test Staff Renamed',
            'username' => 'audit_staff_renamed',
            'phone' => '+90 555 444 3322',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'user_id' => $manager->id,
            'action' => 'employee.updated',
        ]);

        // 3. Deactivate employee
        $this->actingAs($manager)->patch("/company/employees/{$employee->id}/status", [
            'active' => false,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'user_id' => $manager->id,
            'action' => 'employee.deactivated',
        ]);

        // 4. Activate employee
        $this->actingAs($manager)->patch("/company/employees/{$employee->id}/status", [
            'active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'user_id' => $manager->id,
            'action' => 'employee.activated',
        ]);

        // Verify password never appears anywhere in audit logs
        $logs = AuditLog::where('company_id', $company->id)->get();
        foreach ($logs as $log) {
            $this->assertStringNotContainsString('SecretPass_998877', (string) $log->description);
            $this->assertStringNotContainsString('SecretPass_998877', (string) json_encode($log->toArray()));
        }
    }

    /**
     * 16. Employee search by name and by username.
     */
    public function test_employee_search_by_name_and_username(): void
    {
        [$manager, $company] = $this->createCompanyManager();

        $emp1 = $company->employees()->create([
            'name' => 'Alexander Hamilton',
            'username' => 'alex_ham',
            'password' => 'Pass1234',
            'active' => true,
            'must_change_password' => true,
        ]);

        $emp2 = $company->employees()->create([
            'name' => 'Benjamin Franklin',
            'username' => 'ben_frank',
            'password' => 'Pass1234',
            'active' => true,
            'must_change_password' => true,
        ]);

        // Search by Name
        $responseName = $this->actingAs($manager)->get('/company/employees?search=Hamilton');
        $responseName->assertOk();
        $responseName->assertSee('Alexander Hamilton');
        $responseName->assertDontSee('Benjamin Franklin');

        // Search by Username
        $responseUser = $this->actingAs($manager)->get('/company/employees?search=ben_frank');
        $responseUser->assertOk();
        $responseUser->assertSee('Benjamin Franklin');
        $responseUser->assertDontSee('Alexander Hamilton');
    }

    /**
     * 17. Status filtering (active vs inactive).
     */
    public function test_employee_status_filtering(): void
    {
        [$manager, $company] = $this->createCompanyManager();

        $activeEmp = $company->employees()->create([
            'name' => 'Active Specialist',
            'username' => 'spec_active',
            'password' => 'Pass1234',
            'active' => true,
            'must_change_password' => true,
        ]);

        $inactiveEmp = $company->employees()->create([
            'name' => 'Inactive Specialist',
            'username' => 'spec_inactive',
            'password' => 'Pass1234',
            'active' => false,
            'must_change_password' => true,
        ]);

        // Filter active
        $responseActive = $this->actingAs($manager)->get('/company/employees?status=active');
        $responseActive->assertOk();
        $responseActive->assertSee('Active Specialist');
        $responseActive->assertDontSee('Inactive Specialist');

        // Filter inactive
        $responseInactive = $this->actingAs($manager)->get('/company/employees?status=inactive');
        $responseInactive->assertOk();
        $responseInactive->assertSee('Inactive Specialist');
        $responseInactive->assertDontSee('Active Specialist');
    }

    /**
     * 18. Tenant-scoped dashboard employee statistics.
     */
    public function test_tenant_scoped_dashboard_employee_counts(): void
    {
        [$managerA, $companyA] = $this->createCompanyManager(['email' => 'dash_a@example.com']);
        [$managerB, $companyB] = $this->createCompanyManager(['email' => 'dash_b@example.com']);

        // Company A: 2 active, 1 inactive (total 3)
        $companyA->employees()->create(['name' => 'A1', 'username' => 'a1', 'password' => 'pw', 'active' => true, 'must_change_password' => true]);
        $companyA->employees()->create(['name' => 'A2', 'username' => 'a2', 'password' => 'pw', 'active' => true, 'must_change_password' => true]);
        $companyA->employees()->create(['name' => 'A3', 'username' => 'a3', 'password' => 'pw', 'active' => false, 'must_change_password' => true]);

        // Company B: 5 active, 2 inactive (total 7)
        for ($i = 1; $i <= 5; $i++) {
            $companyB->employees()->create(['name' => "B{$i}", 'username' => "b{$i}", 'password' => 'pw', 'active' => true, 'must_change_password' => true]);
        }
        for ($j = 1; $j <= 2; $j++) {
            $companyB->employees()->create(['name' => "B_in_{$j}", 'username' => "b_in_{$j}", 'password' => 'pw', 'active' => false, 'must_change_password' => true]);
        }

        // Manager A visits Dashboard
        $responseA = $this->actingAs($managerA)->get('/company/dashboard');
        $responseA->assertOk();
        $responseA->assertSee('Salon Staff & Employees');
        // Stats in view for Manager A
        $responseA->assertViewHas('employeeStats', [
            'total' => 3,
            'active' => 2,
            'inactive' => 1,
        ]);

        // Manager B visits Dashboard
        $responseB = $this->actingAs($managerB)->get('/company/dashboard');
        $responseB->assertOk();
        $responseB->assertViewHas('employeeStats', [
            'total' => 7,
            'active' => 5,
            'inactive' => 2,
        ]);
    }

    /**
     * 19. Suspended company cannot create employee.
     */
    public function test_suspended_company_cannot_create_employee(): void
    {
        [$manager, $company] = $this->createCompanyManager([], ['status' => Company::STATUS_SUSPENDED]);

        $this->actingAs($manager)->get('/company/employees/create')->assertForbidden();

        $response = $this->actingAs($manager)->post('/company/employees', [
            'name' => 'Blocked Barber',
            'username' => 'blocked_barber',
            'password' => 'Secret123',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('employees', ['username' => 'blocked_barber']);
    }

    /**
     * 20. Suspended company cannot edit employee.
     */
    public function test_suspended_company_cannot_edit_employee(): void
    {
        [$manager, $company] = $this->createCompanyManager([], ['status' => Company::STATUS_ACTIVE]);
        $employee = $company->employees()->create([
            'name' => 'Pre-existing Barber',
            'username' => 'pre_barber',
            'password' => 'Secret123',
            'active' => true,
            'must_change_password' => true,
        ]);

        // Suspend company
        $company->update(['status' => Company::STATUS_SUSPENDED]);

        $this->actingAs($manager)->get("/company/employees/{$employee->id}/edit")->assertForbidden();

        $response = $this->actingAs($manager)->put("/company/employees/{$employee->id}", [
            'name' => 'Attempted Edit',
            'username' => 'pre_barber',
        ]);

        $response->assertForbidden();
        $employee->refresh();
        $this->assertEquals('Pre-existing Barber', $employee->name);
    }

    /**
     * 21. Suspended company cannot change employee status.
     */
    public function test_suspended_company_cannot_change_employee_status(): void
    {
        [$manager, $company] = $this->createCompanyManager([], ['status' => Company::STATUS_ACTIVE]);
        $employee = $company->employees()->create([
            'name' => 'Status Barber',
            'username' => 'status_barber',
            'password' => 'Secret123',
            'active' => true,
            'must_change_password' => true,
        ]);

        // Suspend company
        $company->update(['status' => Company::STATUS_SUSPENDED]);

        $response = $this->actingAs($manager)->patch("/company/employees/{$employee->id}/status", [
            'active' => false,
        ]);

        $response->assertForbidden();
        $employee->refresh();
        $this->assertTrue($employee->active);
    }

    /**
     * 22. Employee creation success state displays login credentials once.
     */
    public function test_employee_creation_success_state_displays_login_credentials_once(): void
    {
        [$manager, $company] = $this->createCompanyManager();

        $createResponse = $this->actingAs($manager)->post('/company/employees', [
            'name' => 'Kemal Specialist',
            'username' => 'kemal_spec',
            'phone' => '+90 555 333 2211',
            'password' => 'KemalPass2026!',
        ]);

        $createResponse->assertRedirect(route('company.employees.index'));
        $createResponse->assertSessionHas('new_employee_credentials');

        // First visit (redirect destination): displays credentials
        $followResponse = $this->actingAs($manager)->get(route('company.employees.index'));
        $followResponse->assertOk();
        $followResponse->assertSee('Employee Login Information');
        $followResponse->assertSee($company->code);
        $followResponse->assertSee('kemal_spec');
        $followResponse->assertSee('KemalPass2026!');
        $followResponse->assertSee('Copy Company Code');
        $followResponse->assertSee('Copy Username');
        $followResponse->assertSee('Copy Password');
        $followResponse->assertSee('Copy All Login Details');
        $followResponse->assertSee('The temporary password will not be displayed again');

        // Second visit (refresh): credentials must NOT remain
        $refreshResponse = $this->actingAs($manager)->get(route('company.employees.index'));
        $refreshResponse->assertOk();
        $refreshResponse->assertDontSee('Employee Login Information');
        $refreshResponse->assertDontSee('KemalPass2026!');
    }
}
