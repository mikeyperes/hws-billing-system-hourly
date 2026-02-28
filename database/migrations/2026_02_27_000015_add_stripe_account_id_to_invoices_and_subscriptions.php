<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add stripe_account_id to invoices and hosting_subscriptions.
 * Tracks which Stripe account each invoice/subscription was created under.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('stripe_account_id')->nullable()->after('client_id')
                ->constrained()->nullOnDelete();
        });

        Schema::table('hosting_subscriptions', function (Blueprint $table) {
            $table->foreignId('stripe_account_id')->nullable()->after('hosting_account_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stripe_account_id');
        });

        Schema::table('hosting_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stripe_account_id');
        });
    }
};
