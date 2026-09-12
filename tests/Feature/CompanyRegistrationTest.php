<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_manager_can_register_and_creates_single_company(): void
    {
        $response = $this->post('/register', [
            'manager_name' => 'Can Özdemir',
            'company_name' => 'Can Barber Lounge',
            'email' => 'can@barber.local',
            'phone' => '+90 530 111 2233',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('company.dashboard'));

        $this->assertDatabaseHas('users', [
            'name' => 'Can Özdemir',
            'email' => 'can@barber.local',
            'role' => User::ROLE_COMPANY_MANAGER,
        ]);

        $this->assertDatabaseHas('companies', [
            'name' => 'Can Barber Lounge',
            'phone' => '+90 530 111 2233',
            'status' => Company::STATUS_PENDING,
        ]);

        $this->assertEquals(1, Company::where('name', 'Can Barber Lounge')->count());

        $user = User::where('email', 'can@barber.local')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->company);
        $this->assertEquals('Can Barber Lounge', $user->company->name);
        $this->assertMatchesRegularExpression('/^SLN-[A-Z0-9]{5}$/', $user->company->code);
        $this->assertEquals(Company::STATUS_PENDING, $user->company->status);
    }

    public function test_company_manager_can_access_company_dashboard(): void
    {
        $manager = User::factory()->create([
            'name' => 'Serkan Usta',
            'role' => User::ROLE_COMPANY_MANAGER,
        ]);

        Company::create([
            'code' => 'SLN-ABCDE',
            'name' => 'Serkan Hair Design',
            'manager_id' => $manager->id,
            'phone' => '+90 532 999 8877',
            'status' => Company::STATUS_PENDING,
        ]);

        $response = $this->actingAs($manager)->get('/company/dashboard');

        $response->assertOk();
        $response->assertSee('Serkan Hair Design');
        $response->assertSee('SLN-ABCDE');
        $response->assertSee('Serkan Usta');
        $response->assertSee('Pending Review');
    }

    public function test_guest_cannot_access_company_dashboard(): void
    {
        $response = $this->get('/company/dashboard');

        $response->assertRedirect(route('login'));
    }

    public function test_system_admin_cannot_access_company_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SYSTEM_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get('/company/dashboard');

        $response->assertForbidden();
    }
}
