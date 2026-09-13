<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappOutboundMessage extends Model
{
    protected $fillable = ['customer_id', 'inbound_message_id', 'provider', 'idempotency_key', 'part', 'message_type', 'payload',
        'destination_id', 'external_message_id', 'status', 'delivery_status', 'attempts', 'last_error_code', 'last_error_summary', 'claimed_at', 'next_attempt_at', 'sent_at'];
    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'attempts' => 'integer', 'part' => 'integer', 'claimed_at' => 'datetime', 'next_attempt_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}
