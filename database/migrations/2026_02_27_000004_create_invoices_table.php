<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the invoices table.
 * Each invoice maps to a Stripe draft invoice.
 * Tracks total minutes, amount, payment status, and which employee sheet rows are included.
 */
return new class extends Migration
{
    /**
     * Run the migration to create the invoices table.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            // Auto-incrementing primary key
            $table->id();
            // Stripe Invoice ID (in_xxxxx) — used for all Stripe API lookups
            // Nullable because local record is created before Stripe call
            $table->string('stripe_invoice_id')->nullable()->unique();
            // Foreign key to the clients table — which client this invoice is for
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            // Sum of all billable minutes across all line items in this invoice
            $table->integer('total_minutes')->default(0);
            // Calculated dollar amount: (total_minutes / 60) × client hourly_rate
            $table->decimal('total_amount', 10, 2)->default(0);
            // Invoice lifecycle status: draft → sent → paid (or void)
            // Synced with Stripe when refreshing payment status
            $table->string('status')->default('draft');
            // Full Stripe payment details stored as JSON when invoice is paid
            // Includes: payment method, date paid, amount received, etc.
            $table->json('stripe_payment_details')->nullable();
            // Per-employee primary key ranges included in this invoice
            // JSON format: {"employee_id": {"start": X, "end": Y}, ...}
            // Used for traceability and billing reversal
            $table->json('employee_ranges')->nullable();
            // Stripe hosted invoice URL — for linking in emails
            $table->string('stripe_invoice_url')->nullable();
            // Laravel timestamps (created_at, updated_at)
            $table->timestamps();
            // Soft delete support — records are flagged, not removed from DB
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migration — drop the invoices table.
     *
     * @return void
     */
    public function down(): void
    {
        // Remove the invoices table entirely
        Schema::dropIfExists('invoices');
    }
};
