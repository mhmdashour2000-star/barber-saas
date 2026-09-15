<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function show()
    {
        return response()->view('employee.login')->header('Cache-Control', 'private, no-store');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'company_code' => ['required', 'string', 'max:50'],
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        $code = Str::upper(trim($data['company_code']));
        $username = trim($data['username']);
        $key = 'employee-login:'.hash('sha256', json_encode([$code, Str::lower($username), $request->ip()]));
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['company_code' => 'Too many login attempts. Please try again in one minute.'])->status(429);
        }
        $company = Company::where('code', $code)->first();
        $employee = $company?->employees()->where('username', $username)->first();
        // A valid dummy bcrypt hash keeps unknown identities on the password-verification path.
        $valid = Hash::check($data['password'], $employee?->password ?: '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (!$valid || !$employee?->active || $company?->status !== Company::STATUS_ACTIVE) {
            RateLimiter::hit($key, 60);
            return back()->withErrors(['company_code' => 'The provided credentials are invalid.'])
                ->onlyInput('company_code', 'username');
        }
        RateLimiter::clear($key);
        // Explicitly switching to employee access cannot retain a manager/admin login.
        Auth::guard('web')->logout();
        Auth::guard('employee')->login($employee);
        $request->session()->regenerate();
        $request->session()->put('employee_password_fingerprint', hash('sha256', $employee->password));
        return redirect()->route($employee->must_change_password ? 'employee.password' : 'employee.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('employee')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('employee.login');
    }

    public function passwordForm()
    {
        return view('employee.password');
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed', 'different:current_password'],
        ]);
        $employee = Auth::guard('employee')->user();
        DB::transaction(function () use ($employee, $data, $request) {
            $locked = $employee->company->employees()->lockForUpdate()->findOrFail($employee->id);
            abort_unless($locked->active && $locked->company->status === Company::STATUS_ACTIVE, 403);
            if (!Hash::check($data['current_password'], $locked->password)) {
                throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
            }
            $locked->update(['password' => $data['password'], 'must_change_password' => false]);
            $request->session()->put('employee_password_fingerprint', hash('sha256', $locked->password));
        });
        $request->session()->regenerate();
        return redirect()->route('employee.dashboard')->with('status', 'Password updated.');
    }
}
