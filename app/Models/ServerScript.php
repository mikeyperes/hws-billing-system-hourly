<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ServerScript — predefined maintenance commands for WHM servers.
 * Danger levels: safe, caution, destructive.
 */
class ServerScript extends Model
{
    protected $fillable = [
        'name',
        'description',
        'command',
        'category',
        'danger_level',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope: only active scripts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the CSS class for the danger level badge.
     */
    public function getDangerBadgeAttribute(): string
    {
        return match ($this->danger_level) {
            'destructive' => 'bg-red-100 text-red-700',
            'caution'     => 'bg-yellow-100 text-yellow-700',
            default       => 'bg-green-100 text-green-700',
        };
    }
}
