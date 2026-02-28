<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add is_primary_billing flag to client_stripe_links.
 * Marks one Stripe link as the default billing source for a client.
 * Used throughout the system as the go-to Stripe account/customer for invoicing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_stripe_links', function (Blueprint $table) {
            $table->boolean('is_primary_billing')->default(false)->after('is_hourly_billing');
        });
    }

    public function down(): void
    {
        Schema::table('client_stripe_links', function (Blueprint $table) {
            $table->dropColumn('is_primary_billing');
        });
    }
};
