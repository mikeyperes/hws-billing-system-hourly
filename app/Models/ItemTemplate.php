<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ItemTemplate -- reusable invoice line item templates with shortcode support.
 * Used by the Invoicing Center to quickly populate subscription/invoice descriptions.
 */
class ItemTemplate extends Model
{
    protected $fillable = [
        'name',
        'category',
        'description_template',
        'default_amount_cents',
        'default_interval',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'default_amount_cents' => 'integer',
        'sort_order'           => 'integer',
        'is_active'            => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Render the description template by replacing shortcodes.
     */
    public function renderDescription(array $shortcodes): string
    {
        $text = $this->description_template;
        foreach ($shortcodes as $code => $value) {
            $text = str_replace($code, $value, $text);
        }
        return $text;
    }

    /**
     * Get formatted default amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->default_amount_cents / 100, 2);
    }
}
