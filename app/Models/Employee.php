<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Employee model — each employee has a Google Sheet for time tracking.
 * Primary key cursors track where billing scans should start/ended.
 *
 * @property int $id
 * @property string $name
 * @property string $google_sheet_id
 * @property int $scan_start_primary_key
 * @property int $last_billing_primary_key
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Employee extends Model
{
    // Enable soft deletes — employees are flagged, not removed from DB
    use SoftDeletes;

    /**
     * Fields that can be mass-assigned via create() or update().
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',                              // Employee display name
        'google_sheet_id',                   // Google Sheet ID (extracted from URL) — nullable
        'scan_start_primary_key',            // Where to start scanning on next billing run
        'previous_scan_start_primary_key',   // Where cursor was before last change
        'last_billing_primary_key',          // Where the last billing run ended
        'is_active',                         // Whether employee is included in billing scans
    ];

    /**
     * Attribute type casting for proper PHP types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'scan_start_primary_key'          => 'integer',
        'previous_scan_start_primary_key' => 'integer',
        'last_billing_primary_key'        => 'integer',
        'is_active'                       => 'boolean',
    ];

    /**
     * Get all invoice line items from this employee's sheet.
     *
     * @return HasMany
     */
    public function lineItems(): HasMany
    {
        // An employee's sheet rows can appear across many invoices
        return $this->hasMany(InvoiceLineItem::class);
    }

    /**
     * Get all scan logs for this employee.
     *
     * @return HasMany
     */
    public function scanLogs(): HasMany
    {
        // Each billing scan creates a log entry for this employee
        return $this->hasMany(ScanLog::class);
    }

    /**
     * Scope query to only include active employees.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        // Filter to only records where is_active = true
        return $query->where('is_active', true);
    }

    /**
     * Build the full Google Sheet URL from the stored sheet ID.
     *
     * @return string The complete Google Sheets URL
     */
    public function getSheetUrlAttribute(): string
    {
        // Construct the full URL using the stored sheet ID
        return 'https://docs.google.com/spreadsheets/d/' . $this->google_sheet_id;
    }

    /**
     * Extract and store the sheet ID from a full Google Sheets URL.
     * Accepts both full URLs and plain sheet IDs.
     *
     * @param string $value Full URL or plain sheet ID
     * @return void
     */
    public function setGoogleSheetIdAttribute(string $value): void
    {
        // Check if the value is a full Google Sheets URL
        if (str_contains($value, 'docs.google.com/spreadsheets')) {
            // Extract the sheet ID from the URL using regex
            // URL format: https://docs.google.com/spreadsheets/d/SHEET_ID/edit...
            preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $value, $matches);
            // Use the extracted ID if found, otherwise store the raw value
            $this->attributes['google_sheet_id'] = $matches[1] ?? $value;
        } else {
            // Already a plain sheet ID — store as-is
            $this->attributes['google_sheet_id'] = $value;
        }
    }
}
