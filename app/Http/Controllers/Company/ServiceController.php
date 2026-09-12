<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreServiceRequest;
use App\Http\Requests\Company\UpdateServiceRequest;
use App\Http\Requests\Company\UpdateServiceStatusRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    /**
     * Display a listing of services for the authenticated company.
     */
    public function index(Request $request): View
    {
        $company = $request->user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        $query = $company->services()->with(['employees'])->latest();

        // Search by Service Name
        if ($search = trim($request->input('search', ''))) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Status Filter: all | active | inactive
        $status = $request->input('status', 'all');
        if ($status === 'active') {
            $query->where('active', true);
        } elseif ($status === 'inactive') {
            $query->where('active', false);
        }

        $services = $query->paginate(15)->withQueryString();

        return view('company.services.index', compact('services', 'company'));
    }

    /**
     * Show the form for creating a new service.
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

        // Only display employees belonging to the authenticated company
        $employees = $company->employees()->orderBy('name')->get();

        return view('company.services.create', compact('company', 'employees'));
    }

    /**
     * Store a newly created service in storage.
     */
    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $company = $request->user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        $validated = $request->validated();

        // Convert human-readable decimal price to minor integer units safely
        $priceMinorUnits = (int) round(((float) $validated['price']) * 100);

        /** @var Service $service */
        $service = DB::transaction(function () use ($company, $validated, $priceMinorUnits) {
            // Create through company relationship to strictly enforce company_id ownership
            $service = $company->services()->create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'price_minor_units' => $priceMinorUnits,
                'duration_minutes' => (int) $validated['duration_minutes'],
                'active' => $validated['active'] ?? true,
            ]);

            // Sync employees if provided (FormRequest already validated they belong to this company)
            if (!empty($validated['employee_ids'])) {
                // Secondary safeguard: filter employee IDs strictly through company
                $validEmployeeIds = $company->employees()
                    ->whereIn('id', $validated['employee_ids'])
                    ->pluck('id')
                    ->all();

                $service->employees()->sync($validEmployeeIds);
            }

            return $service;
        });

        $assignedCount = $service->employees()->count();

        AuditLog::record(
            'service.created',
            "Service '{$service->name}' created ({$service->duration_minutes} min, {$service->formatted_price}, {$assignedCount} employees assigned).",
            $request->user()->id,
            $company->id
        );

        return redirect()
            ->route('company.services.index')
            ->with('status', "Service '{$service->name}' created successfully.");
    }

    /**
     * Show the form for editing the specified service.
     */
    public function edit(int $serviceId): View
    {
        $company = Auth::user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        if ($company->status === Company::STATUS_SUSPENDED) {
            abort(403, 'Your company account is currently suspended.');
        }

        // Strictly tenant-scoped retrieval
        $service = $company->services()->with('employees')->findOrFail($serviceId);
        $employees = $company->employees()->orderBy('name')->get();

        return view('company.services.edit', compact('service', 'company', 'employees'));
    }

    /**
     * Update the specified service in storage.
     */
    public function update(UpdateServiceRequest $request, int $serviceId): RedirectResponse
    {
        $company = $request->user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        // Strictly tenant-scoped lookup
        $service = $company->services()->findOrFail($serviceId);
        $validated = $request->validated();

        $priceMinorUnits = (int) round(((float) $validated['price']) * 100);

        DB::transaction(function () use ($company, $service, $validated, $priceMinorUnits) {
            $service->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'price_minor_units' => $priceMinorUnits,
                'duration_minutes' => (int) $validated['duration_minutes'],
                'active' => $validated['active'] ?? $service->active,
            ]);

            // Sync employees with tenant verification
            $employeeIds = $validated['employee_ids'] ?? [];
            $validEmployeeIds = empty($employeeIds)
                ? []
                : $company->employees()->whereIn('id', $employeeIds)->pluck('id')->all();

            $service->employees()->sync($validEmployeeIds);
        });

        $assignedCount = $service->employees()->count();

        AuditLog::record(
            'service.updated',
            "Service '{$service->name}' updated ({$service->duration_minutes} min, {$service->formatted_price}, {$assignedCount} employees assigned).",
            $request->user()->id,
            $company->id
        );

        return redirect()
            ->route('company.services.index')
            ->with('status', "Service '{$service->name}' updated successfully.");
    }

    /**
     * Update the active status of the specified service.
     */
    public function updateStatus(UpdateServiceStatusRequest $request, int $serviceId): RedirectResponse
    {
        $company = $request->user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        // Strictly tenant-scoped lookup
        $service = $company->services()->findOrFail($serviceId);
        $active = $request->boolean('active');

        $service->update([
            'active' => $active,
        ]);

        $action = $active ? 'service.activated' : 'service.deactivated';
        $description = $active
            ? "Service '{$service->name}' activated."
            : "Service '{$service->name}' deactivated.";

        AuditLog::record(
            $action,
            $description,
            $request->user()->id,
            $company->id
        );

        $statusMsg = $active ? "Service '{$service->name}' activated successfully." : "Service '{$service->name}' deactivated successfully.";

        return redirect()
            ->route('company.services.index')
            ->with('status', $statusMsg);
    }
}
