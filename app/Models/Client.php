<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Client model — represents a billable client imported from Stripe.
 * Stores local billing configuration (hourly rate, billing type, credits).
 *
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string $stripe_customer_id
 * @property float $hourly_rate
 * @property string|null $billing_type
 * @property float $credit_balance_hours
 * @property bool $credit_alert_sent
 * @property bool $is_active
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Client extends Model
{
    // Enable soft deletes — clients are flagged, not removed from DB
    use SoftDeletes;

    /**
     * Fields that can be mass-assigned via create() or update().
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',                 // Display name from Stripe
        'email',                // Email from Stripe
        'stripe_customer_id',   // Stripe Customer ID (cus_xxxxx)
        'hourly_rate',          // Client-specific hourly rate in USD
        'billing_type',         // From Lists module: hourly_open, hourly_credits, fixed, or null
        'credit_balance_hours', // Remaining prepaid hours (for hourly_credits clients)
        'credit_alert_sent',    // Whether low-credit alert has been sent
        'is_active',            // Whether client appears in billing scans
        'notes',                // Free-form admin notes
    ];

    /**
     * Attribute type casting for proper PHP types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'hourly_rate'          => 'decimal:2',  // Always 2 decimal places
        'credit_balance_hours' => 'decimal:2',  // Always 2 decimal places
        'credit_alert_sent'    => 'boolean',    // Cast to true/false
        'is_active'            => 'boolean',    // Cast to true/false
    ];

    /**
     * Get all invoices belonging to this client.
     *
     * @return HasMany
     */
    public function invoices(): HasMany
    {
        // A client can have many invoices over time
        return $this->hasMany(Invoice::class);
    }

    /**
     * Check if this client's credit balance is below the configured threshold.
     * Only relevant for clients with billing_type = 'hourly_credits'.
     *
     * @return bool True if credits are low, false otherwise
     */
    public function isCreditLow(): bool
    {
        // Only check credits for hourly_credits billing type
        if ($this->billing_type !== 'hourly_credits') {
            // Non-credit clients can't have low credits
            return false;
        }

        // Compare balance against threshold from config/hws.php
        return $this->credit_balance_hours <= config('hws.credit_low_threshold_hours');
    }

    /**
     * Scope query to only include active clients.
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
     * Scope query to only include clients with low credit balance.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLowCredit($query)
    {
        // Filter to hourly_credits clients below the threshold
        return $query->where('billing_type', 'hourly_credits')
            ->where('credit_balance_hours', '<=', config('hws.credit_low_threshold_hours'));
    }
}
