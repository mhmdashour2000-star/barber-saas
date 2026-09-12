<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\UpdateCompanySettingsRequest;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    /**
     * Show the company settings form.
     */
    public function edit(): View
    {
        $user = Auth::user();
        $company = $user->company;

        return view('company.settings', compact('user', 'company'));
    }

    /**
     * Update the company settings.
     */
    public function update(UpdateCompanySettingsRequest $request): RedirectResponse
    {
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            abort(404, 'Company not found.');
        }

        // Strictly use only validated fields from the request
        $validated = $request->validated();

        $company->update($validated);

        AuditLog::record(
            'company.settings.updated',
            "Company settings updated for '{$company->name}'.",
            $user->id,
            $company->id
        );

        return redirect()
            ->route('company.settings.edit')
            ->with('status', 'Company settings updated successfully.');
    }
}
