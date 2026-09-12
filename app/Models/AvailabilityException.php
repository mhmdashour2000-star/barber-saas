<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvailabilityException extends Model
{
    use HasFactory;

    public const TYPE_COMPANY = 'company';
    public const TYPE_SERVICE = 'service';
    public const TYPE_EMPLOYEE = 'employee';

    /**
     * Note: company_id is omitted from fillable to preserve tenant integrity.
     */
    protected $fillable = [
        'service_id',
        'employee_id',
        'date',
        'type',
        'is_closed',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'is_closed' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function windows(): HasMany
    {
        return $this->hasMany(AvailabilityExceptionWindow::class);
    }
}
