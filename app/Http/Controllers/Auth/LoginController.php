<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * LoginController — handles admin login and logout.
 * Simple form-based authentication without requiring Laravel Breeze/UI packages.
 * Uses Laravel's built-in Auth facade.
 */
class LoginController extends Controller
{
    /**
     * Show the login form.
     *
     * @return \Illuminate\View\View
     */
    public function showLoginForm()
    {
        // If already authenticated, redirect to dashboard
        if (Auth::check()) {
            // User is logged in — send to dashboard
            return redirect()->route('dashboard');
        }

        // Render the login view
        return view('auth.login');
    }

    /**
     * Process the login attempt.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login(Request $request)
    {
        // Validate login credentials
        $credentials = $request->validate([
            'email'    => 'required|email',     // Must be a valid email
            'password' => 'required|string',    // Must be a string
        ]);

        // Attempt authentication with the provided credentials
        $remember = $request->boolean('remember');

        // Try to authenticate the user
        if (Auth::attempt($credentials, $remember)) {
            // Regenerate the session to prevent session fixation attacks
            $request->session()->regenerate();

            // Redirect to the intended page (or dashboard as default)
            return redirect()->intended(route('dashboard'));
        }

        // Authentication failed — redirect back with error
        return back()
            ->withInput($request->only('email'))
            ->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ]);
    }

    /**
     * Log the user out and redirect to login.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
    {
        // Log the user out of the application
        Auth::logout();

        // Invalidate the current session
        $request->session()->invalidate();

        // Regenerate the CSRF token
        $request->session()->regenerateToken();

        // Redirect to the login page
        return redirect()->route('login');
    }
}
