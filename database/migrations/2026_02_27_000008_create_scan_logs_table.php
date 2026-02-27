<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the scan_logs table.
 * Records each billing scan run per employee.
 * Errors stored as JSON array for detailed debugging in the scan log UI.
 */
return new class extends Migration
{
    /**
     * Run the migration to create the scan_logs table.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('scan_logs', function (Blueprint $table) {
            // Auto-incrementing primary key
            $table->id();
            // Foreign key to employees table — which employee this scan was for
            // Nullable and set null on delete so logs survive employee removal
            $table->foreignId('employee_id')->nullable()->constrained()->onDelete('set null');
            // Type of scan performed (currently just 'billing', extensible for future types)
            $table->string('scan_type')->default('billing');
            // Total number of rows read from the Google Sheet during this scan
            $table->integer('rows_scanned')->default(0);
            // Number of valid pending rows collected for invoicing
            $table->integer('rows_collected')->default(0);
            // JSON array of error objects encountered during the scan
            // Each error: {"row": X, "type": "unmatched_client", "message": "..."}
            $table->json('errors')->nullable();
            // When the scan started — used for duration calculation and log ordering
            $table->timestamp('started_at')->nullable();
            // When the scan completed — null if still running or failed mid-scan
            $table->timestamp('completed_at')->nullable();
            // Scan lifecycle status: running → completed or failed
            $table->string('status')->default('running');
            // Laravel timestamps (created_at, updated_at)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migration — drop the scan_logs table.
     *
     * @return void
     */
    public function down(): void
    {
        // Remove the scan_logs table entirely
        Schema::dropIfExists('scan_logs');
    }
};
