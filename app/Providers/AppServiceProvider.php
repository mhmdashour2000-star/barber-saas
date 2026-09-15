<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\RateLimiter::for('registration', fn (\Illuminate\Http\Request $request) => [
            \Illuminate\Cache\RateLimiting\Limit::perHour(10)->by('register-ip:'.$request->ip()),
            \Illuminate\Cache\RateLimiting\Limit::perHour(5)->by('register-email:'.$this->identityKey($request)),
        ]);
        \Illuminate\Support\Facades\RateLimiter::for('manager-recovery', fn (\Illuminate\Http\Request $request) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by('recovery-ip:'.$request->ip()),
            \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by('recovery-email:'.$this->identityKey($request)),
        ]);
    }

    private function identityKey(\Illuminate\Http\Request $request): string
    {
        $email = $request->input('email');
        return hash('sha256', \Illuminate\Support\Str::lower(trim(is_string($email) ? $email : '')));
    }
}
