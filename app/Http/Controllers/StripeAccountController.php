<?php

namespace App\Http\Controllers;

use App\Models\StripeAccount;
use App\Services\StripeService;
use Illuminate\Http\Request;

/**
 * StripeAccountController — manages connected Stripe accounts.
 * Accessible from Settings. Supports multiple accounts for different divisions.
 */
class StripeAccountController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Display list of all Stripe accounts.
     */
    public function index()
    {
        $accounts = StripeAccount::orderBy('name')->get();
        return view('settings.stripe-accounts', ['accounts' => $accounts]);
    }

    /**
     * Show create form.
     */
    public function create()
    {
        return view('settings.stripe-account-form', ['account' => null]);
    }

    /**
     * Store a new Stripe account.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        if ($request->has('is_default') && $request->is_default) {
            StripeAccount::where('is_default', true)->update(['is_default' => false]);
        }

        StripeAccount::create([
            'name'                   => $validated['name'],
            'secret_key'             => $validated['secret_key'],
            'stripe_account_display' => $validated['stripe_account_display'] ?? null,
            'is_default'             => $request->has('is_default'),
            'is_active'              => true,
            'notes'                  => $validated['notes'] ?? null,
        ]);

        return redirect()->route('settings.stripe-accounts.index')
            ->with('success', 'Stripe account added.');
    }

    /**
     * Show edit form.
     */
    public function edit(StripeAccount $stripeAccount)
    {
        return view('settings.stripe-account-form', ['account' => $stripeAccount]);
    }

    /**
     * Update a Stripe account.
     */
    public function update(Request $request, StripeAccount $stripeAccount)
    {
        $validated = $request->validate($this->rules(isUpdate: true));

        if ($request->has('is_default') && $request->is_default) {
            StripeAccount::where('is_default', true)
                ->where('id', '!=', $stripeAccount->id)
                ->update(['is_default' => false]);
        }

        $updateData = [
            'name'                   => $validated['name'],
            'stripe_account_display' => $validated['stripe_account_display'] ?? null,
            'is_default'             => $request->has('is_default'),
            'is_active'              => $request->has('is_active'),
            'notes'                  => $validated['notes'] ?? null,
        ];

        if (!empty($validated['secret_key'])) {
            $updateData['secret_key'] = $validated['secret_key'];
        }

        $stripeAccount->update($updateData);

        return redirect()->route('settings.stripe-accounts.index')
            ->with('success', 'Stripe account updated.');
    }

    /**
     * Test connectivity for a specific Stripe account.
     */
    public function test(StripeAccount $stripeAccount)
    {
        $result = $this->stripeService->testConnection($stripeAccount->id);
        $type = $result['success'] ? 'success' : 'error';

        return redirect()->route('settings.stripe-accounts.index')
            ->with($type, 'Stripe "' . $stripeAccount->name . '": ' . $result['message']);
    }

    /**
     * Delete a Stripe account.
     */
    public function destroy(StripeAccount $stripeAccount)
    {
        $name = $stripeAccount->name;
        $stripeAccount->delete();

        return redirect()->route('settings.stripe-accounts.index')
            ->with('success', 'Stripe account "' . $name . '" deleted.');
    }

    /**
     * Shared validation rules — single source of truth.
     */
    private function rules(bool $isUpdate = false): array
    {
        $rules = [
            'name'                   => 'required|string|max:255',
            'secret_key'             => ($isUpdate ? 'nullable' : 'required') . '|string|max:500',
            'stripe_account_display' => 'nullable|string|max:255',
            'notes'                  => 'nullable|string|max:1000',
        ];
        if ($isUpdate) {
            $rules['is_active'] = 'boolean';
        }
        return $rules;
    }
}
