<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Show the login form (redirect to dashboard if already signed in).
     */
    public function showLogin(Request $request)
    {
        if ($request->session()->get('admin_authenticated')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    /**
     * Validate the submitted credentials against the .env admin login.
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email    = (string) config('admin.email');
        $password = (string) config('admin.password');

        $okEmail = hash_equals(strtolower($email), strtolower(trim($data['email'])));
        $okPass  = hash_equals($password, $data['password']);

        if (! $okEmail || ! $okPass) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Those credentials don\'t match our records.']);
        }

        $request->session()->regenerate();
        $request->session()->put('admin_authenticated', true);

        return redirect()->intended(route('admin.dashboard'));
    }

    /**
     * Sign the admin out.
     */
    public function logout(Request $request)
    {
        $request->session()->forget('admin_authenticated');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
