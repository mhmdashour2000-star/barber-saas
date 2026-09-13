<?php

namespace App\Http\Controllers\Company;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\RescheduleAppointmentRequest;
use App\Models\Appointment;
use App\Models\Company;
use App\Services\AppointmentService;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AppointmentController extends Controller
{
    public function __construct(private AppointmentService $appointments) {}

    public function index(Request $request): View
    {
        $company = $this->company($request);
        $filters = $request->validate([
            'filter' => ['nullable', Rule::in(['all', 'today', 'upcoming', 'completed', 'no-show', 'cancelled'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $filter = $filters['filter'] ?? 'all';
        $search = trim($filters['search'] ?? '');
        $timezone = AvailabilityService::TIMEZONE;
        $now = Carbon::now($timezone);
        $query = $company->appointments()->with(['employee' => fn ($query) => $query->where('company_id', $company->id)]);

        if ($filter === 'today') {
            $query->where('starts_at', '>=', $now->copy()->startOfDay()->utc())
                ->where('starts_at', '<', $now->copy()->startOfDay()->addDay()->utc());
        } elseif ($filter === 'upcoming') {
            $query->where('status', AppointmentStatus::CONFIRMED)->where('starts_at', '>=', $now->copy()->utc());
        } elseif ($filter === 'completed') {
            $query->where('status', AppointmentStatus::COMPLETED);
        } elseif ($filter === 'no-show') {
            $query->where('status', AppointmentStatus::NO_SHOW);
        } elseif ($filter === 'cancelled') {
            $query->whereIn('status', [AppointmentStatus::CANCELLED_BY_CUSTOMER, AppointmentStatus::CANCELLED_BY_COMPANY]);
        }
        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('booking_code', 'like', '%'.$search.'%')
                    ->orWhere('customer_name_snapshot', 'like', '%'.$search.'%')
                    ->orWhere('customer_phone_snapshot', 'like', '%'.$search.'%');
            });
        }
        $appointments = $query->orderBy('starts_at')->orderBy('id')->paginate(15)->withQueryString();

        return view('company.appointments.index', compact('company', 'appointments', 'filter', 'search', 'timezone'));
    }

    public function show(Request $request, int $appointment): View
    {
        $company = $this->company($request);
        $appointment = $company->appointments()->with([
            'employee' => fn ($query) => $query->where('company_id', $company->id),
            'events' => fn ($query) => $query->where('company_id', $company->id),
        ])->findOrFail($appointment);
        $employees = $company->employees()->where('active', true)
            ->whereHas('services', fn ($query) => $query->where('services.id', $appointment->service_id))
            ->orderBy('name')->get();
        $timezone = AvailabilityService::TIMEZONE;

        return view('company.appointments.show', compact('company', 'appointment', 'employees', 'timezone'));
    }

    public function reschedule(RescheduleAppointmentRequest $request, int $appointment): RedirectResponse
    {
        [$company, $appointment] = $this->mutationContext($request, $appointment);
        $data = $request->validated();
        try {
            $this->appointments->reschedule($appointment, $company,
                Carbon::parse($data['starts_at'], AvailabilityService::TIMEZONE),
                isset($data['employee_id']) ? (int) $data['employee_id'] : null,
                'user', $request->user()->id);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['starts_at' => $exception->getMessage()]);
        }

        return to_route('company.appointments.show', $appointment)->with('status', 'Appointment rescheduled.');
    }

    public function cancel(Request $request, int $appointment): RedirectResponse
    {
        return $this->mutate($request, $appointment, 'cancelByCompany', 'Appointment cancelled by your salon.');
    }

    public function complete(Request $request, int $appointment): RedirectResponse
    {
        return $this->mutate($request, $appointment, 'markCompleted', 'Appointment marked completed.');
    }

    public function noShow(Request $request, int $appointment): RedirectResponse
    {
        return $this->mutate($request, $appointment, 'markNoShow', 'Appointment marked as no-show.');
    }

    private function company(Request $request): Company
    {
        $company = $request->user()->company;
        abort_unless($company, 404, 'Company not found.');

        return $company;
    }

    private function mutationContext(Request $request, int $id): array
    {
        $company = $this->company($request);
        $appointment = $company->appointments()->findOrFail($id);
        abort_if($company->status === Company::STATUS_SUSPENDED, 403, 'Your company account is currently suspended.');

        return [$company, $appointment];
    }

    private function mutate(Request $request, int $id, string $method, string $message): RedirectResponse
    {
        [$company, $appointment] = $this->mutationContext($request, $id);
        try {
            $this->appointments->$method($appointment, $company, 'user', $request->user()->id);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['appointment' => $exception->getMessage()]);
        }

        return to_route('company.appointments.show', $appointment)->with('status', $message);
    }
}
