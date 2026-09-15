<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\AppointmentService;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class DashboardController extends Controller
{
    public function index()
    {
        $employee = Auth::guard('employee')->user();
        $today = Carbon::now(AvailabilityService::TIMEZONE)->startOfDay();
        $appointments = $employee->company->appointments()->where('employee_id', $employee->id)
            ->where('starts_at', '>=', $today->copy()->utc())
            ->where('starts_at', '<', $today->copy()->addDay()->utc())
            ->orderBy('starts_at')->orderBy('id')->get();
        return view('employee.dashboard', compact('employee', 'today', 'appointments'));
    }

    public function complete(Request $request, string $code, AppointmentService $service)
    {
        return $this->transition($request, $code, $service, false);
    }

    public function noShow(Request $request, string $code, AppointmentService $service)
    {
        return $this->transition($request, $code, $service, true);
    }

    private function transition(Request $request, string $code, AppointmentService $service, bool $noShow)
    {
        $employee = Auth::guard('employee')->user();
        try {
            DB::transaction(function () use ($employee, $code, $service, $noShow, $request) {
                // Same employee -> appointment lock order as rescheduling. Check ownership and
                // business day under the lock so reassignment cannot race authorization.
                $employee = $employee->company->employees()->lockForUpdate()->findOrFail($employee->id);
                abort_unless($employee->active && $employee->company->status === Company::STATUS_ACTIVE
                    && !$employee->must_change_password
                    && hash_equals(hash('sha256', $employee->password), (string) $request->session()->get('employee_password_fingerprint')), 403);
                $appointment = $employee->company->appointments()->where('employee_id', $employee->id)
                    ->where('booking_code', $code)->lockForUpdate()->firstOrFail();
                if (!$appointment->starts_at->copy()->timezone(AvailabilityService::TIMEZONE)
                    ->isSameDay(Carbon::now(AvailabilityService::TIMEZONE))) {
                    throw new InvalidArgumentException('Only today’s appointments can be updated.');
                }
                if ($noShow) {
                    $service->markNoShow($appointment, $employee->company, 'employee', $employee->id);
                } else {
                    $service->markCompleted($appointment, $employee->company, 'employee', $employee->id);
                }
            }, 3);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['appointment' => $exception->getMessage()]);
        }
        return redirect()->route('employee.dashboard')->with('status', 'Appointment updated.');
    }
}
