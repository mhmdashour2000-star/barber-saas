<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     * Note: company_id is intentionally omitted to protect against tenant spoofing.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'price_minor_units',
        'duration_minutes',
        'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_minor_units' => 'integer',
            'duration_minutes' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * The company that owns the service.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The employees assigned to perform this service.
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_services')
            ->withTimestamps();
    }

    /**
     * Weekly availability windows for this service.
     */
    public function weeklyAvailabilities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ServiceWeeklyAvailability::class);
    }

    /**
     * Date exceptions for this service.
     */
    public function availabilityExceptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AvailabilityException::class);
    }

    /**
     * Human-readable decimal price (e.g. 250.50).
     */
    public function getPriceDecimalAttribute(): float
    {
        return round($this->price_minor_units / 100, 2);
    }

    /**
     * Formatted price string with TRY currency symbol (e.g. 250,50 ₺).
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price_minor_units / 100, 2, ',', '.') . ' ₺';
    }
}
