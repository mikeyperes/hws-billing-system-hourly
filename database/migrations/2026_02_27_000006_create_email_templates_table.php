<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the email_templates table.
 * Stores multiple canned email templates per use case.
 * All fields support shortcode substitution (e.g., {{client_name}}).
 * One template per use case can be marked as primary (default selection).
 */
return new class extends Migration
{
    /**
     * Run the migration to create the email_templates table.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            // Auto-incrementing primary key
            $table->id();
            // Use case category — groups templates by purpose
            // Examples: 'invoice_notification', 'low_credit_alert', 'custom'
            $table->string('use_case');
            // Human-readable template name for display in the template selector
            $table->string('name');
            // Whether this is the default template for its use case
            // Only one template per use_case should have is_primary = true
            $table->boolean('is_primary')->default(false);
            // Sender display name — supports shortcodes
            $table->string('from_name')->nullable();
            // Sender email address — supports shortcodes
            $table->string('from_email')->nullable();
            // Reply-to email address — supports shortcodes
            $table->string('reply_to')->nullable();
            // CC email addresses (comma-separated) — supports shortcodes
            $table->string('cc')->nullable();
            // Email subject line — supports shortcodes
            $table->string('subject')->nullable();
            // Email body — HTML content with shortcode support
            // longText because work_log shortcode can produce large tables
            $table->longText('body')->nullable();
            // Whether this template is available for selection
            $table->boolean('is_active')->default(true);
            // Laravel timestamps (created_at, updated_at)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migration — drop the email_templates table.
     *
     * @return void
     */
    public function down(): void
    {
        // Remove the email_templates table entirely
        Schema::dropIfExists('email_templates');
    }
};
