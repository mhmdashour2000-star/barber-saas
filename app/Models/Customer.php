<?php

namespace App\Models;

use App\Support\PhoneHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    /**
     * Note: company_id is intentionally omitted from $fillable to protect against tenant spoofing.
     */
    protected $fillable = [
        'name',
        'phone',
        'verified_at',
        'active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    /**
     * Automatically normalize phone number before saving.
     */
    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = PhoneHelper::normalize($value);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function violations(): HasMany
    {
        return $this->hasMany(CustomerViolation::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(CustomerBlock::class);
    }

    /**
     * Retrieve the currently active block for this customer, if any.
     * Active means: starts_at <= now, lifted_at IS NULL, and (ends_at IS NULL or ends_at > now).
     */
    public function activeBlock(): ?CustomerBlock
    {
        $now = Carbon::now();

        return $this->blocks()
            ->where('starts_at', '<=', $now)
            ->whereNull('lifted_at')
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', $now);
            })
            ->latest('starts_at')
            ->first();
    }

    /**
     * Check if customer is currently blocked.
     */
    public function isBlocked(): bool
    {
        return $this->activeBlock() !== null;
    }

    /**
     * Basic booking eligibility check.
     * Customer can book if active and not blocked.
     */
    public function canBook(): bool
    {
        return $this->active && !$this->isBlocked();
    }
}
