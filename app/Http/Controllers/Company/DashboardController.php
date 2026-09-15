<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $company = $user->company;

        $employeeStats = [
            'total' => $company ? $company->employees()->count() : 0,
            'active' => $company ? $company->employees()->where('active', true)->count() : 0,
            'inactive' => $company ? $company->employees()->where('active', false)->count() : 0,
        ];

        $serviceStats = [
            'total' => $company ? $company->services()->count() : 0,
            'active' => $company ? $company->services()->where('active', true)->count() : 0,
            'inactive' => $company ? $company->services()->where('active', false)->count() : 0,
        ];

        $now = \Carbon\Carbon::now();
        $customerStats = [
            'total' => $company ? $company->customers()->count() : 0,
            'blocked' => $company ? $company->customers()->whereHas('blocks', function ($bq) use ($now) {
                $bq->where('starts_at', '<=', $now)
                   ->whereNull('lifted_at')
                   ->where(function ($sub) use ($now) {
                       $sub->whereNull('ends_at')->orWhere('ends_at', '>', $now);
                   });
            })->count() : 0,
        ];

        $start = \Carbon\Carbon::now(\App\Services\AvailabilityService::TIMEZONE)->startOfDay()->utc();
        $end = $start->copy()->setTimezone(\App\Services\AvailabilityService::TIMEZONE)->addDay()->utc();
        $todayQuery = $company?->appointments()->where('starts_at', '>=', $start)->where('starts_at', '<', $end);
        $appointmentStats = ['Today’s appointments' => $todayQuery ? (clone $todayQuery)->count() : 0];
        foreach (['Completed today' => ['completed'], 'Cancelled today' => ['cancelled_by_customer', 'cancelled_by_company'], 'No-show today' => ['no_show']] as $label => $statuses) {
            $appointmentStats[$label] = $todayQuery ? (clone $todayQuery)->whereIn('status', $statuses)->count() : 0;
        }
        $appointmentStats['Upcoming confirmed'] = $company ? $company->appointments()->where('status', 'confirmed')->where('starts_at', '>=', now())->count() : 0;

        return view('company.dashboard', compact('user', 'company', 'employeeStats', 'serviceStats', 'customerStats', 'appointmentStats'));
    }
}
