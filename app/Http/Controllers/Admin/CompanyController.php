<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $query = Company::with('manager');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhereHas('manager', function ($mq) use ($search) {
                      $mq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $companies = $query->latest()->paginate(10)->withQueryString();

        return view('admin.companies.index', compact('companies'));
    }

    public function show(Company $company): View
    {
        $company->load(['manager', 'auditLogs' => function ($q) {
            $q->latest()->take(10);
        }, 'auditLogs.user']);

        return view('admin.companies.show', compact('company'));
    }

    /**
     * Update the status of a company (active, pending, suspended).
     */
    public function updateStatus(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in([Company::STATUS_ACTIVE, Company::STATUS_PENDING, Company::STATUS_SUSPENDED]),
            ],
        ]);

        $oldStatus = $company->status;
        $newStatus = $validated['status'];

        $company->update([
            'status' => $newStatus,
        ]);

        AuditLog::record(
            'company.status.updated',
            "Company {$company->code} status changed from {$oldStatus} to {$newStatus}.",
            $request->user()->id,
            $company->id
        );

        return back()->with('status', "Company '{$company->name}' status updated to " . ucfirst($newStatus) . '.');
    }
}
