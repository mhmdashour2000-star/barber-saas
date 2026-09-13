<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappConversation extends Model
{
    protected $fillable = ['customer_id', 'state', 'context', 'revision', 'last_message_at', 'expires_at'];

    protected function casts(): array
    {
        return ['context' => 'array', 'revision' => 'integer', 'last_message_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
