<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUSPENDED = 'suspended';
    protected $attributes = ['accepting_new_bookings' => true];

    protected $fillable = [
        'code',
        'name',
        'manager_id',
        'phone',
        'status',
        'address',
        'map_url',
        'booking_days_ahead',
        'late_cancellation_hours',
        'violation_limit',
        'block_duration_days',
        'whatsapp_enabled',
        'accepting_new_bookings',
        'whatsapp_phone_number',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'whatsapp_enabled' => 'boolean',
            'accepting_new_bookings' => 'boolean',
            'booking_days_ahead' => 'integer',
            'late_cancellation_hours' => 'integer',
            'violation_limit' => 'integer',
            'block_duration_days' => 'integer',
        ];
    }

    protected $hidden = ['whatsapp_phone_number_id', 'whatsapp_business_account_id'];

    public function whatsappConversations(): HasMany
    {
        return $this->hasMany(WhatsappConversation::class);
    }

    public function whatsappOutboundMessages(): HasMany
    {
        return $this->hasMany(WhatsappOutboundMessage::class);
    }

    public function whatsappInboundMessages(): HasMany
    {
        return $this->hasMany(WhatsappInboundMessage::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function availabilityExceptions(): HasMany
    {
        return $this->hasMany(AvailabilityException::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function customerViolations(): HasMany
    {
        return $this->hasMany(CustomerViolation::class);
    }

    public function customerBlocks(): HasMany
    {
        return $this->hasMany(CustomerBlock::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function appointmentEvents(): HasMany
    {
        return $this->hasMany(AppointmentEvent::class);
    }

    /**
     * Generate a unique public company code like SLN-A7K4P.
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = 'SLN-' . strtoupper(Str::random(5));
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
