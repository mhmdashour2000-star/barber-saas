<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreAvailabilityExceptionRequest;
use App\Http\Requests\Company\UpdateWeeklyScheduleRequest;
use App\Models\AuditLog;
use App\Models\AvailabilityException;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AvailabilityController extends Controller
{
    /**
     * Display salon availability overview (Upcoming closures, special hours, exception list).
     */
    public function index(Request $request): View
    {
        $company = $request->user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        $exceptions = $company->availabilityExceptions()
            ->with(['service', 'employee', 'windows'])
            ->orderBy('date')
            ->paginate(15);

        $services = $company->services()->where('active', true)->orderBy('name')->get();
        $employees = $company->employees()->where('active', true)->orderBy('name')->get();

        return view('company.availability.index', compact('company', 'exceptions', 'services', 'employees'));
    }

    /**
     * Store a new date-specific availability exception (Company closure/custom hours, service exception, or employee leave).
     */
    public function storeException(StoreAvailabilityExceptionRequest $request): RedirectResponse
    {
        $company = $request->user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        $validated = $request->validated();

        DB::transaction(function () use ($company, $validated) {
            /** @var AvailabilityException $exception */
            $exception = $company->availabilityExceptions()->create([
                'type' => $validated['type'],
                'date' => $validated['date'],
                'is_closed' => $validated['is_closed'],
                'reason' => $validated['reason'] ?? null,
                'service_id' => $validated['type'] === AvailabilityException::TYPE_SERVICE ? ($validated['service_id'] ?? null) : null,
                'employee_id' => $validated['type'] === AvailabilityException::TYPE_EMPLOYEE ? ($validated['employee_id'] ?? null) : null,
            ]);

            // Save custom windows if not fully closed
            if (!$validated['is_closed'] && !empty($validated['windows'])) {
                foreach ($validated['windows'] as $window) {
                    if (!empty($window['start_time']) && !empty($window['end_time'])) {
                        $exception->windows()->create([
                            'start_time' => $window['start_time'],
                            'end_time' => $window['end_time'],
                        ]);
                    }
                }
            }
        });

        AuditLog::record(
            'availability.exception.created',
            "Date exception created for {$validated['date']} (Type: {$validated['type']}, Closed: " . ($validated['is_closed'] ? 'Yes' : 'No') . ").",
            $request->user()->id,
            $company->id
        );

        return redirect()
            ->route('company.availability.index')
            ->with('status', 'Availability exception created successfully.');
    }

    /**
     * Delete an availability exception.
     */
    public function destroyException(Request $request, int $exceptionId): RedirectResponse
    {
        $company = $request->user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        if ($company->status === Company::STATUS_SUSPENDED) {
            abort(403, 'Your company account is currently suspended.');
        }

        $exception = $company->availabilityExceptions()->findOrFail($exceptionId);
        $dateStr = $exception->date->format('Y-m-d');
        $typeStr = $exception->type;

        $exception->delete();

        AuditLog::record(
            'availability.exception.deleted',
            "Date exception for {$dateStr} (Type: {$typeStr}) was removed.",
            $request->user()->id,
            $company->id
        );

        return redirect()
            ->route('company.availability.index')
            ->with('status', 'Availability exception deleted successfully.');
    }

    /**
     * Show Service Weekly Schedule configuration view.
     */
    public function editServiceSchedule(int $serviceId): View
    {
        $company = Auth::user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        $service = $company->services()->with('weeklyAvailabilities')->findOrFail($serviceId);

        // Group weekly windows by day (1 = Monday ... 7 = Sunday)
        $scheduleByDay = [];
        for ($i = 1; $i <= 7; $i++) {
            $scheduleByDay[$i] = $service->weeklyAvailabilities
                ->where('day_of_week', $i)
                ->sortBy('start_time')
                ->values();
        }

        return view('company.services.schedule', compact('service', 'company', 'scheduleByDay'));
    }

    /**
     * Save Service Weekly Schedule.
     */
    public function updateServiceSchedule(UpdateWeeklyScheduleRequest $request, int $serviceId): RedirectResponse
    {
        $company = $request->user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        $service = $company->services()->findOrFail($serviceId);
        $days = $request->input('days', []);

        DB::transaction(function () use ($service, $days) {
            // Remove existing weekly availability records for clean replacement
            $service->weeklyAvailabilities()->delete();

            foreach ($days as $day => $windows) {
                if (!is_array($windows)) {
                    continue;
                }

                foreach ($windows as $window) {
                    $start = $window['start_time'] ?? null;
                    $end = $window['end_time'] ?? null;

                    if ($start && $end) {
                        $service->weeklyAvailabilities()->create([
                            'day_of_week' => (int) $day,
                            'start_time' => $start,
                            'end_time' => $end,
                        ]);
                    }
                }
            }
        });

        AuditLog::record(
            'service.schedule.updated',
            "Weekly schedule updated for service '{$service->name}'.",
            $request->user()->id,
            $company->id
        );

        return redirect()
            ->route('company.services.schedule.edit', $service->id)
            ->with('status', "Weekly availability schedule for '{$service->name}' saved successfully.");
    }

    /**
     * Show Employee Weekly Working Availability configuration view.
     */
    public function editEmployeeSchedule(int $employeeId): View
    {
        $company = Auth::user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        $employee = $company->employees()->with('weeklyAvailabilities')->findOrFail($employeeId);

        $scheduleByDay = [];
        for ($i = 1; $i <= 7; $i++) {
            $scheduleByDay[$i] = $employee->weeklyAvailabilities
                ->where('day_of_week', $i)
                ->sortBy('start_time')
                ->values();
        }

        return view('company.employees.schedule', compact('employee', 'company', 'scheduleByDay'));
    }

    /**
     * Save Employee Weekly Working Availability.
     */
    public function updateEmployeeSchedule(UpdateWeeklyScheduleRequest $request, int $employeeId): RedirectResponse
    {
        $company = $request->user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        $employee = $company->employees()->findOrFail($employeeId);
        $days = $request->input('days', []);

        DB::transaction(function () use ($employee, $days) {
            $employee->weeklyAvailabilities()->delete();

            foreach ($days as $day => $windows) {
                if (!is_array($windows)) {
                    continue;
                }

                foreach ($windows as $window) {
                    $start = $window['start_time'] ?? null;
                    $end = $window['end_time'] ?? null;

                    if ($start && $end) {
                        $employee->weeklyAvailabilities()->create([
                            'day_of_week' => (int) $day,
                            'start_time' => $start,
                            'end_time' => $end,
                        ]);
                    }
                }
            }
        });

        AuditLog::record(
            'employee.schedule.updated',
            "Weekly schedule updated for employee '{$employee->name}'.",
            $request->user()->id,
            $company->id
        );

        return redirect()
            ->route('company.employees.schedule.edit', $employee->id)
            ->with('status', "Weekly working schedule for '{$employee->name}' saved successfully.");
    }
}
