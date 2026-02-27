<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Setting model — key/value store for runtime-editable configuration.
 * Provides static helpers for getting/setting values without instantiation.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property string $group
 * @property string $type
 * @property string|null $label
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Setting extends Model
{
    /**
     * Fields that can be mass-assigned via create() or update().
     *
     * @var array<string>
     */
    protected $fillable = [
        'key',        // Unique setting identifier
        'value',      // The setting value (stored as text)
        'group',      // Category grouping for UI display
        'type',       // Input type for the settings form
        'label',      // Human-readable label for the form
        'sort_order', // Display order within group
    ];

    /**
     * Retrieve a setting value by its key.
     * Returns the default if the key doesn't exist in the database.
     *
     * @param string $key     The setting key to look up
     * @param mixed  $default Fallback value if key not found
     * @return mixed The setting value or the default
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        // Query the database for a matching key
        $setting = static::where('key', $key)->first();

        // Return the stored value if found, otherwise the default
        return $setting ? $setting->value : $default;
    }

    /**
     * Create or update a setting by key.
     * If the key already exists, its value is updated.
     * If not, a new record is created.
     *
     * @param string $key   The setting key
     * @param mixed  $value The value to store
     * @param string $group Optional group category
     * @return static The created or updated Setting instance
     */
    public static function setValue(string $key, mixed $value, string $group = 'general'): static
    {
        // Use Laravel's updateOrCreate to handle both insert and update
        return static::updateOrCreate(
            // Find by key
            ['key' => $key],
            // Set/update the value and group
            ['value' => $value, 'group' => $group]
        );
    }

    /**
     * Get all settings organized by their group.
     * Used to render the Settings page with grouped sections.
     *
     * @return \Illuminate\Support\Collection Collection of settings grouped by 'group' field
     */
    public static function getGrouped(): \Illuminate\Support\Collection
    {
        // Fetch all settings, ordered by group then sort_order, and group the collection
        return static::orderBy('group')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('group');
    }
}
