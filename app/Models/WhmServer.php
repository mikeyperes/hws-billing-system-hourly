<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * WhmServer — represents a WHM/cPanel server connection.
 * Credentials are encrypted at rest using Laravel's Crypt facade.
 * Each server can have many hosting accounts.
 */
class WhmServer extends Model
{
    /**
     * Mass-assignable attributes.
     */
    protected $fillable = [
        'name',           // Server display name
        'hostname',       // Server hostname or IP
        'port',           // WHM API port (default 2087)
        'auth_type',      // Authentication method
        'username',       // WHM username (typically root)
        'credentials',    // Encrypted password/token/hash
        'is_active',      // Whether server is active for scanning
        'last_synced_at', // Last sync timestamp
        'account_count',  // Total accounts on last sync
        'notes',          // Admin notes
    ];

    /**
     * Attribute casting.
     */
    protected $casts = [
        'is_active'      => 'boolean',
        'last_synced_at' => 'datetime',
        'port'           => 'integer',
        'account_count'  => 'integer',
    ];

    /**
     * Encrypt credentials before saving.
     *
     * @param string|null $value Raw credential value
     */
    public function setCredentialsAttribute(?string $value): void
    {
        // Only encrypt if a value is provided
        $this->attributes['credentials'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Decrypt credentials when reading.
     *
     * @param string|null $value Encrypted credential value
     * @return string|null Decrypted credential
     */
    public function getCredentialsAttribute(?string $value): ?string
    {
        // Only decrypt if a value exists
        if (!$value) return null;

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            // If decryption fails (key changed, corrupted data), return null
            return null;
        }
    }

    /**
     * Relationship: A WHM server has many hosting accounts.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function hostingAccounts()
    {
        return $this->hasMany(HostingAccount::class);
    }

    /**
     * Relationship: Get only active accounts on this server.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function activeAccounts()
    {
        return $this->hasMany(HostingAccount::class)->where('status', 'active');
    }
}
