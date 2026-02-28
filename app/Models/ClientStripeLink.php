<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ClientStripeLink — links a client to a Stripe customer ID on a specific account.
 * One client can have different cus_ IDs across multiple Stripe accounts.
 * One link per client can be flagged as the hourly billing profile.
 *
 * @property int $id
 * @property int $client_id
 * @property int $stripe_account_id
 * @property string $stripe_customer_id
 * @property bool $is_hourly_billing
 * @property string|null $notes
 */
class ClientStripeLink extends Model
{
    protected $fillable = [
        'client_id',
        'stripe_account_id',
        'stripe_customer_id',
        'is_hourly_billing',
        'is_primary_billing',
        'notes',
    ];

    protected $casts = [
        'is_hourly_billing'  => 'boolean',
        'is_primary_billing' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function stripeAccount(): BelongsTo
    {
        return $this->belongsTo(StripeAccount::class);
    }
}
