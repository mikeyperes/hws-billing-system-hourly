<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the stripe_accounts table.
 * Supports connecting multiple Stripe accounts (different business divisions).
 * Secret keys are encrypted via Laravel Crypt facade in the model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // Display name (e.g., "Hexa Web", "Hexa Hosting")
            $table->text('secret_key');                      // Encrypted Stripe secret key (sk_test_ or sk_live_)
            $table->string('stripe_account_display')->nullable(); // Optional Stripe account ID for display
            $table->boolean('is_default')->default(false);   // Default account for new operations
            $table->boolean('is_active')->default(true);     // Active toggle
            $table->text('notes')->nullable();               // Free-form admin notes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_accounts');
    }
};
