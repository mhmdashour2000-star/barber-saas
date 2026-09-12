<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerViolation extends Model
{
    use HasFactory;

    public const TYPE_LATE_CANCELLATION = 'late_cancellation';
    public const TYPE_NO_SHOW = 'no_show';
    public const TYPE_MANUAL = 'manual';

    protected $fillable = [
        'customer_id',
        'type',
        'reason',
        'appointment_id',
        'occurred_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
