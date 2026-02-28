<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Update employees table:
 * - Make google_sheet_id nullable (can add later)
 * - Add previous_scan_start_primary_key to track where cursor was before last change
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('google_sheet_id')->nullable()->change();
            $table->integer('previous_scan_start_primary_key')->default(0)
                ->after('scan_start_primary_key');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('google_sheet_id')->nullable(false)->change();
            $table->dropColumn('previous_scan_start_primary_key');
        });
    }
};
