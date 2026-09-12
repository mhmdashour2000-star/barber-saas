<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    private function createCompanyManager(array $userAttributes = [], array $companyAttributes = []): array
    {
        $manager = User::factory()->create(array_merge([
            'role' => User::ROLE_COMPANY_MANAGER,
        ], $userAttributes));

        $company = Company::create(array_merge([
            'code' => Company::generateUniqueCode(),
            'name' => 'Elite Barber Studio',
            'manager_id' => $manager->id,
            'phone' => '+90 555 123 4567',
            'status' => Company::STATUS_ACTIVE,
            'address' => 'Bagdat Caddesi No: 10, Kadikoy, Istanbul',
            'map_url' => 'https://maps.google.com/?q=kadikoy',
            'booking_days_ahead' => 7,
            'late_cancellation_hours' => 24,
            'violation_limit' => 3,
            'block_duration_days' => 7,
        ], $companyAttributes));

        return [$manager, $company];
    }

    /**
     * 1. Guest is redirected from Company Manager dashboard.
     */
    public function test_guest_is_redirected_from_company_dashboard(): void
    {
        $response = $this->get('/company/dashboard');

        $response->assertRedirect(route('login'));
    }

    /**
     * 2. Guest is redirected from Company Settings.
     */
    public function test_guest_is_redirected_from_company_settings(): void
    {
        $response = $this->get('/company/settings');

        $response->assertRedirect(route('login'));
    }

    /**
     * 3. System Admin receives 403 when accessing Company Manager pages.
     */
    public function test_system_admin_receives_403_when_accessing_company_manager_pages(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SYSTEM_ADMIN,
        ]);

        $dashboardResponse = $this->actingAs($admin)->get('/company/dashboard');
        $dashboardResponse->assertForbidden();

        $settingsResponse = $this->actingAs($admin)->get('/company/settings');
        $settingsResponse->assertForbidden();

        $updateResponse = $this->actingAs($admin)->put('/company/settings', [
            'name' => 'Hacked Salon',
            'phone' => '+90 555 999 9999',
            'booking_days_ahead' => 14,
            'late_cancellation_hours' => 12,
            'violation_limit' => 5,
            'block_duration_days' => 14,
        ]);
        $updateResponse->assertForbidden();
    }

    /**
     * 4. Company Manager can open dashboard.
     */
    public function test_company_manager_can_open_dashboard(): void
    {
        [$manager, $company] = $this->createCompanyManager();

        $response = $this->actingAs($manager)->get('/company/dashboard');

        $response->assertOk();
        $response->assertSee($company->name);
        $response->assertSee($company->code);
        $response->assertSee('Active');
        $response->assertSee($manager->name);
        $response->assertSee($company->phone);
        $response->assertSee('7'); // booking_days_ahead
        $response->assertSee('24'); // late_cancellation_hours
        $response->assertSee('3'); // violation_limit
    }

    /**
     * 5. Company Manager can open settings.
     */
    public function test_company_manager_can_open_settings(): void
    {
        [$manager, $company] = $this->createCompanyManager();

        $response = $this->actingAs($manager)->get('/company/settings');

        $response->assertOk();
        $response->assertSee($company->name);
        $response->assertSee($company->code);
        $response->assertSee($company->phone);
        $response->assertSee($company->address);
    }

    /**
     * 6. Company Manager can update salon name, phone, address, and map URL.
     */
    public function test_company_manager_can_update_salon_information(): void
    {
        [$manager, $company] = $this->createCompanyManager();

        $response = $this->actingAs($manager)->put('/company/settings', [
            'name' => 'Royal Signature Salon',
            'phone' => '+90 532 999 8877',
            'address' => 'Nisantasi, Abdi Ipekci Cad. No: 12, Istanbul',
            'map_url' => 'https://maps.google.com/?q=nisantasi',
            'booking_days_ahead' => 14,
            'late_cancellation_hours' => 48,
            'violation_limit' => 5,
            'block_duration_days' => 14,
        ]);

        $response->assertRedirect(route('company.settings.edit'));
        $response->assertSessionHas('status', 'Company settings updated successfully.');

        $company->refresh();
        $this->assertEquals('Royal Signature Salon', $company->name);
        $this->assertEquals('+90 532 999 8877', $company->phone);
        $this->assertEquals('Nisantasi, Abdi Ipekci Cad. No: 12, Istanbul', $company->address);
        $this->assertEquals('https://maps.google.com/?q=nisantasi', $company->map_url);
    }

    /**
     * 7. Company Manager can update booking rules.
     */
    public function test_company_manager_can_update_booking_rules(): void
    {
        [$manager, $company] = $this->createCompanyManager();

        $response = $this->actingAs($manager)->put('/company/settings', [
            'name' => $company->name,
            'phone' => $company->phone,
            'address' => $company->address,
            'map_url' => $company->map_url,
            'booking_days_ahead' => 30,
            'late_cancellation_hours' => 12,
            'violation_limit' => 2,
            'block_duration_days' => 30,
        ]);

        $response->assertRedirect(route('company.settings.edit'));

        $company->refresh();
        $this->assertEquals(30, $company->booking_days_ahead);
        $this->assertEquals(12, $company->late_cancellation_hours);
        $this->assertEquals(2, $company->violation_limit);
        $this->assertEquals(30, $company->block_duration_days);
    }

    /**
     * 8. Invalid numeric values are rejected.
     */
    public function test_invalid_numeric_values_are_rejected(): void
    {
        [$manager, $company] = $this->createCompanyManager();

        $response = $this->actingAs($manager)->put('/company/settings', [
            'name' => $company->name,
            'phone' => $company->phone,
            'booking_days_ahead' => 0, // min 1
            'late_cancellation_hours' => 200, // max 168
            'violation_limit' => 0, // min 1
            'block_duration_days' => 400, // max 365
        ]);

        $response->assertSessionHasErrors([
            'booking_days_ahead',
            'late_cancellation_hours',
            'violation_limit',
            'block_duration_days',
        ]);
    }

    /**
     * 9. Invalid map URL is rejected.
     */
    public function test_invalid_map_url_is_rejected(): void
    {
        [$manager, $company] = $this->createCompanyManager();

        $response = $this->actingAs($manager)->put('/company/settings', [
            'name' => $company->name,
            'phone' => $company->phone,
            'map_url' => 'not-a-valid-url-address',
            'booking_days_ahead' => 7,
            'late_cancellation_hours' => 24,
            'violation_limit' => 3,
            'block_duration_days' => 7,
        ]);

        $response->assertSessionHasErrors(['map_url']);
    }

    /**
     * 10. Company public code cannot be modified through the settings request.
     */
    public function test_company_public_code_cannot_be_modified_through_settings(): void
    {
        [$manager, $company] = $this->createCompanyManager();
        $originalCode = $company->code;

        $response = $this->actingAs($manager)->put('/company/settings', [
            'code' => 'SLN-HACKED',
            'name' => 'Renamed Salon',
            'phone' => $company->phone,
            'booking_days_ahead' => 7,
            'late_cancellation_hours' => 24,
            'violation_limit' => 3,
            'block_duration_days' => 7,
        ]);

        $response->assertRedirect(route('company.settings.edit'));
        $company->refresh();

        $this->assertEquals($originalCode, $company->code);
        $this->assertEquals('Renamed Salon', $company->name);
    }

    /**
     * 11. Company status cannot be modified through the settings request.
     */
    public function test_company_status_cannot_be_modified_through_settings(): void
    {
        [$manager, $company] = $this->createCompanyManager([], ['status' => Company::STATUS_PENDING]);

        $response = $this->actingAs($manager)->put('/company/settings', [
            'status' => Company::STATUS_ACTIVE,
            'name' => $company->name,
            'phone' => $company->phone,
            'booking_days_ahead' => 7,
            'late_cancellation_hours' => 24,
            'violation_limit' => 3,
            'block_duration_days' => 7,
        ]);

        $response->assertRedirect(route('company.settings.edit'));
        $company->refresh();

        $this->assertEquals(Company::STATUS_PENDING, $company->status);
    }

    /**
     * 12. Another company's data cannot be modified.
     */
    public function test_company_manager_cannot_modify_another_company(): void
    {
        [$manager1, $company1] = $this->createCompanyManager(['email' => 'm1@test.local'], ['name' => 'Salon One']);
        [$manager2, $company2] = $this->createCompanyManager(['email' => 'm2@test.local'], ['name' => 'Salon Two']);

        $response = $this->actingAs($manager1)->put('/company/settings', [
            'company_id' => $company2->id,
            'name' => 'Maliciously Changed Salon',
            'phone' => '+90 555 000 0000',
            'booking_days_ahead' => 14,
            'late_cancellation_hours' => 48,
            'violation_limit' => 5,
            'block_duration_days' => 14,
        ]);

        $response->assertRedirect(route('company.settings.edit'));

        $company1->refresh();
        $company2->refresh();

        $this->assertEquals('Maliciously Changed Salon', $company1->name);
        $this->assertEquals('Salon Two', $company2->name); // Unchanged!
    }

    /**
     * 13. Audit log is created after successful settings update.
     */
    public function test_audit_log_is_created_after_successful_settings_update(): void
    {
        [$manager, $company] = $this->createCompanyManager();

        $this->actingAs($manager)->put('/company/settings', [
            'name' => 'New Salon Title',
            'phone' => '+90 555 888 7766',
            'booking_days_ahead' => 10,
            'late_cancellation_hours' => 12,
            'violation_limit' => 4,
            'block_duration_days' => 10,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'company_id' => $company->id,
            'action' => 'company.settings.updated',
        ]);
    }

    /**
     * 14. Suspended company cannot update company settings.
     */
    public function test_suspended_company_cannot_update_company_settings(): void
    {
        [$manager, $company] = $this->createCompanyManager([], ['status' => Company::STATUS_SUSPENDED]);

        $response = $this->actingAs($manager)->put('/company/settings', [
            'name' => 'Attempted Change',
            'phone' => '+90 555 999 0000',
            'booking_days_ahead' => 7,
            'late_cancellation_hours' => 24,
            'violation_limit' => 3,
            'block_duration_days' => 7,
        ]);

        $response->assertForbidden();
        $company->refresh();
        $this->assertNotEquals('Attempted Change', $company->name);
    }
}
