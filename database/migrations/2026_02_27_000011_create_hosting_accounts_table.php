<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the hosting_accounts table.
 * Stores cPanel accounts discovered from WHM server scans.
 * Each account belongs to a WHM server and can have an owner (client).
 * Multiple Stripe subscriptions can be attached to each account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hosting_accounts', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Which WHM server this account lives on
            $table->foreignId('whm_server_id')->constrained('whm_servers')->onDelete('cascade');

            // Owner — links to our clients table (nullable if not yet assigned)
            $table->foreignId('client_id')->nullable()->constrained('clients')->onDelete('set null');

            // cPanel username — unique per server (e.g. "hexawebs")
            $table->string('username');

            // Primary domain for this account
            $table->string('domain');

            // cPanel package/plan name (e.g. "default", "premium_hosting")
            $table->string('package')->nullable();

            // Account status from WHM — 'active', 'suspended', 'terminated'
            $table->string('status')->default('active');

            // IP address assigned to the account
            $table->string('ip_address')->nullable();

            // Disk usage in MB — from WHM API
            $table->integer('disk_used_mb')->default(0);

            // Disk limit in MB — from WHM API (0 = unlimited)
            $table->integer('disk_limit_mb')->default(0);

            // Bandwidth used in MB — from WHM API
            $table->integer('bandwidth_used_mb')->default(0);

            // Account creation date on the server
            $table->date('server_created_at')->nullable();

            // Notes — free text for admin reference
            $table->text('notes')->nullable();

            // Timestamps
            $table->timestamps();

            // Unique constraint — one username per server
            $table->unique(['whm_server_id', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosting_accounts');
    }
};
