<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappInboundMessage extends Model
{
    protected $fillable = ['customer_id', 'provider', 'external_message_id', 'fingerprint', 'message_type',
        'status', 'attempts', 'response', 'failure_code', 'received_at', 'processed_at', 'processing_revision', 'transport_payload', 'transport_queued_at'];

    protected $hidden = ['fingerprint', 'transport_payload'];

    protected function casts(): array
    {
        return ['response' => 'array', 'attempts' => 'integer', 'processing_revision' => 'integer', 'received_at' => 'datetime', 'processed_at' => 'datetime',
            'transport_payload' => 'encrypted:array', 'transport_queued_at' => 'datetime'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
