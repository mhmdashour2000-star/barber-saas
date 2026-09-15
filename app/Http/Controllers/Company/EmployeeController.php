<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreEmployeeRequest;
use App\Http\Requests\Company\UpdateEmployeeRequest;
use App\Http\Requests\Company\UpdateEmployeeStatusRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeeController extends Controller
{
    public function resetPassword(\App\Http\Requests\Company\ResetEmployeePasswordRequest $request, int $employeeId): RedirectResponse
    {
        $company = $request->user()->company;
        $employee = $company->employees()->findOrFail($employeeId);
        $password = $request->validated('password');
        $employee->update(['password' => $password, 'must_change_password' => true]);
        AuditLog::record('employee.password_reset', 'Employee login password reset.', $request->user()->id, $company->id);
        return redirect()->route('company.employees.index')->with('status', 'Employee password reset successfully.')
            ->with('new_employee_credentials', \App\Support\TemporaryEmployeeCredentials::encrypt($request, [
                'name' => $employee->name, 'company_code' => $company->code,
                'username' => $employee->username, 'password' => $password,
            ]));
    }

    /**
     * Display a listing of employees for the authenticated company.
     */
    public function index(Request $request): \Illuminate\Http\Response
    {
        $company = $request->user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        $query = $company->employees()->latest();

        // Search by Name or Username
        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        // Status Filter: all | active | inactive
        $status = $request->input('status', 'all');
        if ($status === 'active') {
            $query->where('active', true);
        } elseif ($status === 'inactive') {
            $query->where('active', false);
        }

        $employees = $query->paginate(15)->withQueryString();

        $creds = \App\Support\TemporaryEmployeeCredentials::consume($request);

        return response()->view('company.employees.index', compact('employees', 'company', 'creds'))
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    /**
     * Show the form for creating a new employee.
     */
    public function create(): View
    {
        $company = Auth::user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        if ($company->status === Company::STATUS_SUSPENDED) {
            abort(403, 'Your company account is currently suspended.');
        }

        return view('company.employees.create', compact('company'));
    }

    /**
     * Store a newly created employee in the authenticated company.
     */
    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $company = $request->user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        $validated = $request->validated();

        // Create through company relationship to automatically assign company_id securely
        $employee = $company->employees()->create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'], // Model cast hashes password automatically
            'active' => true,
            'must_change_password' => true,
        ]);

        AuditLog::record(
            'employee.created',
            "Employee '{$employee->name}' ({$employee->username}) created.",
            $request->user()->id,
            $company->id
        );

        return redirect()
            ->route('company.employees.index')
            ->with('status', 'Employee created successfully.')
            ->with('new_employee_credentials', \App\Support\TemporaryEmployeeCredentials::encrypt($request, [
                'name' => $employee->name,
                'company_code' => $company->code,
                'username' => $employee->username,
                'password' => $validated['password'],
            ]));
    }

    /**
     * Show the form for editing the specified employee.
     */
    public function edit(int $employeeId): View
    {
        $company = Auth::user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        if ($company->status === Company::STATUS_SUSPENDED) {
            abort(403, 'Your company account is currently suspended.');
        }

        $employee = $company->employees()->findOrFail($employeeId);

        return view('company.employees.edit', compact('employee', 'company'));
    }

    /**
     * Update the specified employee in storage.
     */
    public function update(UpdateEmployeeRequest $request, int $employeeId): RedirectResponse
    {
        $company = $request->user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        $employee = $company->employees()->findOrFail($employeeId);
        $validated = $request->validated();

        // Standard edit explicitly updates only name, username, and phone
        $employee->update([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'phone' => $validated['phone'] ?? null,
        ]);

        AuditLog::record(
            'employee.updated',
            "Employee '{$employee->name}' ({$employee->username}) updated.",
            $request->user()->id,
            $company->id
        );

        return redirect()
            ->route('company.employees.index')
            ->with('status', 'Employee updated successfully.');
    }

    /**
     * Update the active status of the specified employee.
     */
    public function updateStatus(UpdateEmployeeStatusRequest $request, int $employeeId): RedirectResponse
    {
        $company = $request->user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        $employee = $company->employees()->findOrFail($employeeId);
        $active = $request->boolean('active');

        $employee->update([
            'active' => $active,
        ]);

        $action = $active ? 'employee.activated' : 'employee.deactivated';
        $description = $active
            ? "Employee '{$employee->name}' ({$employee->username}) activated."
            : "Employee '{$employee->name}' ({$employee->username}) deactivated.";

        AuditLog::record(
            $action,
            $description,
            $request->user()->id,
            $company->id
        );

        $statusMsg = $active ? 'Employee activated successfully.' : 'Employee deactivated successfully.';

        return redirect()
            ->route('company.employees.index')
            ->with('status', $statusMsg);
    }
}
