<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * HostingSubscription — links a hosting account to a Stripe subscription.
 * Each hosting account can have multiple subscriptions for different services
 * (e.g. hosting, maintenance, domain renewal, SSL, email hosting).
 */
class HostingSubscription extends Model
{
    /**
     * Mass-assignable attributes.
     */
    protected $fillable = [
        'hosting_account_id',     // FK to hosting_accounts
        'type',                   // Subscription type label (hosting, maintenance, domain)
        'stripe_subscription_id', // Stripe sub_xxx ID
        'stripe_customer_id',     // Stripe cus_xxx ID
        'stripe_price_id',        // Stripe price_xxx ID
        'status',                 // active, past_due, canceled, etc.
        'amount_cents',           // Recurring amount in cents
        'interval',               // month, year, week
        'current_period_start',   // Period start from Stripe
        'current_period_end',     // Period end from Stripe
        'canceled_at',            // Cancellation date
        'notes',                  // Admin notes
    ];

    /**
     * Attribute casting.
     */
    protected $casts = [
        'amount_cents'         => 'integer',
        'current_period_start' => 'datetime',
        'current_period_end'   => 'datetime',
        'canceled_at'          => 'datetime',
    ];

    /**
     * Relationship: This subscription belongs to a hosting account.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function hostingAccount()
    {
        return $this->belongsTo(HostingAccount::class);
    }

    /**
     * Get the formatted amount (dollars).
     *
     * @return string Formatted dollar amount (e.g. "$29.99")
     */
    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->amount_cents / 100, 2);
    }
}
