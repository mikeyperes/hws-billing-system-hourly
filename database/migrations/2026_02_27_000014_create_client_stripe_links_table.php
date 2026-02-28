<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the client_stripe_links pivot table.
 * Links clients to their Stripe customer IDs across multiple Stripe accounts.
 * One client can have different cus_ IDs on different Stripe accounts.
 * One link can be flagged as the hourly billing profile (used by billing scan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_stripe_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('stripe_account_id')->constrained()->onDelete('cascade');
            $table->string('stripe_customer_id');            // cus_xxxxx on this specific Stripe account
            $table->boolean('is_hourly_billing')->default(false); // Hourly billing profile flag
            $table->text('notes')->nullable();
            $table->timestamps();

            // A client can only have one link per Stripe account
            $table->unique(['client_id', 'stripe_account_id'], 'client_account_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_stripe_links');
    }
};
