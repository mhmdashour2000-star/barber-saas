<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'action',
        'description',
        'ip_address',
        'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Helper to log an explicit action.
     */
    public static function record(string $action, ?string $description = null, ?int $userId = null, ?int $companyId = null): self
    {
        return static::create([
            'user_id' => $userId ?? auth()->id(),
            'company_id' => $companyId,
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Alias for record().
     */
    public static function log(string $action, ?string $description = null, ?int $userId = null, ?int $companyId = null): self
    {
        return static::record($action, $description, $userId, $companyId);
    }
}
