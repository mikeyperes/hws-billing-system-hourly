<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make clients.stripe_customer_id nullable.
 * Stripe customer IDs are now managed via client_stripe_links pivot table.
 * Legacy field kept for backward compatibility during transition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable(false)->change();
        });
    }
};
