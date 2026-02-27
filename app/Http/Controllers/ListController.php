<?php

namespace App\Http\Controllers;

use App\Models\ListItem;
use Illuminate\Http\Request;

/**
 * ListController — manages the dynamic lookup lists system.
 * Lists are generic key-value groups used for dropdowns throughout the app.
 * Admin can add new list keys, add/remove values, and reorder items.
 */
class ListController extends Controller
{
    /**
     * Display the list management page.
     * Shows all list keys with their items.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Get all list keys that exist in the system
        $listKeys = ListItem::getKeys();

        // Build a grouped collection: key → items
        $lists = [];
        foreach ($listKeys as $key) {
            // Get all items (active and inactive) for this key, ordered by sort_order
            $lists[$key] = ListItem::where('list_key', $key)
                ->orderBy('sort_order')
                ->get();
        }

        // Render the list management view
        return view('lists.index', [
            'lists' => $lists,  // All lists grouped by key
        ]);
    }

    /**
     * Add a new item to a list.
     * If the list_key is new, it creates a new list category.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        // Validate the incoming form data
        $validated = $request->validate([
            'list_key'   => 'required|string|max:100',   // List category key
            'list_value' => 'required|string|max:255',   // The new value to add
        ]);

        // Determine the next sort_order for this list key
        $maxSort = ListItem::where('list_key', $validated['list_key'])->max('sort_order') ?? -1;

        // Create the new list item
        ListItem::create([
            'list_key'   => $validated['list_key'],    // List category
            'list_value' => $validated['list_value'],   // New value
            'sort_order' => $maxSort + 1,               // Append to the end
            'is_active'  => true,                       // Active by default
        ]);

        // Redirect back with success message
        return redirect()
            ->route('lists.index')
            ->with('success', 'Added "' . $validated['list_value'] . '" to ' . $validated['list_key'] . '.');
    }

    /**
     * Toggle the active/inactive status of a list item.
     *
     * @param ListItem $list Route model binding
     * @return \Illuminate\Http\RedirectResponse
     */
    public function toggle(ListItem $list)
    {
        // Flip the is_active flag
        $list->update([
            'is_active' => !$list->is_active,
        ]);

        // Build status message
        $status = $list->is_active ? 'activated' : 'deactivated';

        // Redirect back with success message
        return redirect()
            ->route('lists.index')
            ->with('success', '"' . $list->list_value . '" ' . $status . '.');
    }

    /**
     * Delete a list item permanently.
     *
     * @param ListItem $list Route model binding
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(ListItem $list)
    {
        // Store info for the success message before deleting
        $value = $list->list_value;
        $key = $list->list_key;

        // Delete the list item
        $list->delete();

        // Redirect back with success message
        return redirect()
            ->route('lists.index')
            ->with('success', 'Removed "' . $value . '" from ' . $key . '.');
    }
}
