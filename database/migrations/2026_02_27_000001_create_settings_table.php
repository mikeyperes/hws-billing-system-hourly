<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the settings table.
 * Key/value store for runtime-editable configuration.
 * Grouped by category (stripe, email, system, etc.) for organized UI display.
 */
return new class extends Migration
{
    /**
     * Run the migration to create the settings table.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            // Auto-incrementing primary key
            $table->id();
            // Unique setting identifier (e.g., 'smtp_host', 'stripe_key')
            $table->string('key')->unique();
            // The setting value — nullable because some settings may be empty
            $table->text('value')->nullable();
            // Category grouping for UI organization (e.g., 'email', 'stripe', 'system')
            $table->string('group')->default('general');
            // Input type for the settings form (text, textarea, password, boolean)
            $table->string('type')->default('text');
            // Human-readable label shown in the settings UI
            $table->string('label')->nullable();
            // Controls display order within each group
            $table->integer('sort_order')->default(0);
            // Laravel timestamps (created_at, updated_at)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migration — drop the settings table.
     *
     * @return void
     */
    public function down(): void
    {
        // Remove the settings table entirely
        Schema::dropIfExists('settings');
    }
};
