<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerBlock extends Model
{
    use HasFactory;

    public const SOURCE_AUTOMATIC = 'automatic';
    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'customer_id',
        'reason',
        'source',
        'starts_at',
        'ends_at',
        'lifted_at',
        'lifted_by_user_id',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'lifted_at' => 'datetime',
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

    public function liftedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by_user_id');
    }

    /**
     * Compute block status: 'ACTIVE', 'LIFTED', or 'EXPIRED'.
     */
    public function getStatusAttribute(): string
    {
        if ($this->lifted_at !== null) {
            return 'LIFTED';
        }

        $now = Carbon::now();

        if ($this->starts_at > $now) {
            return 'PENDING';
        }

        if ($this->ends_at !== null && $this->ends_at <= $now) {
            return 'EXPIRED';
        }

        return 'ACTIVE';
    }
}
