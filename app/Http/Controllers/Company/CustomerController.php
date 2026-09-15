<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\BlockCustomerRequest;
use App\Http\Requests\Company\UpdateCustomerNotesRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerViolation;
use App\Services\CustomerRestrictionService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerRestrictionService $restrictionService
    ) {}

    /**
     * Display listing of salon customers with search and filters.
     */
    public function index(Request $request): View
    {
        $company = $request->user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        $query = $company->customers()->with(['blocks', 'violations'])->latest();

        // Search by name or phone
        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter: all | active | blocked | not_blocked
        $filter = $request->input('status', 'all');
        $now = Carbon::now();

        if ($filter === 'active') {
            $query->where('active', true);
        } elseif ($filter === 'blocked') {
            $query->whereHas('blocks', function ($bq) use ($now) {
                $bq->where('starts_at', '<=', $now)
                   ->whereNull('lifted_at')
                   ->where(function ($sub) use ($now) {
                       $sub->whereNull('ends_at')->orWhere('ends_at', '>', $now);
                   });
            });
        } elseif ($filter === 'not_blocked') {
            $query->whereDoesntHave('blocks', function ($bq) use ($now) {
                $bq->where('starts_at', '<=', $now)
                   ->whereNull('lifted_at')
                   ->where(function ($sub) use ($now) {
                       $sub->whereNull('ends_at')->orWhere('ends_at', '>', $now);
                   });
            });
        }

        $customers = $query->paginate(15)->withQueryString();

        return view('company.customers.index', compact('customers', 'company'));
    }

    /**
     * Display the specified customer details, violation history, and block logs.
     */
    public function show(int $customerId): View
    {
        $company = Auth::user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        /** @var Customer $customer */
        $customer = $company->customers()
            ->with([
                'violations' => fn ($q) => $q->latest('occurred_at'),
                'violations.createdBy',
                'blocks' => fn ($q) => $q->latest('starts_at'),
                'blocks.createdBy',
                'blocks.liftedBy',
            ])
            ->findOrFail($customerId);

        $activeBlock = $customer->activeBlock();
        $qualifyingViolationsCount = $this->restrictionService->getActiveViolationCount($customer);
        $appointments = $company->appointments()->where('customer_id', $customer->id)
            ->with(['employee' => fn ($query) => $query->where('company_id', $company->id)])
            ->orderByDesc('starts_at')->orderByDesc('id')->paginate(10, ['*'], 'appointments_page');

        return view('company.customers.show', compact('customer', 'company', 'activeBlock', 'qualifyingViolationsCount', 'appointments'));
    }

    /**
     * Manually block a customer.
     */
    public function block(BlockCustomerRequest $request, int $customerId): RedirectResponse
    {
        $company = $request->user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        $customer = $company->customers()->findOrFail($customerId);
        $validated = $request->validated();

        $this->restrictionService->manualBlock(
            $customer,
            $validated['reason'],
            $validated['duration_days'] ?? $company->block_duration_days,
            $request->user()->id
        );

        return redirect()
            ->route('company.customers.show', $customer->id)
            ->with('status', "Customer '{$customer->name}' has been blocked.");
    }

    /**
     * Manually unblock an active customer.
     */
    public function unblock(Request $request, int $customerId): RedirectResponse
    {
        $company = $request->user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        if ($company->status === Company::STATUS_SUSPENDED) {
            abort(403, 'Your company account is currently suspended.');
        }

        $customer = $company->customers()->findOrFail($customerId);

        $unblocked = $this->restrictionService->manualUnblock($customer, $request->user()->id);

        $msg = $unblocked
            ? "Customer '{$customer->name}' block has been lifted."
            : "Customer '{$customer->name}' does not have an active block.";

        return redirect()
            ->route('company.customers.show', $customer->id)
            ->with('status', $msg);
    }

    /**
     * Update customer internal notes or active status.
     */
    public function update(UpdateCustomerNotesRequest $request, int $customerId): RedirectResponse
    {
        $company = $request->user()->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        $customer = $company->customers()->findOrFail($customerId);
        $validated = $request->validated();

        $customer->update([
            'notes' => $validated['notes'] ?? null,
            'active' => $request->has('active') ? $request->boolean('active') : $customer->active,
        ]);

        AuditLog::record(
            'customer.updated',
            "Customer details updated for '{$customer->name}'.",
            $request->user()->id,
            $company->id
        );

        return redirect()
            ->route('company.customers.show', $customer->id)
            ->with('status', 'Customer information updated successfully.');
    }
}
