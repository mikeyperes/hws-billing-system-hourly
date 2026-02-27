<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the whm_servers table.
 * Stores WHM/cPanel server connections for the Hexa Cloud Services module.
 * Each server can be scanned to discover hosting accounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whm_servers', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Server display name — friendly label (e.g. "Production VPS 1")
            $table->string('name');

            // Server hostname or IP — used for WHM API connections
            $table->string('hostname');

            // WHM API port — typically 2087 for HTTPS
            $table->integer('port')->default(2087);

            // Authentication method — 'root_password', 'api_token', or 'access_hash'
            $table->string('auth_type')->default('api_token');

            // WHM username — typically 'root'
            $table->string('username')->default('root');

            // Encrypted credentials — password, API token, or access hash
            // Stored encrypted via Laravel's Crypt facade in the model
            $table->text('credentials')->nullable();

            // Whether this server is active for scanning
            $table->boolean('is_active')->default(true);

            // Last time accounts were synced from this server
            $table->timestamp('last_synced_at')->nullable();

            // Total accounts discovered on last sync
            $table->integer('account_count')->default(0);

            // Notes — free text for admin reference
            $table->text('notes')->nullable();

            // Timestamps
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whm_servers');
    }
};
