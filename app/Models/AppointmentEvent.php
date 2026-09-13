<?php

namespace App\Models;

use App\Enums\AppointmentEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentEvent extends Model
{
    /**
     * History has only created_at; model hooks below reject normal updates/deletes.
     */
    public $timestamps = false;

    protected $fillable = [
        'appointment_id',
        'type',
        'actor_type',
        'actor_id',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AppointmentEventType::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Appointment events are immutable.');
        });
        static::deleting(function (): void {
            throw new \LogicException('Appointment events cannot be deleted.');
        });
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
