<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_inbound_messages', function (Blueprint $table) {
            $table->text('transport_payload')->nullable(); // Laravel encrypted:array, never raw webhook JSON.
            $table->timestamp('transport_queued_at')->nullable();
        });
        Schema::create('whatsapp_outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('inbound_message_id')->constrained('whatsapp_inbound_messages')->restrictOnDelete();
            $table->string('provider', 32)->default('meta');
            $table->char('idempotency_key', 64)->unique();
            $table->unsignedInteger('part');
            $table->string('message_type', 20);
            $table->text('payload')->nullable(); // encrypted, erased after acknowledged send/permanent failure.
            $table->string('destination_id', 100);
            $table->string('external_message_id', 191)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('delivery_status', 20)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error_code', 40)->nullable();
            $table->string('last_error_summary', 150)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['inbound_message_id', 'part']);
            $table->index(['company_id', 'external_message_id'], 'wa_outbound_external_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_outbound_messages');
        Schema::table('whatsapp_inbound_messages', fn (Blueprint $table) => $table->dropColumn(['transport_payload', 'transport_queued_at']));
    }
};
