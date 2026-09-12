<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityExceptionWindow extends Model
{
    use HasFactory;

    protected $fillable = [
        'availability_exception_id',
        'start_time',
        'end_time',
    ];

    public function exception(): BelongsTo
    {
        return $this->belongsTo(AvailabilityException::class, 'availability_exception_id');
    }
}
