<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the employees table.
 * Each employee has their own Google Sheet for time-tracking input.
 * Primary key cursors track where each billing scan should start.
 */
return new class extends Migration
{
    /**
     * Run the migration to create the employees table.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            // Auto-incrementing primary key
            $table->id();
            // Employee display name
            $table->string('name');
            // Google Sheet ID extracted from the sheet URL
            // Validated on save to confirm the service account has read/write access
            $table->string('google_sheet_id');
            // The primary_key value in the sheet to begin scanning from on the NEXT billing run
            // Updated after each successful billing cycle
            $table->integer('scan_start_primary_key')->default(0);
            // The primary_key value where the LAST completed billing round ended
            // Used for reference and rollback capability
            $table->integer('last_billing_primary_key')->default(0);
            // Whether employee is active — inactive employees are skipped in billing scans
            $table->boolean('is_active')->default(true);
            // Laravel timestamps (created_at, updated_at)
            $table->timestamps();
            // Soft delete support — records are flagged, not removed from DB
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migration — drop the employees table.
     *
     * @return void
     */
    public function down(): void
    {
        // Remove the employees table entirely
        Schema::dropIfExists('employees');
    }
};
