<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the hosting_subscriptions table.
 * Links hosting accounts to Stripe subscriptions.
 * Each hosting account can have MULTIPLE subscriptions (e.g. hosting, maintenance, domain).
 * This is the many-to-many bridge between hosting_accounts and Stripe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hosting_subscriptions', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Which hosting account this subscription belongs to
            $table->foreignId('hosting_account_id')->constrained('hosting_accounts')->onDelete('cascade');

            // Subscription type/label — describes what this subscription covers
            // Examples: "hosting", "maintenance", "domain", "ssl", "email_hosting"
            $table->string('type');

            // Stripe subscription ID (e.g. "sub_1N3abc...")
            $table->string('stripe_subscription_id')->nullable();

            // Stripe customer ID (e.g. "cus_1N3abc...")
            $table->string('stripe_customer_id')->nullable();

            // Stripe price ID (e.g. "price_1N3abc...")
            $table->string('stripe_price_id')->nullable();

            // Subscription status — mirrors Stripe: 'active', 'past_due', 'canceled', 'unpaid', 'trialing', 'incomplete'
            $table->string('status')->default('active');

            // Recurring amount in cents (e.g. 2999 = $29.99)
            $table->integer('amount_cents')->default(0);

            // Billing interval — 'month', 'year', 'week'
            $table->string('interval')->default('month');

            // Current period start and end dates — from Stripe
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();

            // When this subscription was canceled (if applicable)
            $table->timestamp('canceled_at')->nullable();

            // Notes — free text for admin reference
            $table->text('notes')->nullable();

            // Timestamps
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosting_subscriptions');
    }
};
