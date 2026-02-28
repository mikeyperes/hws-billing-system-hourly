<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Invoice model — each record maps to a Stripe draft invoice.
 * Tracks total time, amount, payment status, and which employee rows are included.
 *
 * @property int $id
 * @property string|null $stripe_invoice_id
 * @property int $client_id
 * @property int $total_minutes
 * @property float $total_amount
 * @property string $status
 * @property array|null $stripe_payment_details
 * @property array|null $employee_ranges
 * @property string|null $stripe_invoice_url
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Invoice extends Model
{
    // Enable soft deletes — invoices are flagged, not removed from DB
    use SoftDeletes;

    /**
     * Fields that can be mass-assigned via create() or update().
     *
     * @var array<string>
     */
    protected $fillable = [
        'stripe_invoice_id',       // Stripe Invoice ID for API lookups
        'client_id',               // FK to clients table
        'stripe_account_id',       // FK to stripe_accounts — which account created this invoice
        'total_minutes',           // Sum of all line item minutes
        'total_amount',            // Calculated dollar amount
        'status',                  // draft, sent, paid, void
        'stripe_payment_details',  // JSON payment info from Stripe
        'employee_ranges',         // JSON per-employee primary key ranges
        'stripe_invoice_url',      // Stripe hosted invoice URL for email links
    ];

    /**
     * Attribute type casting for proper PHP types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'total_minutes'           => 'integer',   // Always an integer
        'total_amount'            => 'decimal:2',  // Always 2 decimal places
        'stripe_payment_details'  => 'array',      // Auto JSON encode/decode
        'employee_ranges'         => 'array',      // Auto JSON encode/decode
    ];

    /**
     * Get the client this invoice belongs to.
     *
     * @return BelongsTo
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the Stripe account this invoice was created under.
     *
     * @return BelongsTo
     */
    public function stripeAccount(): BelongsTo
    {
        return $this->belongsTo(StripeAccount::class);
    }

    /**
     * Get all line items (billable rows) included in this invoice.
     *
     * @return HasMany
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    /**
     * Calculate total hours from total minutes.
     * Used for display and email shortcodes.
     *
     * @return float Hours rounded to 2 decimal places
     */
    public function getTotalHoursAttribute(): float
    {
        // Convert minutes to hours with 2 decimal precision
        return round($this->total_minutes / 60, 2);
    }

    /**
     * Format the total amount as a USD currency string.
     * Used for display and email shortcodes.
     *
     * @return string Formatted amount (e.g., "$1,250.00")
     */
    public function getFormattedAmountAttribute(): string
    {
        // Use the currency symbol from config and format with commas and 2 decimals
        return config('hws.currency_symbol') . number_format($this->total_amount, 2);
    }

    /**
     * Check if this invoice has been paid.
     *
     * @return bool True if status is 'paid'
     */
    public function isPaid(): bool
    {
        // Compare against the paid status constant from config
        return $this->status === config('hws.invoice_statuses.paid');
    }

    /**
     * Check if this invoice is still a draft.
     *
     * @return bool True if status is 'draft'
     */
    public function isDraft(): bool
    {
        // Compare against the draft status constant from config
        return $this->status === config('hws.invoice_statuses.draft');
    }

    /**
     * Scope query to only include unpaid invoices.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnpaid($query)
    {
        // Exclude invoices with paid or void status
        return $query->whereNotIn('status', [
            config('hws.invoice_statuses.paid'),
            config('hws.invoice_statuses.void'),
        ]);
    }
}
