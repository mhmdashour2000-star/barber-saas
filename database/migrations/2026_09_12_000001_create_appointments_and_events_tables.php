<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('employee_id');

            $table->string('booking_code', 12)->unique();

            // All appointment instants are stored as UTC, at second precision.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->string('status', 30)->default('confirmed');

            // Historical snapshots — immutable after creation
            $table->string('customer_name_snapshot');
            $table->string('customer_phone_snapshot');
            $table->string('service_name_snapshot');
            $table->unsignedInteger('service_price_minor_units_snapshot');
            $table->unsignedInteger('service_duration_minutes_snapshot');
            $table->string('employee_name_snapshot');

            // Terminal status timestamps
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('no_show_at')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('restrict');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('restrict');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('restrict');

            // Composite indexes for efficient queries
            $table->index(['company_id', 'starts_at']);
            $table->index(['company_id', 'employee_id', 'status', 'starts_at'], 'appointments_slot_lookup');
            $table->index(['employee_id', 'ends_at']);
            $table->index(['customer_id', 'starts_at']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('appointment_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('appointment_id');
            $table->unsignedBigInteger('company_id');

            $table->string('type', 40);

            $table->string('actor_type', 30)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();

            $table->json('metadata')->nullable();

            $table->dateTime('created_at'); // Explicit UTC instant supplied by the service.

            // Foreign keys
            $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('restrict');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('restrict');

            // Indexes
            $table->index('appointment_id');
            $table->index('company_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_events');
        Schema::dropIfExists('appointments');
    }
};
