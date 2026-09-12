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
        // 1. Service Weekly Availability
        Schema::create('service_weekly_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 1 = Monday ... 7 = Sunday (ISO-8601)
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['service_id', 'day_of_week']);
        });

        // 2. Employee Weekly Availability
        Schema::create('employee_weekly_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 1 = Monday ... 7 = Sunday (ISO-8601)
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['employee_id', 'day_of_week']);
        });

        // 3. Availability Exceptions (Salon, Service, or Employee date-specific closure/custom hours)
        Schema::create('availability_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->string('type'); // 'company', 'service', 'employee'
            $table->boolean('is_closed')->default(true);
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'date']);
            $table->index(['service_id', 'date']);
            $table->index(['employee_id', 'date']);
        });

        // 4. Availability Exception Windows (supports multiple custom intervals per date exception)
        Schema::create('availability_exception_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('availability_exception_id')->constrained('availability_exceptions')->cascadeOnDelete();
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index('availability_exception_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('availability_exception_windows');
        Schema::dropIfExists('availability_exceptions');
        Schema::dropIfExists('employee_weekly_availabilities');
        Schema::dropIfExists('service_weekly_availabilities');
    }
};
