<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\LoginThrottle;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        if (Auth::check()) {
            if (Auth::user()->isSystemAdmin()) {
                return redirect()->route('admin.dashboard');
            }
            return redirect()->route('company.dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        LoginThrottle::ensureAllowed($request);
        $credentials['email'] = \Illuminate\Support\Str::lower(trim($credentials['email']));
        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            LoginThrottle::clear($request);
            $request->session()->regenerate();

            /** @var User $user */
            $user = Auth::user();

            if ($user->isSystemAdmin()) {
                AuditLog::record('admin.login', 'System Admin logged in via main portal.', $user->id);
                return redirect()->intended(route('admin.dashboard'));
            }

            $company = $user->company;
            $request->session()->put('password_hash_web', $user->password);
            AuditLog::record('manager.login', 'Company manager logged in.', $user->id, $company?->id);
            return redirect()->intended(route('company.dashboard'));
        }

        LoginThrottle::failed($request);

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
