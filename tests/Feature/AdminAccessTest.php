<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login_when_accessing_dashboard(): void
    {
        $response = $this->get('/admin/dashboard');

        $response->assertRedirect(route('admin.login'));
    }

    public function test_guest_is_redirected_to_admin_login_when_accessing_companies(): void
    {
        $response = $this->get('/admin/companies');

        $response->assertRedirect(route('admin.login'));
    }

    public function test_company_manager_is_forbidden_from_admin_dashboard(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_COMPANY_MANAGER,
        ]);

        $response = $this->actingAs($manager)->get('/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_company_manager_is_forbidden_from_admin_companies(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_COMPANY_MANAGER,
        ]);

        $response = $this->actingAs($manager)->get('/admin/companies');

        $response->assertForbidden();
    }

    public function test_system_admin_can_access_admin_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SYSTEM_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertSee('System Admin Dashboard');
    }

    public function test_system_admin_can_access_companies_list(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SYSTEM_ADMIN,
        ]);

        $manager = User::factory()->create([
            'role' => User::ROLE_COMPANY_MANAGER,
        ]);

        $company = Company::create([
            'code' => 'SLN-TEST1',
            'name' => 'Test Barber Salon',
            'manager_id' => $manager->id,
            'phone' => '+90 555 123 4567',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($admin)->get('/admin/companies');

        $response->assertOk();
        $response->assertSee('SLN-TEST1');
        $response->assertSee('Test Barber Salon');
    }

    public function test_system_admin_can_access_company_details(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SYSTEM_ADMIN,
        ]);

        $manager = User::factory()->create([
            'role' => User::ROLE_COMPANY_MANAGER,
        ]);

        $company = Company::create([
            'code' => 'SLN-TEST2',
            'name' => 'Royal Cuts Studio',
            'manager_id' => $manager->id,
            'phone' => '+90 555 987 6543',
            'status' => Company::STATUS_PENDING,
        ]);

        $response = $this->actingAs($admin)->get("/admin/companies/{$company->id}");

        $response->assertOk();
        $response->assertSee('SLN-TEST2');
        $response->assertSee('Royal Cuts Studio');
        $response->assertSee('Pending Review');
    }

    public function test_system_admin_can_login_via_admin_login_form(): void
    {
        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@barbar.local',
            'password' => bcrypt('Admin123456!'),
            'role' => User::ROLE_SYSTEM_ADMIN,
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'admin@barbar.local',
            'password' => 'Admin123456!',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_company_manager_cannot_login_via_admin_portal(): void
    {
        User::create([
            'name' => 'Manager User',
            'email' => 'manager@test.local',
            'password' => bcrypt('Password123!'),
            'role' => User::ROLE_COMPANY_MANAGER,
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'manager@test.local',
            'password' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
