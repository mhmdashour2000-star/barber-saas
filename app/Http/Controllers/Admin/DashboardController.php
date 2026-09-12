<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total_companies' => Company::count(),
            'active_companies' => Company::where('status', Company::STATUS_ACTIVE)->count(),
            'pending_companies' => Company::where('status', Company::STATUS_PENDING)->count(),
            'total_managers' => User::where('role', User::ROLE_COMPANY_MANAGER)->count(),
            'total_audit_logs' => AuditLog::count(),
        ];

        $recentCompanies = Company::with('manager')
            ->latest()
            ->take(5)
            ->get();

        $recentAuditLogs = AuditLog::with('user', 'company')
            ->latest()
            ->take(6)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentCompanies', 'recentAuditLogs'));
    }
}
