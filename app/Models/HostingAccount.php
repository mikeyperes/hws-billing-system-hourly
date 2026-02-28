<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * HostingAccount — a cPanel account discovered from a WHM server.
 * Belongs to a WHM server, optionally owned by a client.
 * Can have multiple Stripe subscriptions (hosting, maintenance, domain, etc.).
 */
class HostingAccount extends Model
{
    /**
     * Mass-assignable attributes.
     */
    protected $fillable = [
        'whm_server_id',     // FK to whm_servers
        'client_id',         // FK to clients (nullable)
        'username',          // cPanel username
        'domain',            // Primary domain
        'owner',             // Reseller owner (from WHM)
        'email',             // Contact email on cPanel account
        'package',           // Hosting package name
        'status',            // active, suspended, removed
        'suspend_reason',    // WHM suspension reason
        'ip_address',        // Assigned IP
        'disk_used_mb',      // Disk usage
        'disk_limit_mb',     // Disk quota
        'bandwidth_used_mb', // Bandwidth usage
        'shell_access',      // Shell type
        'theme',             // cPanel theme
        'server_created_at', // Creation date on server
        'notes',             // Admin notes
    ];

    /**
     * Attribute casting.
     */
    protected $casts = [
        'disk_used_mb'      => 'integer',
        'disk_limit_mb'     => 'integer',
        'bandwidth_used_mb' => 'integer',
        'server_created_at' => 'date',
    ];

    /**
     * Relationship: This account belongs to a WHM server.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function whmServer()
    {
        return $this->belongsTo(WhmServer::class);
    }

    /**
     * Relationship: This account is owned by a client (optional).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Relationship: This account has many Stripe subscriptions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions()
    {
        return $this->hasMany(HostingSubscription::class);
    }

    /**
     * Relationship: Get only active subscriptions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function activeSubscriptions()
    {
        return $this->hasMany(HostingSubscription::class)->where('status', 'active');
    }
}
