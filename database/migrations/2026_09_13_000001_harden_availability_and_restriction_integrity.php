<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preflight before any DDL: never discard historical exceptions or their windows.
        $target = "CASE WHEN type = 'company' THEN 0 WHEN type = 'service' THEN service_id WHEN type = 'employee' THEN employee_id END";
        $duplicates = DB::table('availability_exceptions')->selectRaw("company_id, type, date, $target AS effective_target, COUNT(*) AS total")
            ->groupByRaw("company_id, type, date, $target")->havingRaw('COUNT(*) > 1')->exists();
        $invalid = DB::table('availability_exceptions')->whereRaw("($target) IS NULL")->exists();
        if ($duplicates || $invalid) {
            throw new RuntimeException('Availability exceptions contain duplicate or invalid targets. Reconcile records and preserve their windows/reasons before retrying; no schema changes were made.');
        }

        Schema::table('availability_exceptions', function (Blueprint $table) {
            // MySQL 5.7 forbids generated expressions over cascading FK columns.
            // Company exceptions need a non-NULL date key; other targets use their ordinary FK.
            $table->date('company_exception_date')->nullable()
                ->virtualAs("CASE WHEN type = 'company' THEN date ELSE NULL END");
            $table->unique(['company_id', 'company_exception_date'], 'availability_company_date_unique');
            $table->unique(['company_id', 'type', 'service_id', 'date'], 'availability_service_date_unique');
            $table->unique(['company_id', 'type', 'employee_id', 'date'], 'availability_employee_date_unique');
        });
        Schema::table('customer_blocks', function (Blueprint $table) {
            // An ID watermark makes fresh cycles correct even within the same timestamp second.
            $table->unsignedBigInteger('violation_cursor_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_blocks', fn (Blueprint $table) => $table->dropColumn('violation_cursor_id'));
        Schema::table('availability_exceptions', function (Blueprint $table) {
            $table->dropUnique('availability_company_date_unique');
            $table->dropUnique('availability_service_date_unique');
            $table->dropUnique('availability_employee_date_unique');
            $table->dropColumn('company_exception_date');
        });
    }
};
