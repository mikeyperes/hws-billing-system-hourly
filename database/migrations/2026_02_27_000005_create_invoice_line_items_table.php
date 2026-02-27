<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the invoice_line_items table.
 * Each record represents one billable row from an employee's Google Sheet.
 * Maps back to the original sheet entry via primary_key and sheet_row_number.
 */
return new class extends Migration
{
    /**
     * Run the migration to create the invoice_line_items table.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('invoice_line_items', function (Blueprint $table) {
            // Auto-incrementing primary key
            $table->id();
            // Foreign key to invoices table — which invoice this line item belongs to
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            // Foreign key to employees table — which employee's sheet this row came from
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            // The primary_key value from the employee's Google Sheet for this row
            // Used to identify the exact row for write-back operations
            $table->integer('primary_key');
            // Date the work was performed — copied from the sheet's date column
            $table->date('date');
            // Duration of work in minutes — copied from the sheet's time column
            $table->integer('time_minutes');
            // Description of work performed — copied from the sheet's description column
            $table->text('description')->nullable();
            // Client name as entered in the sheet — stored for reference even if client is later renamed
            $table->string('client_name');
            // Domain/project reference — stored but not processed in billing logic per spec
            $table->string('domain')->nullable();
            // The actual row number in the Google Sheet (1-indexed)
            // Needed for write-back operations (updating billed_status, applying cell formatting)
            $table->integer('sheet_row_number')->nullable();
            // Laravel timestamps (created_at, updated_at)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migration — drop the invoice_line_items table.
     *
     * @return void
     */
    public function down(): void
    {
        // Remove the invoice_line_items table entirely
        Schema::dropIfExists('invoice_line_items');
    }
};
