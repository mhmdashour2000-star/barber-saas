<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Authenticatable
{
    use HasFactory;

    // Employee sessions deliberately do not offer persistent remember-me login.
    protected $rememberTokenName = '';

    /**
     * The attributes that are mass assignable.
     * Note: company_id is intentionally omitted to prevent mass-assignment exploits.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'phone',
        'active',
        'password',
        'must_change_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'must_change_password' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * The company that the employee belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The services assigned to this employee.
     */
    public function services(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'employee_services')
            ->withTimestamps();
    }

    /**
     * Weekly working availability windows for this employee.
     */
    public function weeklyAvailabilities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeWeeklyAvailability::class);
    }

    /**
     * Date-specific exceptions for this employee.
     */
    public function availabilityExceptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AvailabilityException::class);
    }
}
