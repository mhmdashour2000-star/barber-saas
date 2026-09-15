<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployee
{
    public function handle(Request $request, Closure $next): Response
    {
        $employee = Auth::guard('employee')->user()?->fresh();
        if (!$employee || !$employee->active || $employee->company?->status !== Company::STATUS_ACTIVE
            || !hash_equals(hash('sha256', $employee->password), (string) $request->session()->get('employee_password_fingerprint'))) {
            Auth::guard('employee')->logout();
            $request->session()->forget('employee_password_fingerprint');
            return redirect()->route('employee.login');
        }
        // Never switch the default web guard: manager/admin authorization keeps using User.
        Auth::guard('employee')->setUser($employee);
        if ($employee->must_change_password && !$request->routeIs('employee.password', 'employee.password.update')) {
            return redirect()->route('employee.password');
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        return $response;
    }
}
