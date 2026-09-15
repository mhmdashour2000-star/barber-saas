<?php

namespace Tests\Feature;

use App\Models\{Company, User, AuditLog};
use App\Notifications\ManagerResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth, DB, Hash, Notification, Password};
use Tests\TestCase;

class ManagerAccountTest extends TestCase
{
    use RefreshDatabase;
    private User $manager;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') throw new \RuntimeException('Isolated SQLite required.');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->manager = User::factory()->create(['role' => User::ROLE_COMPANY_MANAGER, 'email' => 'manager@example.test', 'password' => 'OriginalPass123']);
        Company::create(['manager_id' => $this->manager->id, 'name' => 'Salon', 'code' => 'SLN-TEST', 'phone' => '05551112233', 'status' => 'active']);
    }

    public function test_password_change_revokes_other_sessions_and_tokens_without_leaking_passwords(): void
    {
        $token = Password::broker('managers')->createToken($this->manager);
        $this->actingAs($this->manager)->get('/company/settings')->assertOk()->assertSee(route('company.password.edit'));
        $oldHash = session('password_hash_web');
        $oldSession = session()->getId();
        $this->put('/company/password', ['current_password' => 'OriginalPass123', 'password' => 'NewManagerPass123', 'password_confirmation' => 'NewManagerPass123'])->assertRedirect(route('company.settings.edit'));
        $this->assertTrue(Hash::check('NewManagerPass123', $this->manager->fresh()->password));
        $this->assertNotSame($oldSession, session()->getId());
        $this->get('/company/dashboard')->assertOk();
        $this->assertFalse(Password::broker('managers')->tokenExists($this->manager->fresh(), $token));
        $this->assertStringNotContainsString('NewManagerPass123', serialize(session()->all()).AuditLog::all()->toJson());
        $this->actingAs($this->manager->fresh())->withSession(['password_hash_web' => $oldHash])->get('/company/dashboard')->assertRedirect(route('login'));
    }

    public function test_current_password_and_confirmation_are_required(): void
    {
        $this->actingAs($this->manager)->get('/company/password')->assertOk();
        $this->put('/company/password', ['current_password' => 'WrongPass123', 'password' => 'NewManagerPass123', 'password_confirmation' => 'NewManagerPass123'])->assertSessionHasErrors('current_password');
        $this->assertNull(session()->getOldInput('current_password'));
        $this->assertNull(session()->getOldInput('password'));
        $this->put('/company/password', ['current_password' => 'OriginalPass123', 'password' => 'NewManagerPass123', 'password_confirmation' => 'MismatchPass'])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('OriginalPass123', $this->manager->fresh()->password));
    }

    public function test_password_change_does_not_authenticate_employee_or_admin(): void
    {
        $employee = $this->manager->company->employees()->create(['name' => 'Staff', 'username' => 'staff', 'password' => 'StaffPass123']);
        Auth::guard('employee')->login($employee);
        $this->get('/company/password')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => User::ROLE_SYSTEM_ADMIN]))->put('/company/password', [])->assertForbidden();
        $this->assertTrue(Hash::check('StaffPass123', $employee->fresh()->password));
    }

    public function test_recovery_is_generic_and_only_sends_to_managers_with_hashed_token(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SYSTEM_ADMIN]);
        $employee = $this->manager->company->employees()->create(['name' => 'Staff', 'username' => 'staff', 'password' => 'StaffPass123']);
        $messages = [];
        foreach ([$this->manager->email, 'unknown@example.test', $admin->email, 'staff@example.test'] as $email) {
            $this->from('/forgot-password')->post('/forgot-password', ['email' => $email])->assertRedirect('/forgot-password');
            $messages[] = session('status');
        }
        $this->assertCount(1, array_unique($messages));
        Notification::assertSentTo($this->manager, ManagerResetPassword::class, function ($notification) {
            $this->assertStringStartsWith(rtrim(config('app.url'), '/').'/reset-password/', $notification->toMail($this->manager)->actionUrl);
            $stored = DB::table('password_reset_tokens')->where('email', $this->manager->email)->value('token');
            $this->assertNotSame($notification->token, $stored);
            $this->assertTrue(Hash::check($notification->token, $stored));
            return true;
        });
        Notification::assertNotSentTo($admin, ManagerResetPassword::class);
        $this->assertTrue(Hash::check('StaffPass123', $employee->fresh()->password));
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_recovery_token_is_single_use_and_reset_revokes_old_sessions(): void
    {
        $token = Password::broker('managers')->createToken($this->manager);
        $oldHash = $this->manager->password;
        $payload = ['token' => $token, 'email' => $this->manager->email, 'password' => 'RecoveredPass123', 'password_confirmation' => 'RecoveredPass123'];
        $this->get(route('manager.password.reset', ['token' => $token, 'email' => $this->manager->email]))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->post('/reset-password', $payload)->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('RecoveredPass123', $this->manager->fresh()->password));
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
        $this->assertNull(session()->getOldInput('token'));
        $this->actingAs($this->manager->fresh())->withSession(['password_hash_web' => $oldHash])->get('/company/dashboard')->assertRedirect(route('login'));
    }

    public function test_expired_tokens_and_admin_tokens_do_not_reset_and_secrets_are_not_flashed(): void
    {
        $token = Password::broker('managers')->createToken($this->manager);
        DB::table('password_reset_tokens')->update(['created_at' => now()->subMinutes(61)]);
        $this->post('/reset-password', ['token' => $token, 'email' => $this->manager->email, 'password' => 'ChangedPass123', 'password_confirmation' => 'ChangedPass123'])->assertSessionHasErrors('email');
        $admin = User::factory()->create(['role' => User::ROLE_SYSTEM_ADMIN, 'password' => 'AdminPass123']);
        $token = Password::broker('managers')->createToken($admin);
        $this->post('/reset-password', ['token' => $token, 'email' => $admin->email, 'password' => 'ChangedPass123', 'password_confirmation' => 'ChangedPass123'])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('AdminPass123', $admin->fresh()->password));
        $this->post('/reset-password', ['token' => $token, 'email' => $this->manager->email, 'password' => 'short'])->assertSessionHasErrors('password');
        $this->assertNull(session()->getOldInput('token'));
        $this->assertTrue(Hash::check('OriginalPass123', $this->manager->fresh()->password));
    }

    public function test_recovery_throttles_unknown_identities_and_recovers(): void
    {
        for ($i = 0; $i < 5; $i++) $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertRedirect();
        $this->post('/forgot-password', ['email' => ' UNKNOWN@example.test '])->assertStatus(429);
        $this->travel(61)->seconds();
        $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertRedirect();
    }

    public function test_registration_throttles_identity_and_ip_without_creating_data(): void
    {
        for ($i = 0; $i < 5; $i++) $this->post('/register', ['email' => 'signup@example.test'])->assertSessionHasErrors('manager_name');
        $this->post('/register', ['email' => ' SIGNUP@example.test '])->assertStatus(429);
        for ($i = 0; $i < 5; $i++) $this->post('/register', ['email' => 'person'.$i.'@example.test'])->assertSessionHasErrors('manager_name');
        $this->post('/register', ['email' => 'fresh@example.test'])->assertStatus(429);
        $this->assertDatabaseCount('companies', 1);
        $this->travel(3601)->seconds();
        $this->post('/register', ['email' => 'fresh@example.test'])->assertSessionHasErrors('manager_name');
    }

    public function test_malformed_identity_reaches_validation_without_server_error(): void
    {
        $this->post('/register', ['email' => ['invalid']])->assertSessionHasErrors('email');
        $this->post('/forgot-password', ['email' => ['invalid']])->assertSessionHasErrors('email');
    }

    public function test_recovery_revokes_legacy_database_sessions_only_for_this_manager(): void
    {
        config(['session.driver' => 'database']);
        $other = User::factory()->create(['role' => User::ROLE_SYSTEM_ADMIN]);
        foreach (['legacy-manager' => $this->manager->id, 'other-user' => $other->id] as $id => $userId) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'payload' => base64_encode(serialize([])), 'last_activity' => now()->timestamp]);
        }
        $token = Password::broker('managers')->createToken($this->manager);
        $this->post('/reset-password', ['token' => $token, 'email' => $this->manager->email,
            'password' => 'RecoveredPass123', 'password_confirmation' => 'RecoveredPass123'])->assertRedirect(route('login'));
        $this->assertDatabaseMissing('sessions', ['id' => 'legacy-manager']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-user']);
    }
}
