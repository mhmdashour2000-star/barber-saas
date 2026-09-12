<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function showRegistrationForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            if (Auth::user()->isSystemAdmin()) {
                return redirect()->route('admin.dashboard');
            }
            return redirect()->route('company.dashboard');
        }

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'manager_name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($validated, $request) {
            $user = User::create([
                'name' => $validated['manager_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => User::ROLE_COMPANY_MANAGER,
            ]);

            $company = Company::create([
                'code' => Company::generateUniqueCode(),
                'name' => $validated['company_name'],
                'manager_id' => $user->id,
                'phone' => $validated['phone'],
                'status' => Company::STATUS_PENDING,
            ]);

            AuditLog::record(
                'company.registered',
                "Company '{$company->name}' registered with manager '{$user->name}'. Status: pending.",
                $user->id,
                $company->id
            );

            return $user;
        });

        Auth::login($user);

        return redirect()->route('company.dashboard')->with('status', 'Welcome! Your salon account has been created successfully and is pending review.');
    }
}
