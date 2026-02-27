<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * EmailTemplate model — stores canned email templates per use case.
 * All fields (from, to, subject, body, etc.) support shortcode substitution.
 * One template per use_case can be marked as primary (default selection).
 *
 * @property int $id
 * @property string $use_case
 * @property string $name
 * @property bool $is_primary
 * @property string|null $from_name
 * @property string|null $from_email
 * @property string|null $reply_to
 * @property string|null $cc
 * @property string|null $subject
 * @property string|null $body
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class EmailTemplate extends Model
{
    /**
     * Fields that can be mass-assigned via create() or update().
     *
     * @var array<string>
     */
    protected $fillable = [
        'use_case',   // Category: invoice_notification, low_credit_alert, custom, etc.
        'name',       // Human-readable template name
        'is_primary', // Whether this is the default for its use_case
        'from_name',  // Sender name (supports shortcodes)
        'from_email', // Sender email (supports shortcodes)
        'reply_to',   // Reply-to address (supports shortcodes)
        'cc',         // CC addresses, comma-separated (supports shortcodes)
        'subject',    // Email subject line (supports shortcodes)
        'body',       // Email HTML body (supports shortcodes)
        'is_active',  // Whether template is available for selection
    ];

    /**
     * Attribute type casting for proper PHP types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_primary' => 'boolean', // Cast to true/false
        'is_active'  => 'boolean', // Cast to true/false
    ];

    /**
     * Get the primary (default) template for a given use case.
     *
     * @param string $useCase The use case to look up (e.g., 'invoice_notification')
     * @return static|null The primary template, or null if none is set
     */
    public static function getPrimary(string $useCase): ?static
    {
        // Find the active, primary template for this use case
        return static::where('use_case', $useCase)
            ->where('is_primary', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get all active templates for a given use case.
     * Used to populate template selector dropdowns.
     *
     * @param string $useCase The use case to look up
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getByUseCase(string $useCase): \Illuminate\Database\Eloquent\Collection
    {
        // Return all active templates for this use case, primary first
        return static::where('use_case', $useCase)
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all distinct use cases that have templates.
     * Used to organize the template management UI.
     *
     * @return \Illuminate\Support\Collection List of unique use_case strings
     */
    public static function getUseCases(): \Illuminate\Support\Collection
    {
        // Pluck unique use_case values from all templates
        return static::distinct()->pluck('use_case');
    }

    /**
     * When setting this template as primary, unset any other primary template
     * for the same use_case. Only one primary per use_case is allowed.
     *
     * @return void
     */
    public function makePrimary(): void
    {
        // First, unset all other primary templates for this use_case
        static::where('use_case', $this->use_case)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        // Then set this one as primary
        $this->update(['is_primary' => true]);
    }
}
