<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\User;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AppointmentHttpTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Company $other;
    private User $manager;
    private Appointment $appointment;
    private Appointment $foreign;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('HTTP tests require isolated SQLite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2030-01-07 09:00:00', 'Europe/Istanbul'));
        [$this->company, $this->appointment] = $this->makeTenant('Alpha', '05551112233');
        [$this->other, $this->foreign] = $this->makeTenant('Beta', '05554445566');
        $this->manager = $this->company->manager;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeTenant(string $name, string $phone): array
    {
        $company = Company::create([
            'name' => $name, 'code' => 'SLN-'.$name, 'phone' => $phone,
            'manager_id' => User::factory()->create(['role' => User::ROLE_COMPANY_MANAGER])->id,
            'status' => Company::STATUS_ACTIVE, 'booking_days_ahead' => 7,
        ]);
        $customer = $company->customers()->create(['name' => $name.' Client', 'phone' => $phone, 'active' => true]);
        $service = $company->services()->create(['name' => $name.' Haircut', 'price_minor_units' => 25000, 'duration_minutes' => 45, 'active' => true]);
        $employee = $company->employees()->create(['name' => $name.' Barber', 'username' => $name, 'password' => 'test-password', 'active' => true]);
        $service->employees()->attach($employee);
        foreach ([$service, $employee] as $model) {
            for ($day = 1; $day <= 7; $day++) {
                $model->weeklyAvailabilities()->create(['day_of_week' => $day, 'start_time' => '09:00', 'end_time' => '18:00']);
            }
        }
        $appointment = app(AppointmentService::class)->create([
            'customer_id' => $customer->id, 'service_id' => $service->id, 'employee_id' => $employee->id,
            'starts_at' => Carbon::parse('2030-01-07 12:00:00', 'Europe/Istanbul'),
        ], $company);

        return [$company, $appointment];
    }

    private function url(string $action, ?Appointment $appointment = null): string
    {
        return route('company.appointments.'.$action, $action === 'index' ? [] : ($appointment ?? $this->appointment));
    }

    private function historyFixture(array $overrides): Appointment
    {
        $attributes = $this->appointment->getAttributes();
        unset($attributes['id'], $attributes['company_id'], $attributes['created_at'], $attributes['updated_at']);

        return $this->company->appointments()->create(array_replace($attributes, [
            'booking_code' => 'AP-'.Str::upper(Str::random(8)),
        ], $overrides));
    }

    public function test_manager_lists_only_own_appointments_and_can_open_details(): void
    {
        $this->actingAs($this->manager)->get($this->url('index'))->assertOk()
            ->assertSee($this->appointment->booking_code)->assertDontSee($this->foreign->booking_code)
            ->assertSee('Alpha Client')->assertDontSee('Beta Client');
        $this->get($this->url('show'))->assertOk()->assertSee('Alpha Haircut')
            ->assertSee('250,00')->assertSee('45 minutes')->assertSee('Event history')
            ->assertSee('07 Jan 2030 12:00')->assertSee('Appointment Created');
    }

    #[DataProvider('endpoints')]
    public function test_cross_tenant_requests_return_404(string $method, string $action): void
    {
        $this->actingAs($this->manager)->json($method, $this->url($action, $this->foreign), ['starts_at' => '2030-01-07T14:00'])
            ->assertNotFound();
        $this->assertSame(AppointmentStatus::CONFIRMED, $this->foreign->fresh()->status);
        $this->assertSame(1, $this->foreign->events()->count());
    }

    public static function endpoints(): array
    {
        return [['GET', 'show'], ['PATCH', 'reschedule'], ['POST', 'cancel'], ['POST', 'complete'], ['POST', 'no-show']];
    }

    public function test_exactly_six_routes_exist_and_unwanted_actions_are_unavailable(): void
    {
        $routes = collect(Route::getRoutes())->filter(fn ($route) => str_starts_with($route->getName() ?? '', 'company.appointments.'));
        $this->assertEqualsCanonicalizing([
            'company.appointments.index', 'company.appointments.show', 'company.appointments.reschedule',
            'company.appointments.cancel', 'company.appointments.complete', 'company.appointments.no-show',
        ], $routes->map->getName()->values()->all());
        foreach ($routes as $route) {
            $this->assertContains('company', $route->gatherMiddleware());
        }
        foreach (['create', 'store', 'edit', 'destroy'] as $action) {
            $this->assertFalse(Route::has('company.appointments.'.$action));
        }
        $this->actingAs($this->manager)->get('/company/appointments/create')->assertNotFound();
        $this->post('/company/appointments')->assertStatus(405);
        $this->get('/company/appointments/'.$this->appointment->id.'/edit')->assertNotFound();
        $this->delete($this->url('show'))->assertStatus(405);
    }

    public function test_guests_and_admins_cannot_use_manager_routes(): void
    {
        foreach ([['GET', 'index'], ...self::endpoints()] as [$method, $action]) {
            $this->call($method, $this->url($action))->assertRedirect(route('login'));
        }
        $admin = User::factory()->create(['role' => User::ROLE_SYSTEM_ADMIN]);
        $this->actingAs($admin);
        foreach ([['GET', 'index'], ...self::endpoints()] as [$method, $action]) {
            $this->json($method, $this->url($action))->assertForbidden();
        }
    }

    public function test_valid_reschedule_normalizes_istanbul_input_and_records_actor(): void
    {
        $this->actingAs($this->manager)->patch($this->url('reschedule'), ['starts_at' => '2030-01-07T14:00'])
            ->assertRedirect($this->url('show'))->assertSessionHas('status');
        $appointment = $this->appointment->fresh();
        $this->assertSame('2030-01-07 11:00:00', $appointment->getRawOriginal('starts_at'));
        $this->assertSame('2030-01-07 11:45:00', $appointment->getRawOriginal('ends_at'));
        $this->assertSame(45, $appointment->service_duration_minutes_snapshot);
        $event = $appointment->events()->where('type', 'rescheduled')->sole();
        $this->assertSame($this->manager->id, $event->actor_id);
        $this->assertSame('user', $event->actor_type);
        $this->assertDatabaseCount('customer_violations', 0);
        $this->get($this->url('show'))->assertOk()->assertSee('Rescheduled')->assertSee('07 Jan 2030 14:00');
    }

    public function test_cross_company_employee_is_rejected(): void
    {
        $this->actingAs($this->manager)->patchJson($this->url('reschedule'), [
            'starts_at' => '2030-01-07T14:00', 'employee_id' => $this->foreign->employee_id,
        ])->assertUnprocessable()->assertJsonValidationErrors('employee_id');
        $this->assertSame($this->appointment->employee_id, $this->appointment->fresh()->employee_id);
    }

    public function test_employee_change_uses_service_eligibility_checks(): void
    {
        $employee = $this->company->employees()->create(['name' => 'Second Barber', 'username' => 'second', 'password' => 'test-password', 'active' => true]);
        $this->actingAs($this->manager)->patchJson($this->url('reschedule'), [
            'starts_at' => '2030-01-07T14:00', 'employee_id' => $employee->id,
        ])->assertUnprocessable();
        $this->appointment->service->employees()->attach($employee);
        $employee->weeklyAvailabilities()->create(['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '18:00']);
        $this->patch($this->url('reschedule'), ['starts_at' => '2030-01-07T14:00', 'employee_id' => $employee->id])
            ->assertRedirect($this->url('show'));
        $this->assertSame($employee->id, $this->appointment->fresh()->employee_id);
        $this->assertSame('Alpha Barber', $this->appointment->fresh()->employee_name_snapshot);
    }

    #[DataProvider('invalidDates')]
    public function test_invalid_reschedule_is_rejected(mixed $date): void
    {
        $this->actingAs($this->manager)->patchJson($this->url('reschedule'), ['starts_at' => $date])
            ->assertUnprocessable()->assertJsonValidationErrors('starts_at');
        $this->assertSame(1, $this->appointment->events()->count());
    }

    public static function invalidDates(): array
    {
        return [[null], [['invalid']], ['tomorrow'], ['2030-02-30T12:00'], ['2030-01-06T12:00'], ['2030-01-16T12:00'], ['2030-01-07T18:00']];
    }

    public function test_invalid_form_input_can_be_redisplayed_without_render_errors(): void
    {
        $this->actingAs($this->manager)->from($this->url('show'))->patch($this->url('reschedule'), [
            'starts_at' => ['invalid'], 'employee_id' => ['invalid'],
        ])->assertRedirect($this->url('show'))->assertSessionHasErrors(['starts_at', 'employee_id']);
        $messages = session('errors')->getBag('default')->getMessages();
        // The project uses JSON sessions. A second in-process request needs the
        // serialized bag shape that a browser request would load from storage.
        $this->withSession([
            '_old_input' => ['starts_at' => ['invalid'], 'employee_id' => ['invalid']],
            'errors' => ['default' => ['messages' => $messages, 'format' => ':message']],
        ])->get($this->url('show'))->assertOk()
            ->assertSee('The appointment could not be changed.');
    }

    public function test_conflicting_reschedule_is_rejected_without_changes(): void
    {
        $this->historyFixture(['starts_at' => '2030-01-07 11:00:00', 'ends_at' => '2030-01-07 11:45:00']);
        $this->actingAs($this->manager)->from($this->url('show'))
            ->patch($this->url('reschedule'), ['starts_at' => '2030-01-07T14:15'])
            ->assertRedirect($this->url('show'))->assertSessionHasErrors('starts_at');
        $this->assertSame('2030-01-07 09:00:00', $this->appointment->fresh()->getRawOriginal('starts_at'));
    }

    #[DataProvider('protectedFields')]
    public function test_reschedule_rejects_protected_fields(string $field): void
    {
        $this->actingAs($this->manager)->patchJson($this->url('reschedule'), [
            'starts_at' => '2030-01-07T14:00', $field => 'tampered',
        ])->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame(1, $this->appointment->events()->count());
    }

    public static function protectedFields(): array
    {
        return array_map(fn ($field) => [$field], ['company_id', 'customer_id', 'service_id', 'status', 'ends_at',
            'booking_code', 'service_name_snapshot', 'service_price_minor_units_snapshot', 'service_duration_minutes_snapshot',
            'employee_name_snapshot', 'customer_name_snapshot', 'customer_phone_snapshot']);
    }

    public function test_manager_cancellation_never_creates_violation(): void
    {
        $this->actingAs($this->manager)->post($this->url('cancel'), ['status' => 'no_show', 'company_id' => $this->other->id])
            ->assertRedirect($this->url('show'));
        $this->assertSame(AppointmentStatus::CANCELLED_BY_COMPANY, $this->appointment->fresh()->status);
        $this->assertDatabaseCount('customer_violations', 0);
        $this->postJson($this->url('cancel'))->assertUnprocessable();
        $this->assertSame(2, $this->appointment->events()->count());
    }

    #[DataProvider('timedActions')]
    public function test_timed_actions_reject_future_then_succeed_once(string $action, AppointmentStatus $status, int $violations): void
    {
        $this->actingAs($this->manager)->postJson($this->url($action))->assertUnprocessable();
        $this->assertSame(1, $this->appointment->events()->count());
        Carbon::setTestNow($this->appointment->starts_at);
        $this->post($this->url($action))->assertRedirect($this->url('show'));
        $this->assertSame($status, $this->appointment->fresh()->status);
        $this->assertDatabaseCount('customer_violations', $violations);
        $this->postJson($this->url($action))->assertUnprocessable();
        $this->assertDatabaseCount('customer_violations', $violations);
        $this->assertSame(2, $this->appointment->events()->count());
    }

    public static function timedActions(): array
    {
        return [['complete', AppointmentStatus::COMPLETED, 0], ['no-show', AppointmentStatus::NO_SHOW, 1]];
    }

    public function test_terminal_appointment_rejects_every_mutation_and_hides_forms(): void
    {
        app(AppointmentService::class)->cancelByCompany($this->appointment, $this->company);
        $this->actingAs($this->manager);
        foreach (array_slice(self::endpoints(), 1) as [$method, $action]) {
            $this->json($method, $this->url($action), ['starts_at' => '2030-01-07T14:00'])->assertUnprocessable();
        }
        $response = $this->get($this->url('show'))->assertOk();
        foreach (['reschedule', 'cancel', 'complete', 'no-show'] as $action) {
            $response->assertDontSee('action="'.$this->url($action).'"', false);
        }
        $this->assertSame(2, $this->appointment->events()->count());
    }

    public function test_suspended_company_retains_read_only_access(): void
    {
        $this->company->update(['status' => Company::STATUS_SUSPENDED]);
        $this->actingAs($this->manager)->get($this->url('index'))->assertOk()->assertSee('read-only');
        $response = $this->get($this->url('show'))->assertOk()->assertSee('read-only');
        foreach (array_slice(self::endpoints(), 1) as [$method, $action]) {
            $this->json($method, $this->url($action), ['starts_at' => '2030-01-07T14:00'])->assertForbidden();
            $response->assertDontSee('action="'.$this->url($action).'"', false);
        }
        $this->assertSame(1, $this->appointment->events()->count());
    }

    public function test_future_actions_are_hidden_but_eligible_actions_are_shown(): void
    {
        $this->actingAs($this->manager)->get($this->url('show'))->assertOk()
            ->assertDontSee('action="'.$this->url('complete').'"', false)
            ->assertDontSee('action="'.$this->url('no-show').'"', false)
            ->assertSee('action="'.$this->url('reschedule').'"', false);
        Carbon::setTestNow($this->appointment->starts_at);
        $this->get($this->url('show'))->assertOk()->assertSee('action="'.$this->url('complete').'"', false)
            ->assertSee('action="'.$this->url('no-show').'"', false);
    }

    public function test_filters_search_and_today_use_tenant_scope_and_istanbul_day_boundaries(): void
    {
        $localMidnight = $this->historyFixture(['starts_at' => '2030-01-06 21:30:00', 'ends_at' => '2030-01-06 22:15:00']);
        $previousDay = $this->historyFixture(['starts_at' => '2030-01-06 20:00:00', 'ends_at' => '2030-01-06 20:45:00']);
        $completed = $this->historyFixture(['status' => AppointmentStatus::COMPLETED]);
        $noShow = $this->historyFixture(['status' => AppointmentStatus::NO_SHOW]);
        $cancelled = $this->historyFixture(['status' => AppointmentStatus::CANCELLED_BY_CUSTOMER]);
        $this->actingAs($this->manager)->get($this->url('index').'?filter=today')->assertOk()
            ->assertSee($localMidnight->booking_code)->assertDontSee($previousDay->booking_code)->assertDontSee($this->foreign->booking_code);
        $this->get($this->url('index').'?filter=upcoming')->assertOk()->assertSee($this->appointment->booking_code)
            ->assertDontSee($localMidnight->booking_code)->assertDontSee($completed->booking_code);
        foreach (['completed' => $completed, 'no-show' => $noShow, 'cancelled' => $cancelled] as $filter => $expected) {
            $this->get($this->url('index').'?filter='.$filter)->assertOk()->assertSee($expected->booking_code)
                ->assertDontSee($this->appointment->booking_code)->assertDontSee($this->foreign->booking_code);
        }
        foreach ([$this->appointment->booking_code, 'Alpha Client', '+905551112233'] as $search) {
            $this->get($this->url('index').'?'.http_build_query(['search' => $search]))->assertOk()
                ->assertSee($this->appointment->booking_code)->assertDontSee($this->foreign->booking_code);
        }
    }

    public function test_company_sidebar_has_appointments_but_admin_sidebar_does_not(): void
    {
        $this->actingAs($this->manager)->get(route('company.dashboard'))->assertOk()->assertSee($this->url('index'), false);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_SYSTEM_ADMIN]))->get(route('admin.dashboard'))
            ->assertOk()->assertDontSee($this->url('index'), false);
    }
}
