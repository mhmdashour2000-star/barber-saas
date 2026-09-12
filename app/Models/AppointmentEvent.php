<?php

namespace App\Models;

use App\Enums\AppointmentEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentEvent extends Model
{
    /**
     * This model uses only created_at (no updated_at) to keep events immutable.
     */
    public $timestamps = false;

    protected $fillable = [
        'appointment_id',
        'company_id',
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

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
