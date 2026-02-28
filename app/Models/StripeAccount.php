<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

/**
 * StripeAccount model — represents a connected Stripe account.
 * Supports multiple Stripe accounts for different business divisions.
 * Secret keys are encrypted at rest using Laravel's Crypt facade.
 *
 * @property int $id
 * @property string $name
 * @property string $secret_key (encrypted)
 * @property string|null $stripe_account_display
 * @property bool $is_default
 * @property bool $is_active
 * @property string|null $notes
 */
class StripeAccount extends Model
{
    protected $fillable = [
        'name',
        'secret_key',
        'stripe_account_display',
        'is_default',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    /**
     * Encrypt the secret key before storing.
     */
    public function setSecretKeyAttribute(string $value): void
    {
        $this->attributes['secret_key'] = Crypt::encryptString($value);
    }

    /**
     * Decrypt the secret key when reading.
     */
    public function getSecretKeyAttribute(?string $value): ?string
    {
        if ($value === null) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null; // Key changed or corrupt
        }
    }

    /**
     * Get a masked version of the secret key for display.
     */
    public function getMaskedKeyAttribute(): string
    {
        $key = $this->secret_key;
        if (!$key) return '(not set)';
        return substr($key, 0, 7) . '...' . substr($key, -4);
    }

    /**
     * Client links through the pivot table.
     */
    public function clientLinks(): HasMany
    {
        return $this->hasMany(ClientStripeLink::class);
    }

    /**
     * Invoices created under this account.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Scope: only active accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the default Stripe account.
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->where('is_active', true)->first();
    }
}
