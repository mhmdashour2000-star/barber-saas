<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Company\DashboardController as CompanyDashboardController;
use App\Http\Controllers\Company\EmployeeController;
use App\Http\Controllers\Company\AppointmentController;
use App\Http\Controllers\Company\SettingsController as CompanySettingsController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

// Stateless provider transport only. CSRF protection remains enabled for every management route.
Route::withoutMiddleware([
    \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
])->group(function () {
    Route::get('/webhooks/whatsapp/meta', [\App\Http\Controllers\Webhooks\MetaWhatsappController::class, 'verify'])->name('webhooks.whatsapp.meta.verify');
    Route::post('/webhooks/whatsapp/meta', [\App\Http\Controllers\Webhooks\MetaWhatsappController::class, 'receive'])->name('webhooks.whatsapp.meta.receive');
});

/*
|--------------------------------------------------------------------------
| Public SaaS Website & Authentication Routes
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::view('/privacy-policy', 'public.privacy-policy')->name('privacy-policy');

Route::prefix('employee')->name('employee.')->group(function () {
    Route::get('/login', [\App\Http\Controllers\Employee\AuthController::class, 'show'])->name('login');
    Route::post('/login', [\App\Http\Controllers\Employee\AuthController::class, 'login']);
    Route::post('/logout', [\App\Http\Controllers\Employee\AuthController::class, 'logout'])->name('logout');
    Route::middleware('employee')->group(function () {
        Route::get('/', [\App\Http\Controllers\Employee\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/password', [\App\Http\Controllers\Employee\AuthController::class, 'passwordForm'])->name('password');
        Route::put('/password', [\App\Http\Controllers\Employee\AuthController::class, 'password'])->name('password.update');
        Route::post('/appointments/{code}/complete', [\App\Http\Controllers\Employee\DashboardController::class, 'complete'])->name('appointments.complete');
        Route::post('/appointments/{code}/no-show', [\App\Http\Controllers\Employee\DashboardController::class, 'noShow'])->name('appointments.no-show');
    });
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

/*
|--------------------------------------------------------------------------
| Company Manager Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['company'])->prefix('company')->name('company.')->group(function () {
    // Appointment management deliberately has no creation, editing, or deletion routes.
    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])->whereNumber('appointment')->name('appointments.show');
    Route::patch('/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])->whereNumber('appointment')->name('appointments.reschedule');
    Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->whereNumber('appointment')->name('appointments.cancel');
    Route::post('/appointments/{appointment}/complete', [AppointmentController::class, 'complete'])->whereNumber('appointment')->name('appointments.complete');
    Route::post('/appointments/{appointment}/no-show', [AppointmentController::class, 'noShow'])->whereNumber('appointment')->name('appointments.no-show');

    Route::get('/dashboard', [CompanyDashboardController::class, 'index'])->name('dashboard');
    Route::get('/settings', [CompanySettingsController::class, 'edit'])->name('settings.edit');
    Route::match(['put', 'patch'], '/settings', [CompanySettingsController::class, 'update'])->name('settings.update');

    // Employee Management
    Route::put('/employees/{employee}/password', [EmployeeController::class, 'resetPassword'])->whereNumber('employee')->name('employees.password');
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::match(['put', 'patch'], '/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::patch('/employees/{employee}/status', [EmployeeController::class, 'updateStatus'])->name('employees.status');
    Route::get('/employees/{employee}/availability', [\App\Http\Controllers\Company\AvailabilityController::class, 'editEmployeeSchedule'])->name('employees.schedule.edit');
    Route::match(['put', 'patch'], '/employees/{employee}/availability', [\App\Http\Controllers\Company\AvailabilityController::class, 'updateEmployeeSchedule'])->name('employees.schedule.update');

    // Service Management
    Route::get('/services', [\App\Http\Controllers\Company\ServiceController::class, 'index'])->name('services.index');
    Route::get('/services/create', [\App\Http\Controllers\Company\ServiceController::class, 'create'])->name('services.create');
    Route::post('/services', [\App\Http\Controllers\Company\ServiceController::class, 'store'])->name('services.store');
    Route::get('/services/{service}/edit', [\App\Http\Controllers\Company\ServiceController::class, 'edit'])->name('services.edit');
    Route::match(['put', 'patch'], '/services/{service}', [\App\Http\Controllers\Company\ServiceController::class, 'update'])->name('services.update');
    Route::patch('/services/{service}/status', [\App\Http\Controllers\Company\ServiceController::class, 'updateStatus'])->name('services.status');
    Route::get('/services/{service}/availability', [\App\Http\Controllers\Company\AvailabilityController::class, 'editServiceSchedule'])->name('services.schedule.edit');
    Route::match(['put', 'patch'], '/services/{service}/availability', [\App\Http\Controllers\Company\AvailabilityController::class, 'updateServiceSchedule'])->name('services.schedule.update');

    // Salon Availability Overview & Date Exceptions
    Route::get('/availability', [\App\Http\Controllers\Company\AvailabilityController::class, 'index'])->name('availability.index');
    Route::post('/availability/exceptions', [\App\Http\Controllers\Company\AvailabilityController::class, 'storeException'])->name('availability.exceptions.store');
    Route::delete('/availability/exceptions/{exception}', [\App\Http\Controllers\Company\AvailabilityController::class, 'destroyException'])->name('availability.exceptions.destroy');

    // Customer Management & Restrictions
    Route::get('/customers', [\App\Http\Controllers\Company\CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{customer}', [\App\Http\Controllers\Company\CustomerController::class, 'show'])->name('customers.show');
    Route::match(['put', 'patch'], '/customers/{customer}', [\App\Http\Controllers\Company\CustomerController::class, 'update'])->name('customers.update');
    Route::post('/customers/{customer}/block', [\App\Http\Controllers\Company\CustomerController::class, 'block'])->name('customers.block');
    Route::post('/customers/{customer}/unblock', [\App\Http\Controllers\Company\CustomerController::class, 'unblock'])->name('customers.unblock');
});

/*
|--------------------------------------------------------------------------
| System Admin Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout')->middleware('auth');

    Route::middleware(['admin'])->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/companies', [AdminCompanyController::class, 'index'])->name('companies.index');
        Route::get('/companies/{company}', [AdminCompanyController::class, 'show'])->name('companies.show');
        Route::patch('/companies/{company}/status', [AdminCompanyController::class, 'updateStatus'])->name('companies.status');
    });
});
