<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * HostingSubscription -- links a hosting account to a Stripe subscription.
 * Each hosting account can have multiple subscriptions for different services
 * (e.g. hosting, maintenance, domain renewal, SSL, email hosting).
 */
class HostingSubscription extends Model
{
    protected $fillable = [
        'hosting_account_id',
        'stripe_account_id',
        'type',
        'stripe_subscription_id',
        'stripe_customer_id',
        'stripe_customer_name',
        'stripe_customer_email',
        'stripe_price_id',
        'stripe_product_name',
        'stripe_description',
        'status',
        'amount_cents',
        'interval',
        'current_period_start',
        'current_period_end',
        'last_payment_at',
        'next_payment_at',
        'canceled_at',
        'notes',
    ];

    protected $casts = [
        'amount_cents'         => 'integer',
        'current_period_start' => 'datetime',
        'current_period_end'   => 'datetime',
        'last_payment_at'      => 'datetime',
        'next_payment_at'      => 'datetime',
        'canceled_at'          => 'datetime',
    ];

    public function hostingAccount()
    {
        return $this->belongsTo(HostingAccount::class);
    }

    public function stripeAccount()
    {
        return $this->belongsTo(StripeAccount::class);
    }

    /**
     * Formatted dollar amount (e.g. "$29.99").
     */
    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->amount_cents / 100, 2);
    }

    /**
     * Stripe dashboard URL for this subscription.
     */
    public function getStripeDashboardUrlAttribute(): ?string
    {
        if (!$this->stripe_subscription_id) return null;
        $mode = str_contains($this->stripe_subscription_id, 'test') ? 'test/' : '';
        return "https://dashboard.stripe.com/{$mode}subscriptions/{$this->stripe_subscription_id}";
    }
}
