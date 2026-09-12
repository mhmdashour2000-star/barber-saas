<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCompanyStatusTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SYSTEM_ADMIN,
        ]);
    }

    private function createCompany(string $status = Company::STATUS_PENDING): Company
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_COMPANY_MANAGER,
        ]);

        return Company::create([
            'code' => Company::generateUniqueCode(),
            'name' => 'Test Barber Studio',
            'manager_id' => $manager->id,
            'phone' => '+90 555 123 4567',
            'status' => $status,
            'address' => 'Test Address, Istanbul',
            'map_url' => 'https://maps.google.com/?q=istanbul',
            'booking_days_ahead' => 7,
            'late_cancellation_hours' => 24,
            'violation_limit' => 3,
            'block_duration_days' => 7,
        ]);
    }

    /**
     * 1. System Admin can activate a pending company.
     */
    public function test_system_admin_can_activate_pending_company(): void
    {
        $admin = $this->createAdmin();
        $company = $this->createCompany(Company::STATUS_PENDING);

        $response = $this->actingAs($admin)->patch("/admin/companies/{$company->id}/status", [
            'status' => Company::STATUS_ACTIVE,
        ]);

        $response->assertSessionHas('status');
        $company->refresh();
        $this->assertEquals(Company::STATUS_ACTIVE, $company->status);
    }

    /**
     * 2. System Admin can suspend an active company.
     */
    public function test_system_admin_can_suspend_active_company(): void
    {
        $admin = $this->createAdmin();
        $company = $this->createCompany(Company::STATUS_ACTIVE);

        $response = $this->actingAs($admin)->patch("/admin/companies/{$company->id}/status", [
            'status' => Company::STATUS_SUSPENDED,
        ]);

        $response->assertSessionHas('status');
        $company->refresh();
        $this->assertEquals(Company::STATUS_SUSPENDED, $company->status);
    }

    /**
     * 3. System Admin can reactivate a suspended company.
     */
    public function test_system_admin_can_reactivate_suspended_company(): void
    {
        $admin = $this->createAdmin();
        $company = $this->createCompany(Company::STATUS_SUSPENDED);

        $response = $this->actingAs($admin)->patch("/admin/companies/{$company->id}/status", [
            'status' => Company::STATUS_ACTIVE,
        ]);

        $response->assertSessionHas('status');
        $company->refresh();
        $this->assertEquals(Company::STATUS_ACTIVE, $company->status);
    }

    /**
     * 4. Invalid company status is rejected.
     */
    public function test_invalid_company_status_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $company = $this->createCompany(Company::STATUS_PENDING);

        $response = $this->actingAs($admin)->patch("/admin/companies/{$company->id}/status", [
            'status' => 'invalid_status_value',
        ]);

        $response->assertSessionHasErrors(['status']);
        $company->refresh();
        $this->assertEquals(Company::STATUS_PENDING, $company->status);
    }

    /**
     * 5. Company Manager cannot change company status.
     */
    public function test_company_manager_cannot_change_company_status(): void
    {
        $company = $this->createCompany(Company::STATUS_PENDING);
        $manager = $company->manager;

        $response = $this->actingAs($manager)->patch("/admin/companies/{$company->id}/status", [
            'status' => Company::STATUS_ACTIVE,
        ]);

        $response->assertForbidden();
        $company->refresh();
        $this->assertEquals(Company::STATUS_PENDING, $company->status);
    }

    /**
     * 6. Guest cannot change company status.
     */
    public function test_guest_cannot_change_company_status(): void
    {
        $company = $this->createCompany(Company::STATUS_PENDING);

        $response = $this->patch("/admin/companies/{$company->id}/status", [
            'status' => Company::STATUS_ACTIVE,
        ]);

        $response->assertRedirect(route('admin.login'));
        $company->refresh();
        $this->assertEquals(Company::STATUS_PENDING, $company->status);
    }

    /**
     * 7. Audit log is created when status changes.
     */
    public function test_audit_log_is_created_when_status_changes(): void
    {
        $admin = $this->createAdmin();
        $company = $this->createCompany(Company::STATUS_PENDING);

        $this->actingAs($admin)->patch("/admin/companies/{$company->id}/status", [
            'status' => Company::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'action' => 'company.status.updated',
            'description' => "Company {$company->code} status changed from pending to active.",
        ]);
    }
}
