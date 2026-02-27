<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the lists table.
 * Generic, extensible lookup system for dropdown values and categories.
 * Each list is identified by a list_key (e.g., 'customer_billing_type').
 * Admin can add new list_keys and values via UI as the system grows.
 */
return new class extends Migration
{
    /**
     * Run the migration to create the lists table.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('lists', function (Blueprint $table) {
            // Auto-incrementing primary key
            $table->id();
            // List category identifier (e.g., 'customer_billing_type')
            // Multiple rows share the same list_key to form one dropdown list
            $table->string('list_key');
            // The actual value/option within the list (e.g., 'hourly_open')
            $table->string('list_value');
            // Controls display order of items within a list
            $table->integer('sort_order')->default(0);
            // Soft toggle — inactive items are hidden from dropdowns but not deleted
            $table->boolean('is_active')->default(true);
            // Laravel timestamps (created_at, updated_at)
            $table->timestamps();
            // Index on list_key for fast lookups when populating dropdowns
            $table->index('list_key');
        });
    }

    /**
     * Reverse the migration — drop the lists table.
     *
     * @return void
     */
    public function down(): void
    {
        // Remove the lists table entirely
        Schema::dropIfExists('lists');
    }
};
