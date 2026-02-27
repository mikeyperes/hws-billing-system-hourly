<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the clients table.
 * Clients are imported from Stripe using Customer IDs.
 * Local fields added for hourly rate, billing type, and credit tracking.
 */
return new class extends Migration
{
    /**
     * Run the migration to create the clients table.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            // Auto-incrementing primary key
            $table->id();
            // Client display name — pulled from Stripe during import
            $table->string('name');
            // Client email — pulled from Stripe during import
            $table->string('email')->nullable();
            // Stripe Customer ID (cus_xxxxx) — unique to prevent duplicate imports
            $table->string('stripe_customer_id')->unique();
            // Client-specific hourly rate in USD — defaults to value from config/hws.php
            $table->decimal('hourly_rate', 10, 2)->default(100.00);
            // Billing type — selected from the Lists module (hourly_open, hourly_credits, fixed, or null)
            $table->string('billing_type')->nullable();
            // Remaining prepaid credit hours — only used when billing_type = hourly_credits
            $table->decimal('credit_balance_hours', 10, 2)->default(0);
            // Flag: whether a low-credit alert email has already been sent for current balance
            $table->boolean('credit_alert_sent')->default(false);
            // Whether client is active — inactive clients are hidden from billing scans
            $table->boolean('is_active')->default(true);
            // Free-form notes field for admin reference
            $table->text('notes')->nullable();
            // Laravel timestamps (created_at, updated_at)
            $table->timestamps();
            // Soft delete support — records are flagged, not removed from DB
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migration — drop the clients table.
     *
     * @return void
     */
    public function down(): void
    {
        // Remove the clients table entirely
        Schema::dropIfExists('clients');
    }
};
