<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ScanLog model — records each billing scan run per employee.
 * Stores row counts, error details, and timing for the scan log UI.
 *
 * @property int $id
 * @property int|null $employee_id
 * @property string $scan_type
 * @property int $rows_scanned
 * @property int $rows_collected
 * @property array|null $errors
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property string $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ScanLog extends Model
{
    /**
     * Fields that can be mass-assigned via create() or update().
     *
     * @var array<string>
     */
    protected $fillable = [
        'employee_id',    // FK to employees table (nullable — survives employee deletion)
        'scan_type',      // Type of scan: 'billing', 'validation', etc.
        'rows_scanned',   // Total rows read from the Google Sheet
        'rows_collected', // Valid pending rows collected for invoicing
        'errors',         // JSON array of error objects
        'started_at',     // When the scan began
        'completed_at',   // When the scan finished (null if failed mid-scan)
        'status',         // running, completed, failed
    ];

    /**
     * Attribute type casting for proper PHP types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rows_scanned'   => 'integer',  // Always an integer
        'rows_collected' => 'integer',  // Always an integer
        'errors'         => 'array',    // Auto JSON encode/decode
        'started_at'     => 'datetime', // Cast to Carbon datetime
        'completed_at'   => 'datetime', // Cast to Carbon datetime
    ];

    /**
     * Get the employee this scan log belongs to.
     * May be null if the employee was deleted (onDelete set null).
     *
     * @return BelongsTo
     */
    public function employee(): BelongsTo
    {
        // Each scan log is for one employee (or null if deleted)
        return $this->belongsTo(Employee::class);
    }

    /**
     * Calculate the duration of the scan in seconds.
     * Returns null if the scan hasn't completed yet.
     *
     * @return int|null Duration in seconds, or null if incomplete
     */
    public function getDurationSecondsAttribute(): ?int
    {
        // Can't calculate duration if scan hasn't finished
        if (!$this->completed_at || !$this->started_at) {
            return null;
        }

        // Difference between start and end timestamps in seconds
        return $this->completed_at->diffInSeconds($this->started_at);
    }

    /**
     * Check if this scan encountered any errors.
     *
     * @return bool True if the errors array has entries
     */
    public function hasErrors(): bool
    {
        // Check if errors array exists and is non-empty
        return !empty($this->errors);
    }

    /**
     * Get the count of errors in this scan.
     *
     * @return int Number of errors
     */
    public function getErrorCountAttribute(): int
    {
        // Count the errors array, defaulting to 0 if null
        return count($this->errors ?? []);
    }
}
