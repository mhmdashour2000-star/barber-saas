<?php

namespace Tests\Feature;

use App\Enums\AppointmentEventType;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\AvailabilityService;
use App\Services\CustomerRestrictionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AppointmentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Company $other;
    protected Customer $customer;
    protected Service $service;
    protected Employee $employee;
    protected Employee $second;
    protected AppointmentService $appointments;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Appointment tests must use isolated SQLite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2030-01-07 09:00:00', 'Europe/Istanbul'));
        $this->company = $this->makeCompany('A');
        $this->other = $this->makeCompany('B');
        $this->customer = $this->company->customers()->create(['name' => 'Client', 'phone' => '05551112233', 'active' => true]);
        $this->service = $this->company->services()->create([
            'name' => 'Haircut', 'price_minor_units' => 25000, 'duration_minutes' => 45, 'active' => true,
        ]);
        $this->employee = $this->makeEmployee($this->company, 'First');
        $this->second = $this->makeEmployee($this->company, 'Second');
        // Reverse pivot insertion order deliberately: selection must use employee IDs.
        $this->service->employees()->attach([$this->second->id, $this->employee->id]);
        foreach ([$this->service, $this->employee, $this->second] as $model) {
            for ($day = 1; $day <= 7; $day++) {
                $model->weeklyAvailabilities()->create(['day_of_week' => $day, 'start_time' => '09:00', 'end_time' => '18:00']);
            }
        }
        $this->appointments = app(AppointmentService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeCompany(string $name): Company
    {
        return Company::create([
            'name' => $name, 'code' => 'SLN-'.$name,
            'manager_id' => User::factory()->create(['role' => User::ROLE_COMPANY_MANAGER])->id,
            'phone' => '+905551112233', 'status' => Company::STATUS_ACTIVE,
            'booking_days_ahead' => 7, 'late_cancellation_hours' => 24,
            'violation_limit' => 3, 'block_duration_days' => 7,
        ]);
    }

    protected function makeEmployee(Company $company, string $name): Employee
    {
        return $company->employees()->create(['name' => $name, 'username' => $name, 'password' => 'test-password', 'active' => true]);
    }

    protected function start(string $time = '12:00:00'): Carbon
    {
        return Carbon::parse('2030-01-08 '.$time, 'Europe/Istanbul');
    }

    protected function book(array $overrides = [], ?AppointmentService $backend = null): Appointment
    {
        return ($backend ?? $this->appointments)->create(array_replace([
            'customer_id' => $this->customer->id, 'service_id' => $this->service->id,
            'employee_id' => $this->employee->id, 'starts_at' => $this->start(),
        ], $overrides), $this->company);
    }

    protected function reject(callable $operation, string $exceptionClass = InvalidArgumentException::class): void
    {
        try {
            $operation();
        } catch (\Throwable $exception) {
            $this->assertInstanceOf($exceptionClass, $exception);
            return;
        }
        $this->fail('Expected rejection: '.$exceptionClass);
    }

    public function test_creation_preserves_snapshots_and_normalizes_utc_without_mutating_input(): void
    {
        $input = $this->start();
        $appointment = $this->book(['starts_at' => $input, 'company_id' => $this->other->id]);
        $this->assertSame($this->company->id, $appointment->company_id);
        $this->assertSame(AppointmentStatus::CONFIRMED, $appointment->status);
        $this->assertSame('2030-01-08 09:00:00', $appointment->fresh()->getRawOriginal('starts_at'));
        $this->assertSame('2030-01-08 09:45:00', $appointment->fresh()->getRawOriginal('ends_at'));
        $this->assertSame('12:00', $input->format('H:i'));
        $this->assertSame('Europe/Istanbul', $input->timezoneName);
        $this->assertSame('Haircut', $appointment->service_name_snapshot);
        $this->assertSame(45, $appointment->service_duration_minutes_snapshot);
        $this->assertSame(25000, $appointment->service_price_minor_units_snapshot);
        $this->assertSame('First', $appointment->employee_name_snapshot);
        $this->assertSame('Client', $appointment->customer_name_snapshot);
        $this->assertSame('+905551112233', $appointment->customer_phone_snapshot);
        $this->assertSame(AppointmentEventType::CREATED, $appointment->events()->sole()->type);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $this->company->id, 'action' => 'appointment.created']);
    }

    public function test_booking_codes_have_required_format_and_are_unique(): void
    {
        $codes = [];
        for ($i = 0; $i < 4; $i++) {
            $code = $this->book(['starts_at' => $this->start()->addHours($i)])->booking_code;
            $this->assertMatchesRegularExpression('/^AP-[A-HJ-NP-Z2-9]{8}$/', $code);
            $codes[] = $code;
        }
        $this->assertCount(4, array_unique($codes));
    }

    protected function codeBackend(array $codes): AppointmentService
    {
        return new class(app(AvailabilityService::class), app(CustomerRestrictionService::class), $codes) extends AppointmentService {
            public int $attempts = 0;
            public function __construct(AvailabilityService $availability, CustomerRestrictionService $restrictions, private array $codes)
            {
                parent::__construct($availability, $restrictions);
            }
            protected function generateBookingCode(): string
            {
                return $this->codes[min($this->attempts++, count($this->codes) - 1)];
            }
        };
    }

    public function test_insert_collision_retries_and_rolls_back_partial_work(): void
    {
        $first = $this->book();
        $backend = $this->codeBackend([$first->booking_code, 'AP-ABCDEFGH']);
        $second = $this->book(['starts_at' => $this->start('13:00:00')], $backend);
        $this->assertSame('AP-ABCDEFGH', $second->booking_code);
        $this->assertSame(2, $backend->attempts);
        $this->assertDatabaseCount('appointments', 2);
        $this->assertDatabaseCount('appointment_events', 2);
    }

    public function test_collision_retry_is_bounded(): void
    {
        $first = $this->book();
        $backend = $this->codeBackend([$first->booking_code]);
        $this->reject(fn () => $this->book(['starts_at' => $this->start('13:00:00')], $backend), RuntimeException::class);
        $this->assertSame(10, $backend->attempts);
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('appointment_events', 1);
    }

    public function test_booking_code_uniqueness_applies_across_companies(): void
    {
        $first = $this->book();
        $service = $this->other->services()->create(['name' => 'Other', 'price_minor_units' => 100, 'duration_minutes' => 30, 'active' => true]);
        $employee = $this->makeEmployee($this->other, 'Other');
        $customer = $this->other->customers()->create(['name' => 'Other', 'phone' => '05552223344', 'active' => true]);
        $service->employees()->attach($employee);
        foreach ([$service, $employee] as $model) {
            $model->weeklyAvailabilities()->create(['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '18:00']);
        }
        $backend = $this->codeBackend([$first->booking_code, 'AP-ZZZZZZZZ']);
        $appointment = $backend->create([
            'service_id' => $service->id, 'employee_id' => $employee->id,
            'customer_id' => $customer->id, 'starts_at' => $this->start(),
        ], $this->other);
        $this->assertSame(2, $backend->attempts);
        $this->assertSame($this->other->id, $appointment->company_id);
        $this->assertSame('AP-ZZZZZZZZ', $appointment->booking_code);
    }

    public function test_equivalent_utc_and_istanbul_instants_conflict(): void
    {
        $this->book();
        $this->reject(fn () => $this->book(['starts_at' => $this->start()->utc()]));
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_expired_block_does_not_prevent_booking(): void
    {
        $block = app(CustomerRestrictionService::class)->manualBlock($this->customer, 'Expired', 1);
        $block->update(['starts_at' => Carbon::now()->subDays(2), 'ends_at' => Carbon::now()]);
        $this->assertSame($this->customer->id, $this->book()->customer_id);
    }

    public function test_blocked_customer_is_rejected_via_restriction_service(): void
    {
        app(CustomerRestrictionService::class)->manualBlock($this->customer, 'Blocked', 7);
        $this->reject(fn () => $this->book());
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_inactive_customer_is_rejected(): void
    {
        $this->customer->update(['active' => false]);
        $this->reject(fn () => $this->book());
    }

    #[DataProvider('foreignEntities')]
    public function test_foreign_company_entities_are_rejected(string $field): void
    {
        $foreign = match ($field) {
            'customer_id' => $this->other->customers()->create(['name' => 'Other', 'phone' => '05552223344']),
            'service_id' => $this->other->services()->create(['name' => 'Other', 'price_minor_units' => 100, 'duration_minutes' => 30, 'active' => true]),
            'employee_id' => $this->makeEmployee($this->other, 'Other'),
        };
        $this->reject(fn () => $this->book([$field => $foreign->id]), ModelNotFoundException::class);
        $this->assertDatabaseCount('appointments', 0);
    }

    public static function foreignEntities(): array
    {
        return [['customer_id'], ['service_id'], ['employee_id']];
    }

    public function test_inactive_service_is_rejected(): void
    {
        $this->service->update(['active' => false]);
        $this->reject(fn () => $this->book());
    }

    public function test_inactive_employee_is_rejected(): void
    {
        $this->employee->update(['active' => false]);
        $this->reject(fn () => $this->book());
    }

    public function test_unassigned_employee_is_rejected(): void
    {
        $this->service->employees()->detach($this->employee);
        $this->reject(fn () => $this->book());
    }

    #[DataProvider('invalidSlots')]
    public function test_invalid_slot_is_rejected(string $time): void
    {
        $this->reject(fn () => $this->book(['starts_at' => Carbon::parse($time, 'Europe/Istanbul')]));
    }

    public static function invalidSlots(): array
    {
        return [
            'past' => ['2030-01-06 12:00:00'],
            'horizon' => ['2030-01-15 12:00:00'],
            'closed' => ['2030-01-08 08:00:00'],
            'duration overrun' => ['2030-01-08 17:30:00'],
            'seconds overrun' => ['2030-01-08 17:15:01'],
        ];
    }

    public function test_overlap_rejected_and_both_adjacent_boundaries_allowed(): void
    {
        $this->book();
        $this->reject(fn () => $this->book(['starts_at' => $this->start('12:15:00')]));
        $this->book(['starts_at' => $this->start('11:15:00')]);
        $this->book(['starts_at' => $this->start('12:45:00')]);
        $this->assertDatabaseCount('appointments', 3);
    }

    public function test_any_barber_is_deterministic_and_falls_back_after_conflict(): void
    {
        $first = $this->book(['employee_id' => null]);
        $second = $this->book(['employee_id' => null]);
        $this->assertSame($this->employee->id, $first->employee_id);
        $this->assertSame($this->second->id, $second->employee_id);
        $this->reject(fn () => $this->book(['employee_id' => null]));
    }

    public function test_any_barber_skips_inactive_employee(): void
    {
        $this->employee->update(['active' => false]);
        $this->assertSame($this->second->id, $this->book(['employee_id' => null])->employee_id);
    }

    #[DataProvider('cancellationBoundaries')]
    public function test_late_cancellation_boundary(int $minutes, int $violations): void
    {
        $appointment = $this->book();
        Carbon::setTestNow($appointment->starts_at->copy()->subMinutes($minutes));
        $this->appointments->cancelByCustomer($appointment, $this->company);
        $this->assertDatabaseCount('customer_violations', $violations);
        $this->assertDatabaseCount('appointment_events', 2);
        $this->assertSame(AppointmentStatus::CANCELLED_BY_CUSTOMER, $appointment->fresh()->status);
        if ($violations) {
            $this->assertDatabaseHas('customer_violations', ['appointment_id' => $appointment->id, 'type' => 'late_cancellation']);
        }
    }

    public static function cancellationBoundaries(): array
    {
        return ['24h01' => [1441, 0], '24h00' => [1440, 1], '23h59' => [1439, 1]];
    }

    #[DataProvider('terminalActions')]
    public function test_terminal_actions_reload_stale_models_and_never_duplicate(string $method, int $violations): void
    {
        $appointment = $this->book();
        $stale = $appointment->fresh();
        Carbon::setTestNow($appointment->starts_at);
        $this->appointments->$method($appointment, $this->company);
        foreach (['cancelByCustomer', 'cancelByCompany', 'markCompleted', 'markNoShow'] as $next) {
            $this->reject(fn () => $this->appointments->$next($stale, $this->company));
        }
        $this->reject(fn () => $this->appointments->reschedule($stale, $this->company, $this->start('14:00:00')));
        $this->assertDatabaseCount('appointment_events', 2);
        $this->assertDatabaseCount('customer_violations', $violations);
    }

    public static function terminalActions(): array
    {
        return [['cancelByCustomer', 1], ['cancelByCompany', 0], ['markCompleted', 0], ['markNoShow', 1]];
    }

    public function test_future_appointment_cannot_be_completed_or_no_show(): void
    {
        $appointment = $this->book();
        $this->reject(fn () => $this->appointments->markCompleted($appointment, $this->company));
        $this->reject(fn () => $this->appointments->markNoShow($appointment, $this->company));
        $this->assertDatabaseCount('appointment_events', 1);
        $this->assertDatabaseCount('customer_violations', 0);
    }

    public function test_reschedule_preserves_snapshots_and_uses_original_duration_for_availability(): void
    {
        $appointment = $this->book();
        $snapshots = array_filter($appointment->getAttributes(), fn ($key) => str_ends_with($key, '_snapshot'), ARRAY_FILTER_USE_KEY);
        $this->service->update(['name' => 'New haircut', 'duration_minutes' => 120, 'price_minor_units' => 99000]);
        $this->employee->update(['name' => 'Renamed barber']);
        $this->customer->update(['name' => 'Renamed client']);
        $changed = $this->appointments->reschedule($appointment, $this->company, $this->start('17:15:00'), $this->second->id);
        $this->assertSame('15:00', $changed->fresh()->ends_at->format('H:i'));
        $this->assertSame(45.0, $changed->starts_at->diffInMinutes($changed->ends_at));
        $this->assertSame(AppointmentStatus::CONFIRMED, $changed->status);
        foreach ($snapshots as $key => $value) {
            $this->assertSame($value, $changed->$key);
        }
        $event = $changed->events()->where('type', 'rescheduled')->sole();
        $this->assertSame([
            'old_starts_at' => '2030-01-08T09:00:00+00:00',
            'old_ends_at' => '2030-01-08T09:45:00+00:00',
            'old_employee_id' => $this->employee->id,
            'old_employee_name' => 'Renamed barber',
            'new_starts_at' => '2030-01-08T14:15:00+00:00',
            'new_ends_at' => '2030-01-08T15:00:00+00:00',
            'new_employee_id' => $this->second->id,
            'new_employee_name' => 'Second',
        ], $event->metadata);
        $this->assertDatabaseCount('customer_violations', 0);
    }

    public function test_reschedule_cannot_use_shortened_current_duration_to_fit_a_slot(): void
    {
        $appointment = $this->book();
        $this->service->update(['duration_minutes' => 5]);
        $this->reject(fn () => $this->appointments->reschedule($appointment, $this->company, $this->start('17:30:00')));
        $this->assertDatabaseCount('appointment_events', 1);
    }

    public function test_reschedule_rechecks_conflict_horizon_past_and_ownership(): void
    {
        $appointment = $this->book();
        $this->book(['starts_at' => $this->start('14:00:00')]);
        foreach ([$this->start('14:15:00'), $this->start()->addDays(8), $this->start()->subDays(3)] as $time) {
            $this->reject(fn () => $this->appointments->reschedule($appointment, $this->company, $time));
        }
        $foreign = $this->makeEmployee($this->other, 'Other');
        $this->reject(fn () => $this->appointments->reschedule($appointment, $this->company, $this->start('15:00:00'), $foreign->id), ModelNotFoundException::class);
        $same = $this->appointments->reschedule($appointment, $this->company, $this->start());
        $this->assertSame($appointment->id, $same->id); // Excludes itself from conflicts.
    }

    public function test_stale_employee_on_reschedule_does_not_restore_previous_employee(): void
    {
        $appointment = $this->book();
        $this->appointments->reschedule($appointment, $this->company, $this->start('13:00:00'), $this->second->id);
        $result = $this->appointments->reschedule($appointment, $this->company, $this->start('14:00:00'));
        $this->assertSame($this->second->id, $result->employee_id);
    }

    public function test_all_mutations_reject_wrong_company_context(): void
    {
        $appointment = $this->book();
        foreach (['cancelByCustomer', 'cancelByCompany', 'markCompleted', 'markNoShow'] as $method) {
            $this->reject(fn () => $this->appointments->$method($appointment, $this->other), ModelNotFoundException::class);
        }
        $this->reject(fn () => $this->appointments->reschedule($appointment, $this->other, $this->start('13:00:00')), ModelNotFoundException::class);
        $this->assertDatabaseCount('appointment_events', 1);
    }

    public function test_corrupt_cross_company_appointment_reference_is_rejected(): void
    {
        $appointment = $this->book();
        $foreign = $this->other->customers()->create(['name' => 'Other', 'phone' => '05552223344']);
        // Simulate legacy corruption outside the guarded model/service boundary.
        DB::table('appointments')->where('id', $appointment->id)->update(['customer_id' => $foreign->id]);
        $this->reject(fn () => $this->appointments->cancelByCustomer($appointment, $this->company), ModelNotFoundException::class);
        $this->assertDatabaseCount('customer_violations', 0);
        $this->assertDatabaseCount('appointment_events', 1);
    }

    public function test_pending_migration_protects_history_from_parent_deletion(): void
    {
        $appointment = $this->book();
        foreach (['appointments' => $appointment->id, 'employees' => $this->employee->id, 'customers' => $this->customer->id, 'services' => $this->service->id] as $table => $id) {
            $this->reject(fn () => DB::table($table)->where('id', $id)->delete(), \Illuminate\Database\QueryException::class);
        }
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('appointment_events', 1);
    }

    public function test_suspended_company_cannot_book_or_perform_manager_mutations(): void
    {
        $appointment = $this->book();
        $this->company->update(['status' => Company::STATUS_SUSPENDED]);
        $this->reject(fn () => $this->book(['starts_at' => $this->start('14:00:00')]));
        foreach (['cancelByCompany', 'markCompleted', 'markNoShow'] as $method) {
            $this->reject(fn () => $this->appointments->$method($appointment, $this->company));
        }
        $this->reject(fn () => $this->appointments->reschedule($appointment, $this->company, $this->start('13:00:00')));
    }

    public function test_events_and_booking_snapshots_reject_normal_model_mutation(): void
    {
        $appointment = $this->book();
        $event = $appointment->events()->sole();
        $this->reject(fn () => $event->update(['metadata' => ['tampered' => true]]), LogicException::class);
        $this->reject(fn () => $event->delete(), LogicException::class);
        $this->reject(fn () => $appointment->update(['service_name_snapshot' => 'Tampered']), LogicException::class);
        $this->assertDatabaseCount('appointment_events', 1);
        $this->assertSame('Haircut', $appointment->fresh()->service_name_snapshot);
    }

    public function test_failed_event_write_rolls_back_transition_and_violation(): void
    {
        $appointment = $this->book();
        Carbon::setTestNow($appointment->starts_at);
        $backend = new class(app(AvailabilityService::class), app(CustomerRestrictionService::class)) extends AppointmentService {
            protected function recordEvent(Appointment $appointment, AppointmentEventType $type, ?string $actorType, ?int $actorId, array $metadata): AppointmentEvent
            {
                throw new RuntimeException('Simulated event failure');
            }
        };
        $this->reject(fn () => $backend->markNoShow($appointment, $this->company), RuntimeException::class);
        $this->assertSame(AppointmentStatus::CONFIRMED, $appointment->fresh()->status);
        $this->assertDatabaseCount('customer_violations', 0);
        $this->assertDatabaseCount('appointment_events', 1);
    }

    public function test_scheduling_and_transition_paths_request_locks_inside_transactions(): void
    {
        // Instrument real query compilation: SQLite ignores FOR UPDATE, so capture
        // lock intent before compilation rather than pretending SQLite proves MySQL locks.
        $connection = DB::connection();
        $original = $connection->getQueryGrammar();
        $grammar = new class($connection) extends SQLiteGrammar {
            public array $locks = [];
            public function compileSelect(Builder $query)
            {
                if ($query->lock === true) {
                    $this->locks[] = [$query->from, $query->connection->transactionLevel()];
                }
                return parent::compileSelect($query);
            }
        };
        $connection->setQueryGrammar($grammar);
        try {
            $appointment = $this->book();
            $this->assertSame('employees', $grammar->locks[0][0]);
            $this->assertContains('appointments', array_column($grammar->locks, 0));
            foreach ($grammar->locks as [, $level]) {
                $this->assertGreaterThan(1, $level); // RefreshDatabase already owns level 1.
            }
            $grammar->locks = [];
            $this->appointments->reschedule($appointment, $this->company, $this->start('13:00:00'));
            $this->assertSame(['employees', 'appointments'], array_slice(array_column($grammar->locks, 0), 0, 2));
            foreach (['cancelByCustomer', 'cancelByCompany', 'markCompleted', 'markNoShow'] as $method) {
                $another = $this->book(['starts_at' => $this->start('15:00:00')]);
                Carbon::setTestNow($another->starts_at);
                $grammar->locks = [];
                $this->appointments->$method($another, $this->company);
                $this->assertSame('appointments', $grammar->locks[0][0]);
                $this->assertGreaterThan(1, $grammar->locks[0][1]);
                Carbon::setTestNow(Carbon::parse('2030-01-07 09:00:00', 'Europe/Istanbul'));
            }
        } finally {
            $connection->setQueryGrammar($original);
        }
    }
}
