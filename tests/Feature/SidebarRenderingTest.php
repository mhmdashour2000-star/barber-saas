<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_render_with_sidebar_and_navigation(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SYSTEM_ADMIN,
        ]);

        // 1. Admin Dashboard
        $response = $this->actingAs($admin)->get('/admin/dashboard');
        $response->assertOk();
        $response->assertSee('id="app-sidebar"', false);
        $response->assertSee('id="desktop-sidebar-toggle"', false);
        $response->assertSee('id="mobile-sidebar-toggle"', false);
        $response->assertSee('Dashboard');
        $response->assertSee('Companies');
        $response->assertSee('System Administrator');
        $response->assertSee(route('admin.logout'));

        // 2. Admin Companies
        $companiesResponse = $this->actingAs($admin)->get('/admin/companies');
        $companiesResponse->assertOk();
        $companiesResponse->assertSee('id="app-sidebar"', false);
    }

    public function test_company_pages_render_with_sidebar_and_navigation(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_COMPANY_MANAGER,
        ]);

        $company = Company::create([
            'code' => Company::generateUniqueCode(),
            'name' => 'Sidebar Barber Studio',
            'manager_id' => $manager->id,
            'phone' => '+90 555 777 8899',
            'status' => Company::STATUS_ACTIVE,
            'address' => 'Etiler, Istanbul',
            'map_url' => 'https://maps.google.com/?q=etiler',
            'booking_days_ahead' => 7,
            'late_cancellation_hours' => 24,
            'violation_limit' => 3,
            'block_duration_days' => 7,
        ]);

        // 1. Company Dashboard
        $dashResponse = $this->actingAs($manager)->get('/company/dashboard');
        $dashResponse->assertOk();
        $dashResponse->assertSee('id="app-sidebar"', false);
        $dashResponse->assertSee('id="desktop-sidebar-toggle"', false);
        $dashResponse->assertSee('id="mobile-sidebar-toggle"', false);
        $dashResponse->assertSee('Dashboard');
        $dashResponse->assertSee('Employees');
        $dashResponse->assertSee('Settings');
        $dashResponse->assertSee($company->code);
        $dashResponse->assertSee($manager->name);
        $dashResponse->assertSee(route('logout'));

        // 2. Company Employees
        $empResponse = $this->actingAs($manager)->get('/company/employees');
        $empResponse->assertOk();
        $empResponse->assertSee('id="app-sidebar"', false);

        // 3. Company Settings
        $settingsResponse = $this->actingAs($manager)->get('/company/settings');
        $settingsResponse->assertOk();
        $settingsResponse->assertSee('id="app-sidebar"', false);
    }
}
