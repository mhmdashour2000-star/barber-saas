<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\{AuditLog, User};
use App\Notifications\ManagerResetPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Hash, Log, Password};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManagerPasswordController extends Controller
{
    public function edit() { return response()->view('company.password')->header('Cache-Control', 'private, no-store'); }
    public function forgot() { return view('auth.manager-forgot-password'); }

    public function change(Request $request)
    {
        $data = $request->validate(['current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed', 'different:current_password']]);
        $user = DB::transaction(function () use ($request, $data) {
            $user = User::whereKey($request->user()->id)->where('role', User::ROLE_COMPANY_MANAGER)->lockForUpdate()->firstOrFail();
            if (!Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
            }
            $user->forceFill(['password' => Hash::make($data['password']), 'remember_token' => Str::random(60)])->save();
            Password::broker('managers')->deleteToken($user);
            $this->revokeDatabaseSessions($user, $request->session()->getId());
            AuditLog::record('manager.password_changed', 'Manager password changed.', $user->id, $user->company?->id);
            return $user;
        });
        Auth::guard('web')->setUser($user);
        $request->session()->put('password_hash_web', $user->password);
        $request->session()->regenerate();
        return redirect()->route('company.settings.edit')->with('status', 'Password changed. Other manager sessions will require login again.');
    }

    public function email(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        try {
            Password::broker('managers')->sendResetLink([
                'email' => Str::lower(trim($data['email'])), 'role' => User::ROLE_COMPANY_MANAGER,
            ], function ($user, $token) {
                if (app()->isProduction() && in_array(config('mail.default'), ['log', 'array'], true)) {
                    Log::warning('Manager recovery requires a production email transport.');
                    return;
                }
                $user->notify(new ManagerResetPassword($token));
            });
        } catch (\Throwable) {
            // Mail exceptions may include credentials or reset URLs; never log their body.
            Log::warning('Manager recovery delivery unavailable.');
        }
        return back()->with('status', 'If a matching manager account exists, a password reset link will be sent.');
    }

    public function resetForm(Request $request, string $token)
    {
        return response()->view('auth.manager-reset-password', ['token' => $token,
            'email' => is_string($request->query('email')) ? $request->query('email') : ''])
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function reset(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed']]);
        $data['email'] = Str::lower(trim($data['email']));
        $data['role'] = User::ROLE_COMPANY_MANAGER;
        $status = DB::transaction(function () use ($data) {
            // Serialize token consumption for simultaneous reset requests for the same user.
            $user = User::where('email', $data['email'])->where('role', User::ROLE_COMPANY_MANAGER)->lockForUpdate()->first();
            if (!$user) return Password::INVALID_TOKEN;
            return Password::broker('managers')->reset($data, function ($user, $password) {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                $this->revokeDatabaseSessions($user);
                AuditLog::record('manager.password_reset', 'Manager password recovered.', $user->id, $user->company?->id);
                event(new \Illuminate\Auth\Events\PasswordReset($user));
            });
        });
        if ($status !== Password::PASSWORD_RESET) {
            // Do not flash token or password input into a persistent session.
            return back()->withErrors(['email' => 'The reset link is invalid or expired. Please request another link.']);
        }
        return redirect()->route('login')->with('status', 'Password reset. Please log in with your new password.');
    }

    private function revokeDatabaseSessions(User $user, ?string $except = null): void
    {
        // Also revoke sessions issued before auth.session fingerprints were introduced.
        if (config('session.driver') !== 'database') return;
        DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)->when($except, fn ($query) => $query->where('id', '!=', $except))->delete();
    }
}
