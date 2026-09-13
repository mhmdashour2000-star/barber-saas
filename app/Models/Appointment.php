<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    use HasFactory;

    /**
     * company_id is intentionally omitted from $fillable to protect against tenant spoofing.
     */
    protected $fillable = [
        'customer_id',
        'service_id',
        'employee_id',
        'booking_code',
        'starts_at',
        'ends_at',
        'status',
        'customer_name_snapshot',
        'customer_phone_snapshot',
        'service_name_snapshot',
        'service_price_minor_units_snapshot',
        'service_duration_minutes_snapshot',
        'employee_name_snapshot',
        'cancelled_at',
        'completed_at',
        'no_show_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
            'no_show_at' => 'datetime',
            'status' => AppointmentStatus::class,
            'service_price_minor_units_snapshot' => 'integer',
            'service_duration_minutes_snapshot' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $appointment): void {
            foreach (array_keys($appointment->getDirty()) as $field) {
                if (str_ends_with($field, '_snapshot') || in_array($field, ['company_id', 'customer_id', 'service_id', 'booking_code'], true)) {
                    throw new \LogicException('Booking identity and snapshots are immutable.');
                }
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AppointmentEvent::class)->orderBy('created_at')->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if this appointment's status blocks the employee time slot.
     */
    public function blocksSchedule(): bool
    {
        return $this->status->blocksSchedule();
    }

    /**
     * Check if the appointment status is terminal (no further transitions allowed).
     */
    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Check if this appointment can be rescheduled.
     * Only confirmed appointments can be rescheduled; the service validates the new time.
     */
    public function canBeRescheduled(): bool
    {
        return $this->status === AppointmentStatus::CONFIRMED;
    }

    /**
     * Formatted snapshot price (e.g. "250,00 ₺").
     */
    public function getFormattedSnapshotPriceAttribute(): string
    {
        return number_format($this->service_price_minor_units_snapshot / 100, 2, ',', '.') . ' ₺';
    }
}
