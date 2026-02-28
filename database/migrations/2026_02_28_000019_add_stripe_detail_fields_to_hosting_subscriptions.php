<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add detailed Stripe subscription info fields.
 * Stores product name, description, customer name, and payment dates
 * pulled directly from the Stripe API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hosting_subscriptions', function (Blueprint $table) {
            $table->string('stripe_product_name')->nullable()->after('stripe_price_id');
            $table->text('stripe_description')->nullable()->after('stripe_product_name');
            $table->string('stripe_customer_name')->nullable()->after('stripe_customer_id');
            $table->string('stripe_customer_email')->nullable()->after('stripe_customer_name');
            $table->timestamp('last_payment_at')->nullable()->after('current_period_end');
            $table->timestamp('next_payment_at')->nullable()->after('last_payment_at');
        });
    }

    public function down(): void
    {
        Schema::table('hosting_subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_product_name', 'stripe_description',
                'stripe_customer_name', 'stripe_customer_email',
                'last_payment_at', 'next_payment_at',
            ]);
        });
    }
};
