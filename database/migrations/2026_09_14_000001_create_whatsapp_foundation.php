<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('whatsapp_enabled')->default(false);
            $table->string('whatsapp_phone_number', 30)->nullable()->unique();
            $table->string('whatsapp_phone_number_id', 100)->nullable()->unique();
            $table->string('whatsapp_business_account_id', 100)->nullable();
        });
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('state', 40)->default('start');
            $table->json('context')->nullable();
            $table->unsignedBigInteger('revision')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['company_id', 'customer_id'], 'whatsapp_conversation_customer_unique');
        });
        Schema::create('whatsapp_inbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('provider', 32);
            $external = $table->string('external_message_id', 191);
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $external->collation('utf8mb4_bin');
            }
            $table->char('fingerprint', 64);
            $table->string('message_type', 20);
            $table->string('status', 20)->default('received')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedBigInteger('processing_revision')->nullable();
            $table->json('response')->nullable();
            $table->string('failure_code', 40)->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'provider', 'external_message_id'], 'whatsapp_message_external_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_inbound_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['whatsapp_phone_number']);
            $table->dropUnique(['whatsapp_phone_number_id']);
            $table->dropColumn(['whatsapp_enabled', 'whatsapp_phone_number', 'whatsapp_phone_number_id', 'whatsapp_business_account_id']);
        });
    }
};
