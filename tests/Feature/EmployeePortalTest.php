<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeePortalTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Company $other;
    private Employee $employee;
    private Employee $colleague;
    private Employee $foreign;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Employee tests require isolated SQLite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2030-01-08 12:00', 'Europe/Istanbul'));
        $this->company = $this->company('ALPHA');
        $this->other = $this->company('BETA');
        $this->employee = $this->employee($this->company, 'ahmad');
        $this->colleague = $this->employee($this->company, 'colleague');
        $this->foreign = $this->employee($this->other, 'ahmad');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function company(string $name): Company
    {
        return Company::create(['code' => 'SLN-'.$name, 'name' => $name.' Salon', 'phone' => '05551112233',
            'status' => Company::STATUS_ACTIVE, 'manager_id' => User::factory()->create(['role' => User::ROLE_COMPANY_MANAGER])->id]);
    }

    private function employee(Company $company, string $username): Employee
    {
        return $company->employees()->create(['name' => $company->name.' '.$username, 'username' => $username,
            'password' => 'Secret123!', 'active' => true, 'must_change_password' => false]);
    }

    private function credentials(array $overrides = []): array
    {
        return array_replace(['company_code' => 'SLN-ALPHA', 'username' => 'ahmad', 'password' => 'Secret123!'], $overrides);
    }

    private function login(): void
    {
        $this->post('/employee/login', $this->credentials())->assertRedirect(route('employee.dashboard'));
    }

    private function appointment(Employee $employee, string $local = '2030-01-08 10:00', string $name = 'My Client', AppointmentStatus $status = AppointmentStatus::CONFIRMED): Appointment
    {
        $company = $employee->company;
        $customer = $company->customers()->create(['name' => $name, 'phone' => '0555'.random_int(1000000, 9999999), 'active' => true]);
        $service = $company->services()->create(['name' => 'Haircut', 'duration_minutes' => 30, 'price_minor_units' => 15000, 'active' => true]);
        return $company->appointments()->create(['customer_id' => $customer->id, 'service_id' => $service->id,
            'employee_id' => $employee->id, 'booking_code' => 'AP-'.strtoupper(bin2hex(random_bytes(4))),
            'starts_at' => Carbon::parse($local, 'Europe/Istanbul')->utc(),
            'ends_at' => Carbon::parse($local, 'Europe/Istanbul')->addMinutes(30)->utc(), 'status' => $status,
            'customer_name_snapshot' => $name, 'customer_phone_snapshot' => $customer->phone,
            'service_name_snapshot' => 'Haircut', 'service_price_minor_units_snapshot' => 15000,
            'service_duration_minutes_snapshot' => 30, 'employee_name_snapshot' => $employee->name]);
    }

    public function test_public_login_has_no_email_and_valid_login_is_company_scoped_and_rotates_session(): void
    {
        $this->get('/employee/login')->assertOk()->assertSee('Employee login')->assertSee('Company Code')
            ->assertSee('Username')->assertDontSee('name="email"', false);
        $oldId = session()->getId();
        $this->post('/employee/login', $this->credentials(['company_code' => ' sln-alpha ', 'company_id' => $this->other->id]))
            ->assertRedirect(route('employee.dashboard'));
        $this->assertAuthenticatedAs($this->employee, 'employee');
        $this->assertGuest('web');
        $this->assertNotSame($oldId, session()->getId());
        $this->assertStringNotContainsString('Secret123!', serialize(session()->all()));
        $this->post('/employee/logout');
        $this->post('/employee/login', $this->credentials(['company_code' => 'SLN-BETA']))->assertRedirect(route('employee.dashboard'));
        $this->assertAuthenticatedAs($this->foreign, 'employee');
    }

    #[DataProvider('badCredentials')]
    public function test_invalid_credentials_are_generic_and_not_flashed(array $overrides): void
    {
        $this->from('/employee/login')->post('/employee/login', $this->credentials($overrides))
            ->assertSessionHasErrors(['company_code' => 'The provided credentials are invalid.']);
        $this->assertGuest('employee');
        $this->assertNull(session()->getOldInput('password'));
    }

    public static function badCredentials(): array
    {
        return [[['company_code' => 'SLN-MISSING']], [['username' => 'missing']], [['password' => 'wrong']]];
    }

    public function test_inactive_employee_and_non_active_companies_are_rejected(): void
    {
        $this->employee->update(['active' => false]);
        $this->post('/employee/login', $this->credentials())->assertSessionHasErrors('company_code');
        $this->employee->update(['active' => true]);
        foreach ([Company::STATUS_PENDING, Company::STATUS_SUSPENDED] as $status) {
            $this->company->update(['status' => $status]);
            $this->post('/employee/login', $this->credentials())->assertSessionHasErrors('company_code');
            $this->assertGuest('employee');
        }
    }

    public function test_manager_and_admin_credentials_cannot_authenticate_as_employee(): void
    {
        foreach ([User::ROLE_COMPANY_MANAGER, User::ROLE_SYSTEM_ADMIN] as $role) {
            $user = User::factory()->create(['role' => $role, 'password' => 'UserPassword123']);
            $this->post('/employee/login', $this->credentials(['username' => $user->email, 'password' => 'UserPassword123']))
                ->assertSessionHasErrors('company_code');
        }
        $this->assertGuest('employee');
        $this->actingAs($this->company->manager, 'web');
        $this->login();
        $this->assertGuest('web');
    }

    public function test_rate_limit_normalizes_identity_and_recovers_after_expiry(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/employee/login', $this->credentials(['password' => 'wrong']))->assertRedirect();
        }
        $this->postJson('/employee/login', $this->credentials(['company_code' => ' sln-alpha ', 'username' => ' AHMAD ']))->assertStatus(429);
        $this->travel(61)->seconds();
        $this->login();
    }

    public function test_success_clears_failed_attempts_and_other_company_has_separate_limit(): void
    {
        for ($i = 0; $i < 4; $i++) $this->post('/employee/login', $this->credentials(['password' => 'wrong']));
        $this->login();
        $this->post('/employee/logout');
        for ($i = 0; $i < 5; $i++) $this->post('/employee/login', $this->credentials(['password' => 'wrong']));
        $this->postJson('/employee/login', $this->credentials())->assertStatus(429);
        $this->post('/employee/login', $this->credentials(['company_code' => 'SLN-BETA']))->assertRedirect(route('employee.dashboard'));
    }

    public function test_logout_invalidates_session_and_guests_cannot_access_operations(): void
    {
        $this->get('/employee')->assertRedirect(route('employee.login'));
        $this->post('/employee/appointments/AP-FAKE/complete')->assertRedirect(route('employee.login'));
        $this->login();
        session()->put('private_marker', 'value');
        $token = session()->token();
        $this->post('/employee/logout')->assertRedirect(route('employee.login'));
        $this->assertGuest('employee');
        $this->assertFalse(session()->has('private_marker'));
        $this->assertNotSame($token, session()->token());
        $this->get('/employee')->assertRedirect(route('employee.login'));
    }

    public function test_temporary_password_requires_change_and_does_not_leak_on_validation(): void
    {
        $this->employee->update(['must_change_password' => true]);
        $this->post('/employee/login', $this->credentials())->assertRedirect(route('employee.password'));
        $this->get('/employee')->assertRedirect(route('employee.password'));
        $this->post('/employee/appointments/AP-FAKE/complete')->assertRedirect(route('employee.password'));
        $this->get('/employee/password')->assertOk()->assertDontSee('Secret123!');
        $this->put('/employee/password', ['current_password' => 'wrong', 'password' => 'OwnPassword456', 'password_confirmation' => 'OwnPassword456'])
            ->assertSessionHasErrors('current_password');
        $this->assertNull(session()->getOldInput('current_password'));
        $this->assertNull(session()->getOldInput('password'));
        $this->put('/employee/password', ['current_password' => 'Secret123!', 'password' => 'OwnPassword456', 'password_confirmation' => 'OwnPassword456'])
            ->assertRedirect(route('employee.dashboard'));
        $this->assertTrue(Hash::check('OwnPassword456', $this->employee->fresh()->password));
        $this->assertFalse($this->employee->fresh()->must_change_password);
        $this->get('/employee')->assertOk();
    }

    #[DataProvider('revocations')]
    public function test_existing_session_loses_access_after_account_change(string $change): void
    {
        $appointment = $this->appointment($this->employee);
        $this->login();
        match ($change) {
            'password' => $this->employee->update(['password' => 'ResetPassword987']),
            'employee' => $this->employee->update(['active' => false]),
            'company' => $this->company->update(['status' => Company::STATUS_SUSPENDED]),
        };
        $this->post(route('employee.appointments.complete', $appointment->booking_code))->assertRedirect(route('employee.login'));
        $this->assertSame(AppointmentStatus::CONFIRMED, $appointment->fresh()->status);
    }

    public static function revocations(): array { return [['password'], ['employee'], ['company']]; }

    public function test_dashboard_is_tenant_scoped_local_today_and_chronologically_sorted(): void
    {
        $late = $this->appointment($this->employee, '2030-01-08 11:00', 'Later Client');
        $early = $this->appointment($this->employee, '2030-01-08 00:00', 'Early Client');
        $this->appointment($this->employee, '2030-01-07 23:59', 'Yesterday Client');
        $this->appointment($this->employee, '2030-01-09 00:00', 'Tomorrow Client');
        $this->appointment($this->colleague, name: 'Colleague Client');
        $this->appointment($this->foreign, name: 'Foreign Client');
        $this->login();
        $response = $this->get('/employee?employee_id='.$this->foreign->id.'&company_id='.$this->other->id)->assertOk()
            ->assertSeeInOrder(['Early Client', 'Later Client'])->assertSee('00:00')->assertSee('11:00')
            ->assertSee('Tuesday 8 January 2030')->assertSee('Europe/Istanbul')
            ->assertDontSee('Yesterday Client')->assertDontSee('Tomorrow Client')->assertDontSee('Colleague Client')->assertDontSee('Foreign Client');
        $this->assertCount(2, $response->viewData('appointments'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('2030-01-07 21:00', $early->starts_at->format('Y-m-d H:i'));
    }

    public function test_empty_dashboard_and_future_and_terminal_action_visibility(): void
    {
        $this->login();
        $this->get('/employee')->assertSee('No appointments assigned to you today.');
        $future = $this->appointment($this->employee, '2030-01-08 13:00');
        $completed = $this->appointment($this->employee, status: AppointmentStatus::COMPLETED);
        $this->get('/employee')->assertSee('Actions become available')->assertSee('Completed')
            ->assertDontSee(route('employee.appointments.complete', $future->booking_code), false)
            ->assertDontSee(route('employee.appointments.no-show', $completed->booking_code), false);
    }

    public function test_employee_cannot_access_manager_or_admin_routes_and_managers_are_not_employees(): void
    {
        $this->actingAs($this->company->manager, 'web')->get('/employee')->assertRedirect(route('employee.login'));
        $this->login();
        foreach (['/company/dashboard', '/company/settings', '/company/employees', '/company/services', '/company/appointments'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));
        $this->post('/company/employees', [])->assertRedirect(route('login'));
        $this->patch('/admin/companies/'.$this->other->id.'/status', [])->assertRedirect(route('admin.login'));
        $this->put('/company/employees/'.$this->employee->id.'/password', ['password' => 'InjectedPassword'])->assertRedirect(route('login'));
    }

    #[DataProvider('actions')]
    public function test_employee_can_perform_supported_action_and_invalid_repeat_is_rejected(string $action): void
    {
        $appointment = $this->appointment($this->employee);
        $this->login();
        $this->post(route('employee.appointments.'.$action, $appointment->booking_code), ['company_id' => $this->other->id])->assertRedirect(route('employee.dashboard'));
        $this->assertSame($action === 'complete' ? AppointmentStatus::COMPLETED : AppointmentStatus::NO_SHOW, $appointment->fresh()->status);
        $this->assertDatabaseHas('appointment_events', ['appointment_id' => $appointment->id, 'actor_type' => 'employee', 'actor_id' => $this->employee->id]);
        $this->assertDatabaseCount('customer_violations', $action === 'no-show' ? 1 : 0);
        $this->postJson(route('employee.appointments.'.$action, $appointment->booking_code))->assertUnprocessable();
        $otherAction = $action === 'complete' ? 'no-show' : 'complete';
        $this->postJson(route('employee.appointments.'.$otherAction, $appointment->booking_code))->assertUnprocessable();
        $this->assertSame(1, $appointment->events()->count());
    }

    public static function actions(): array { return [['complete'], ['no-show']]; }

    #[DataProvider('actions')]
    public function test_cross_employee_cross_tenant_and_internal_ids_are_rejected(string $action): void
    {
        $colleague = $this->appointment($this->colleague);
        $foreign = $this->appointment($this->foreign);
        $own = $this->appointment($this->employee);
        $this->login();
        foreach ([$colleague->booking_code, $foreign->booking_code, (string) $own->id, 'AP-MISSING'] as $code) {
            $this->postJson(route('employee.appointments.'.$action, $code))->assertNotFound();
        }
        $this->assertDatabaseCount('appointment_events', 0);
        $this->assertDatabaseCount('customer_violations', 0);
    }

    #[DataProvider('outsideTiming')]
    public function test_operational_timing_is_enforced(string $local): void
    {
        $appointment = $this->appointment($this->employee, $local);
        $this->login();
        foreach (['complete', 'no-show'] as $action) {
            $this->postJson(route('employee.appointments.'.$action, $appointment->booking_code))->assertUnprocessable();
        }
        $this->assertSame(AppointmentStatus::CONFIRMED, $appointment->fresh()->status);
    }

    public static function outsideTiming(): array
    {
        return [['2030-01-07 23:59'], ['2030-01-09 00:00'], ['2030-01-08 12:01']];
    }

    public function test_cancelled_appointments_cannot_be_completed(): void
    {
        $appointment = $this->appointment($this->employee, status: AppointmentStatus::CANCELLED_BY_COMPANY);
        $this->login();
        $this->postJson(route('employee.appointments.complete', $appointment->booking_code))->assertUnprocessable();
    }

    public function test_routes_have_csrf_and_no_create_cancel_reschedule_or_delete(): void
    {
        $this->get('/employee/login')->assertOk(); // Boot the HTTP middleware groups.
        $router = app('router');
        foreach (['employee.login', 'employee.logout', 'employee.password.update', 'employee.appointments.complete', 'employee.appointments.no-show', 'company.employees.password'] as $name) {
            $this->assertContains(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
                $router->gatherRouteMiddleware(Route::getRoutes()->getByName($name)));
        }
        foreach (['employee.appointments.create', 'employee.appointments.store', 'employee.appointments.reschedule', 'employee.appointments.cancel', 'employee.appointments.destroy'] as $name) {
            $this->assertFalse(Route::has($name));
        }
    }

    public function test_manager_password_reset_is_hashed_scoped_and_displayed_once_without_plaintext_storage(): void
    {
        $this->actingAs($this->company->manager, 'web')->put(route('company.employees.password', $this->employee), [
            'password' => 'NewTemporary123', 'password_confirmation' => 'NewTemporary123', 'company_id' => $this->other->id,
        ])->assertRedirect(route('company.employees.index'));
        $this->assertTrue(Hash::check('NewTemporary123', $this->employee->fresh()->password));
        $this->assertTrue($this->employee->fresh()->must_change_password);
        $this->assertStringNotContainsString('NewTemporary123', serialize(session()->all()));
        $this->assertStringNotContainsString('NewTemporary123', AuditLog::all()->toJson());
        $this->assertStringNotContainsString('NewTemporary123', $this->employee->fresh()->toJson());
        $this->get('/company/employees')->assertSee('NewTemporary123');
        $this->get('/company/employees')->assertDontSee('NewTemporary123');
        $this->put(route('company.employees.password', $this->foreign), ['password' => 'HackedPass123', 'password_confirmation' => 'HackedPass123'])->assertNotFound();
        $this->assertTrue(Hash::check('Secret123!', $this->foreign->fresh()->password));
        $this->company->update(['status' => Company::STATUS_SUSPENDED]);
        $this->put(route('company.employees.password', $this->employee), ['password' => 'HackedPass123', 'password_confirmation' => 'HackedPass123'])->assertForbidden();
    }

    public function test_manager_updates_username_with_company_uniqueness_and_can_restore_missing_credentials(): void
    {
        $this->employee->update(['password' => '']);
        $this->actingAs($this->company->manager, 'web');
        $this->put(route('company.employees.update', $this->employee), ['name' => 'Ahmad', 'username' => 'colleague'])->assertSessionHasErrors('username');
        $this->put(route('company.employees.update', $this->employee), ['name' => 'Ahmad', 'username' => 'newname', 'company_id' => $this->other->id])->assertRedirect();
        $this->assertSame($this->company->id, $this->employee->fresh()->company_id);
        $this->put(route('company.employees.password', $this->employee), ['password' => 'RestoredPass123', 'password_confirmation' => 'RestoredPass123'])->assertRedirect();
        Auth::guard('web')->logout();
        $this->post('/employee/login', $this->credentials(['username' => 'newname', 'password' => 'RestoredPass123']))->assertRedirect(route('employee.password'));
    }
}
