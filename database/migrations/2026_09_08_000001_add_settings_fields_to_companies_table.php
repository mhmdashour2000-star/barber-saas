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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('address', 500)->nullable()->after('phone');
            $table->string('map_url', 2048)->nullable()->after('address');
            $table->unsignedInteger('booking_days_ahead')->default(7)->after('map_url');
            $table->unsignedInteger('late_cancellation_hours')->default(24)->after('booking_days_ahead');
            $table->unsignedInteger('violation_limit')->default(3)->after('late_cancellation_hours');
            $table->unsignedInteger('block_duration_days')->default(7)->after('violation_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'address',
                'map_url',
                'booking_days_ahead',
                'late_cancellation_hours',
                'violation_limit',
                'block_duration_days',
            ]);
        });
    }
};
