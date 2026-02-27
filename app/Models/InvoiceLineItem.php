<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * InvoiceLineItem model — one billable row from an employee's Google Sheet.
 * Links back to the original sheet entry via primary_key and sheet_row_number.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $employee_id
 * @property int $primary_key
 * @property \Carbon\Carbon $date
 * @property int $time_minutes
 * @property string|null $description
 * @property string $client_name
 * @property string|null $domain
 * @property int|null $sheet_row_number
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class InvoiceLineItem extends Model
{
    /**
     * The database table name.
     * Explicitly set because Laravel would auto-guess 'invoice_line_items' which is correct,
     * but being explicit prevents any ambiguity.
     *
     * @var string
     */
    protected $table = 'invoice_line_items';

    /**
     * Fields that can be mass-assigned via create() or update().
     *
     * @var array<string>
     */
    protected $fillable = [
        'invoice_id',       // FK to invoices table
        'employee_id',      // FK to employees table — whose sheet this came from
        'primary_key',      // The primary_key value from the Google Sheet row
        'date',             // Date work was performed
        'time_minutes',     // Duration in minutes
        'description',      // Work description from the sheet
        'client_name',      // Client name as entered in the sheet (snapshot)
        'domain',           // Domain reference — stored but not processed per spec
        'sheet_row_number', // Actual row number in Google Sheet for write-back
    ];

    /**
     * Attribute type casting for proper PHP types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date'             => 'date',     // Cast to Carbon date object
        'time_minutes'     => 'integer',  // Always an integer
        'primary_key'      => 'integer',  // Always an integer
        'sheet_row_number' => 'integer',  // Always an integer
    ];

    /**
     * Get the invoice this line item belongs to.
     *
     * @return BelongsTo
     */
    public function invoice(): BelongsTo
    {
        // Each line item belongs to exactly one invoice
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the employee whose sheet this line item came from.
     *
     * @return BelongsTo
     */
    public function employee(): BelongsTo
    {
        // Each line item came from exactly one employee's sheet
        return $this->belongsTo(Employee::class);
    }

    /**
     * Convert time_minutes to hours for display.
     *
     * @return float Hours rounded to 2 decimal places
     */
    public function getTimeHoursAttribute(): float
    {
        // Convert minutes to hours with 2 decimal precision
        return round($this->time_minutes / 60, 2);
    }
}
