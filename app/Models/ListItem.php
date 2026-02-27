<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ListItem model — generic lookup table for dropdown values and categories.
 * Multiple items share a list_key to form one dropdown list.
 * Admin can add new list_keys and values as the system grows.
 *
 * @property int $id
 * @property string $list_key
 * @property string $list_value
 * @property int $sort_order
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ListItem extends Model
{
    /**
     * The database table name.
     * Model is 'ListItem' but table is 'lists' — explicit mapping required.
     *
     * @var string
     */
    protected $table = 'lists';

    /**
     * Fields that can be mass-assigned via create() or update().
     *
     * @var array<string>
     */
    protected $fillable = [
        'list_key',   // List category (e.g., 'customer_billing_type')
        'list_value', // Individual option value (e.g., 'hourly_open')
        'sort_order', // Display order within the list
        'is_active',  // Whether this item appears in dropdowns
    ];

    /**
     * Attribute type casting for proper PHP types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sort_order' => 'integer', // Always an integer
        'is_active'  => 'boolean', // Cast to true/false
    ];

    /**
     * Get all active values for a given list key.
     * Returns an array suitable for populating a <select> dropdown.
     *
     * @param string $listKey The list key to look up (e.g., 'customer_billing_type')
     * @return \Illuminate\Support\Collection Collection of list_value strings
     */
    public static function getValues(string $listKey): \Illuminate\Support\Collection
    {
        // Query active items for this key, ordered by sort_order
        return static::where('list_key', $listKey)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('list_value');
    }

    /**
     * Get all active items for a given list key as full objects.
     * Used when you need IDs and other fields, not just values.
     *
     * @param string $listKey The list key to look up
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getItems(string $listKey): \Illuminate\Database\Eloquent\Collection
    {
        // Query active items for this key, ordered by sort_order
        return static::where('list_key', $listKey)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get all distinct list keys in the system.
     * Used to display the list management UI with all available lists.
     *
     * @return \Illuminate\Support\Collection Collection of unique list_key strings
     */
    public static function getKeys(): \Illuminate\Support\Collection
    {
        // Pluck unique list_key values from all records
        return static::distinct()->pluck('list_key');
    }
}
