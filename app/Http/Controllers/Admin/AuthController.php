<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\LoginThrottle;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            if (Auth::user()->isSystemAdmin()) {
                return redirect()->route('admin.dashboard');
            }
            return redirect()->route('company.dashboard');
        }

        return view('admin.auth.login');
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

        if (Auth::attempt($credentials + ['role' => User::ROLE_SYSTEM_ADMIN], $remember)) {
            /** @var User $user */
            $user = Auth::user();

            LoginThrottle::clear($request);
            $request->session()->regenerate();
            AuditLog::record('admin.login', 'System Admin logged in to control panel.', $user->id);

            return redirect()->intended(route('admin.dashboard'));
        }

        LoginThrottle::failed($request);

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            AuditLog::record('admin.logout', 'System Admin logged out.', Auth::id());
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('status', 'You have been logged out of the System Admin portal.');
    }
}
