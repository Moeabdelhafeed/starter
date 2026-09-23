<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $user = User::query()->where('email', strtolower(trim($request->input('email'))))->first();

        // One generic message for unknown email and wrong password so the form
        // cannot be used to enumerate admin accounts.
        if (! $user || ! $user->password || ! Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['email' => __('admin.invalid_credentials')]);
        }

        if (! $user->is_active) {
            return back()->withErrors(['email' => __('admin.account_is_inactive')]);
        }

        if (! $user->roles()->where('guard_name', 'web')->where('is_active', true)->exists()) {
            return back()->withErrors(['email' => __('admin.you_are_not_authorized_to_login')]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
