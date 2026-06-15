<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function index()
    {
        return Inertia::render('Login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:8',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return back()->withErrors([
                'email' => __('admin.invalid_credentials'),
            ]);
        }

        if (! $user->is_active) {
            return back()->withErrors([
                'email' => __('admin.account_is_inactive'),
            ]);
        }

        if ($user->roles()->where('guard_name', 'web')->where('is_active', true)->exists()) {
            Auth::login($user);
        } else {
            return back()->withErrors([
                'email' => __('admin.you_are_not_authorized_to_login'),
            ]);
        }

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
