<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create item_templates table.
 * Stores reusable invoice line item text templates with shortcode support.
 * Used by the Invoicing Center when creating subscriptions/invoices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');                              // Template name (e.g. "Annual Hosting")
            $table->string('category')->default('general');      // Group: hosting, maintenance, domain, custom
            $table->text('description_template');                 // Line item text with shortcodes
            $table->integer('default_amount_cents')->default(0); // Default amount in cents
            $table->string('default_interval')->default('year'); // month, year, week
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_templates');
    }
};
