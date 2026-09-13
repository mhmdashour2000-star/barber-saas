<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CustomerBlock;
use App\Models\CustomerViolation;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\AvailabilityService;
use App\Services\CustomerRestrictionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Phase7CHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $manager;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Hardening tests require isolated SQLite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2030-01-07 09:00', 'Europe/Istanbul'));
        $this->manager = User::factory()->create(['role' => User::ROLE_COMPANY_MANAGER]);
        $this->company = Company::create([
            'name' => 'Hardening Salon', 'code' => 'SLN-HARD', 'phone' => '+905551112233',
            'manager_id' => $this->manager->id, 'status' => Company::STATUS_ACTIVE,
            'booking_days_ahead' => 7, 'violation_limit' => 3, 'block_duration_days' => 7,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public static function portals(): array
    {
        return [['/login', User::ROLE_COMPANY_MANAGER], ['/admin/login', User::ROLE_SYSTEM_ADMIN]];
    }

    #[DataProvider('portals')]
    public function test_login_limit_and_timed_recovery(string $url, string $role): void
    {
        $user = User::factory()->create(['email' => 'throttle@example.com', 'password' => 'correct-password', 'role' => $role]);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson($url, ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->postJson($url, ['email' => strtoupper($user->email), 'password' => 'correct-password'])->assertStatus(429);
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post($url, ['email' => $user->email, 'password' => 'correct-password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, RateLimiter::attempts('login:'.hash('sha256', $user->email.'|127.0.0.1')));
    }

    #[DataProvider('portals')]
    public function test_success_clears_previous_failures(string $url, string $role): void
    {
        $user = User::factory()->create(['email' => 'clear@example.com', 'password' => 'correct-password', 'role' => $role]);
        for ($i = 0; $i < 4; $i++) {
            $this->post($url, ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post($url, ['email' => $user->email, 'password' => 'correct-password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, RateLimiter::attempts('login:'.hash('sha256', $user->email.'|127.0.0.1')));
    }

    public function test_limit_is_shared_between_portals_and_isolated_by_ip_and_identity(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'missing@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->postJson('/admin/login', ['email' => 'MISSING@example.com', 'password' => 'wrong'])->assertStatus(429);
        $this->post('/admin/login', ['email' => 'different@example.com', 'password' => 'wrong'])->assertStatus(302)->assertSessionHasErrors('email');
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.5'])->post('/login', ['email' => 'missing@example.com', 'password' => 'wrong'])
            ->assertStatus(302)->assertSessionHasErrors('email');
    }

    public function test_admin_wrong_role_uses_same_generic_error_as_unknown_identity(): void
    {
        $this->manager->update(['password' => 'correct-password']);
        $this->post('/admin/login', ['email' => $this->manager->email, 'password' => 'correct-password'])
            ->assertSessionHasErrors(['email' => 'The provided credentials do not match our records.']);
        $this->assertGuest();
    }

    private function createCredentials(): string
    {
        $this->actingAs($this->manager)->post('/company/employees', [
            'name' => 'Credential Employee', 'username' => 'credential', 'password' => 'UniqueSecret2026!',
        ])->assertRedirect();

        return session('new_employee_credentials');
    }

    public function test_credentials_are_ciphertext_and_consumed_once_without_caching(): void
    {
        $ciphertext = $this->createCredentials();
        $this->assertStringNotContainsString('UniqueSecret2026!', $ciphertext);
        $this->assertStringNotContainsString('UniqueSecret2026!', json_encode(session()->all()));
        $this->assertStringNotContainsString('UniqueSecret2026!', DB::table('audit_logs')->get()->toJson());
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('UniqueSecret2026!', $this->company->employees()->first()->password));
        $response = $this->get('/company/employees')->assertOk()->assertSee('UniqueSecret2026!');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertNull(session('new_employee_credentials'));
        $this->get('/company/employees')->assertDontSee('UniqueSecret2026!');
        $this->withSession(['new_employee_credentials' => $ciphertext])->get('/company/employees')->assertDontSee('UniqueSecret2026!');
    }

    public function test_database_session_payload_never_contains_plaintext_credentials(): void
    {
        config(['session.driver' => 'database', 'session.encrypt' => false, 'session.lottery' => [0, 100]]);
        app('session')->forgetDrivers();
        $this->createCredentials();
        $rows = DB::table('sessions')->get();
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $payload = base64_decode($row->payload);
            $this->assertStringNotContainsString('UniqueSecret2026!', $payload);
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            $this->assertIsString($decoded['new_employee_credentials']);
        }
    }

    public function test_expired_credentials_are_discarded(): void
    {
        $this->createCredentials();
        $this->travel(5)->minutes();
        $this->get('/company/employees')->assertOk()->assertDontSee('UniqueSecret2026!');
        $this->assertNull(session('new_employee_credentials'));
    }

    public function test_credential_envelope_is_bound_to_manager_and_rejects_tampering(): void
    {
        $ciphertext = $this->createCredentials();
        $other = User::factory()->create(['role' => User::ROLE_COMPANY_MANAGER]);
        Company::create(['name' => 'Other', 'code' => 'OTHER', 'phone' => '+905554445566', 'manager_id' => $other->id, 'status' => 'active']);
        $this->actingAs($other)->withSession(['new_employee_credentials' => $ciphertext])->get('/company/employees')->assertOk()->assertDontSee('UniqueSecret2026!');
        $this->actingAs($this->manager)->withSession(['new_employee_credentials' => 'tampered'])->get('/company/employees')->assertOk()->assertDontSee('UniqueSecret2026!');
    }

    private function customer()
    {
        return $this->company->customers()->create(['name' => 'Client', 'phone' => '+905559998877', 'active' => true]);
    }

    public function test_repeated_manual_block_reuses_existing_without_changing_history(): void
    {
        $customer = $this->customer();
        $service = app(CustomerRestrictionService::class);
        $first = $service->manualBlock($customer, 'Original', 7);
        $second = $service->manualBlock($customer, 'Repeated', null);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('Original', $first->fresh()->reason);
        $this->assertSame(1, $customer->blocks()->count());
    }

    public function test_manual_block_reuses_automatic_and_threshold_cannot_duplicate_it(): void
    {
        $customer = $this->customer();
        $service = app(CustomerRestrictionService::class);
        for ($i = 0; $i < 3; $i++) {
            $service->recordViolation($customer, CustomerViolation::TYPE_NO_SHOW);
        }
        $block = $customer->activeBlock();
        $this->assertSame(CustomerBlock::SOURCE_AUTOMATIC, $block->source);
        $this->assertSame($block->id, $service->manualBlock($customer, 'Manual')->id);
        $this->assertSame($block->id, $service->createAutomaticBlock($customer, 999)->id);
        $this->assertSame(1, $customer->blocks()->count());
        $this->assertSame(0, $service->getActiveViolationCount($customer));
    }

    public function test_unblock_lifts_all_active_rows_and_preserves_expired_history(): void
    {
        $customer = $this->customer();
        foreach ([null, now()->addDays(2), now()->subDay()] as $ends) {
            $this->company->customerBlocks()->create(['customer_id' => $customer->id, 'source' => 'manual', 'reason' => 'Legacy', 'starts_at' => now()->subDays(2), 'ends_at' => $ends]);
        }
        $service = app(CustomerRestrictionService::class);
        $this->assertTrue($service->manualUnblock($customer, $this->manager->id));
        $this->assertFalse($customer->fresh()->isBlocked());
        $this->assertFalse($service->manualUnblock($customer));
        $this->assertSame(3, $customer->blocks()->count());
        $this->assertSame(2, $customer->blocks()->whereNotNull('lifted_at')->count());
        $this->assertNull($customer->blocks()->where('ends_at', '<', now())->first()->lifted_at);
    }

    public function test_new_cycle_works_even_in_the_same_second_after_unblock(): void
    {
        $customer = $this->customer();
        $service = app(CustomerRestrictionService::class);
        for ($i = 0; $i < 3; $i++) {
            $service->recordViolation($customer, CustomerViolation::TYPE_NO_SHOW);
        }
        $service->manualUnblock($customer);
        $service->recordViolation($customer, CustomerViolation::TYPE_NO_SHOW);
        $this->assertSame(1, $service->getActiveViolationCount($customer));
        $this->assertFalse($customer->isBlocked());
        for ($i = 0; $i < 2; $i++) {
            $service->recordViolation($customer, CustomerViolation::TYPE_NO_SHOW);
        }
        $this->assertSame(2, $customer->blocks()->count());
        $this->assertTrue($customer->isBlocked());
    }

    private function schedulingModels(): array
    {
        $service = $this->company->services()->create(['name' => 'Haircut', 'duration_minutes' => 30, 'price_minor_units' => 10000, 'active' => true]);
        $employee = $this->company->employees()->create(['name' => 'Stylist', 'username' => 'stylist', 'password' => 'test-password', 'active' => true]);
        $service->employees()->attach($employee);
        foreach ([$service, $employee] as $model) {
            for ($day = 1; $day <= 7; $day++) {
                $model->weeklyAvailabilities()->create(['day_of_week' => $day, 'start_time' => '00:00', 'end_time' => '23:59']);
            }
        }

        return [$service, $employee];
    }

    public static function targets(): array
    {
        return [['company'], ['service'], ['employee']];
    }

    #[DataProvider('targets')]
    public function test_duplicate_exception_request_is_rejected_for_each_target(string $type): void
    {
        [$service, $employee] = $this->schedulingModels();
        $data = ['type' => $type, 'date' => '2030-01-07', 'is_closed' => true];
        if ($type !== 'company') {
            $data[$type.'_id'] = $type === 'service' ? $service->id : $employee->id;
        }
        $url = route('company.availability.exceptions.store');
        $this->actingAs($this->manager)->post($url, $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->postJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->assertSame(1, $this->company->availabilityExceptions()->count());
    }

    #[DataProvider('targets')]
    public function test_database_enforces_target_date_uniqueness(string $type): void
    {
        [$service, $employee] = $this->schedulingModels();
        $data = ['type' => $type, 'date' => '2030-01-07', 'is_closed' => true,
            'service_id' => $type === 'service' ? $service->id : null,
            'employee_id' => $type === 'employee' ? $employee->id : null];
        $this->company->availabilityExceptions()->create($data);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->company->availabilityExceptions()->create($data);
    }

    public function test_company_closure_wins_and_custom_windows_intersect_deterministically(): void
    {
        [$service, $employee] = $this->schedulingModels();
        $companyException = $this->company->availabilityExceptions()->create(['type' => 'company', 'date' => '2030-01-07', 'is_closed' => true]);
        $exception = $this->company->availabilityExceptions()->create(['type' => 'service', 'service_id' => $service->id, 'date' => '2030-01-07', 'is_closed' => false]);
        $exception->windows()->create(['start_time' => '14:00', 'end_time' => '17:00']);
        $exception->windows()->create(['start_time' => '08:00', 'end_time' => '12:00']);
        $availability = app(AvailabilityService::class);
        $date = Carbon::now('Europe/Istanbul');
        $this->assertSame([], $availability->getCombinedServiceEmployeeWindows($service, $employee, $date));
        $companyException->update(['is_closed' => false]);
        $companyException->windows()->create(['start_time' => '09:00', 'end_time' => '15:00']);
        $expected = [['start' => '09:00', 'end' => '12:00'], ['start' => '14:00', 'end' => '15:00']];
        $this->assertSame($expected, $availability->getCombinedServiceEmployeeWindows($service, $employee, $date));
        $this->assertSame($expected, $availability->getCombinedServiceEmployeeWindows($service, $employee, $date));
    }

    public function test_exception_validation_uses_istanbul_today_at_utc_day_boundary(): void
    {
        Carbon::setTestNow(Carbon::parse('2030-01-07 22:30', 'UTC'));
        $this->actingAs($this->manager)->postJson(route('company.availability.exceptions.store'), [
            'type' => 'company', 'date' => '2030-01-07', 'is_closed' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('date');
    }

    public static function timezoneDates(): array
    {
        // Istanbul historically observed DST. Use both offsets to catch hardcoded +3 arithmetic.
        return [['2015-01-07', '10:00:00'], ['2015-07-07', '09:00:00'], ['2030-01-07', '09:00:00']];
    }

    #[DataProvider('timezoneDates')]
    public function test_booking_reschedule_and_ui_convert_only_at_boundaries(string $date, string $utcTime): void
    {
        Carbon::setTestNow(Carbon::parse($date.' 09:00', 'Europe/Istanbul'));
        [$service, $employee] = $this->schedulingModels();
        $appointment = app(AppointmentService::class)->create([
            'customer_id' => $this->customer()->id, 'service_id' => $service->id, 'employee_id' => $employee->id,
            'starts_at' => Carbon::parse($date.' 12:00', 'Europe/Istanbul'),
        ], $this->company);
        $this->assertSame($date.' '.$utcTime, $appointment->fresh()->getRawOriginal('starts_at'));
        $this->actingAs($this->manager)->get(route('company.appointments.show', $appointment))->assertOk()->assertSee('12:00')->assertSee($date.'T12:00');
        $this->patch(route('company.appointments.reschedule', $appointment), ['starts_at' => $date.'T13:00'])->assertRedirect()->assertSessionHasNoErrors();
        $expected = Carbon::parse($date.' 13:00', 'Europe/Istanbul')->utc()->format('Y-m-d H:i:s');
        $this->assertSame($expected, $appointment->fresh()->getRawOriginal('starts_at'));
        $this->get(route('company.appointments.index'))->assertOk()->assertSee('13:00');
    }

    public function test_repeated_appointment_violation_is_idempotent(): void
    {
        [$service, $employee] = $this->schedulingModels();
        $customer = $this->customer();
        $appointment = app(AppointmentService::class)->create([
            'customer_id' => $customer->id, 'service_id' => $service->id, 'employee_id' => $employee->id,
            'starts_at' => Carbon::now('Europe/Istanbul')->addHours(2),
        ], $this->company);
        $restrictions = app(CustomerRestrictionService::class);
        $first = $restrictions->recordViolation($customer, CustomerViolation::TYPE_NO_SHOW, null, $appointment->id);
        $second = $restrictions->recordViolation($customer, CustomerViolation::TYPE_NO_SHOW, null, $appointment->id);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $customer->violations()->count());
    }

    public function test_blocked_customer_cannot_be_rescheduled_and_original_booking_is_preserved(): void
    {
        [$service, $employee] = $this->schedulingModels();
        $customer = $this->customer();
        $appointment = app(AppointmentService::class)->create([
            'customer_id' => $customer->id, 'service_id' => $service->id, 'employee_id' => $employee->id,
            'starts_at' => Carbon::now('Europe/Istanbul')->addHours(2),
        ], $this->company);
        $before = $appointment->getRawOriginal('starts_at');
        app(CustomerRestrictionService::class)->manualBlock($customer, 'Blocked');
        $this->actingAs($this->manager)->patchJson(route('company.appointments.reschedule', $appointment), ['starts_at' => '2030-01-07T14:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('starts_at');
        $this->assertSame($before, $appointment->fresh()->getRawOriginal('starts_at'));
        $this->assertSame(1, $appointment->events()->count());
    }

    public function test_restriction_mutation_holds_customer_lock_before_inserting_violation(): void
    {
        $customer = $this->customer();
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = [$query->sql, DB::transactionLevel()];
        });
        app(CustomerRestrictionService::class)->recordViolation($customer, CustomerViolation::TYPE_NO_SHOW);
        $customerRead = collect($queries)->search(fn ($entry) => str_contains($entry[0], 'from "customers"'));
        $insert = collect($queries)->search(fn ($entry) => str_contains($entry[0], 'insert into "customer_violations"'));
        $this->assertNotFalse($customerRead);
        $this->assertNotFalse($insert);
        $this->assertLessThan($insert, $customerRead);
        $this->assertGreaterThan(0, $queries[$insert][1]);
        // SQLite omits FOR UPDATE. This verifies transaction/order, not MySQL lock semantics.
    }
}
