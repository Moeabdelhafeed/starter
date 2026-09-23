<?php

namespace App\Http\Controllers\Admin\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user()->load('image');

        return Inertia::render('Profile/Index', [
            // Only what the form renders — the full model would ship device ids,
            // social links and OTP state into the page payload.
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'image' => $user->image,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ]);

        $user->forceFill([
            'name' => $validated['name'],
        ]);

        if ($request->hasFile('image')) {
            $user->saveImage($request->file('image'), 'users');
        } elseif ($request->boolean('remove_image')) {
            $user->deleteImage();
        }

        $user->save();

        return redirect()->back()->with('success', __('admin.updated_successfully'));
    }

    /**
     * Change the signed-in admin's own password.
     *
     * Verifying the current password is what stops a hijacked session from
     * locking the real owner out, so it is required even though the user is
     * already authenticated.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! $user->password || ! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('admin.current_password_incorrect'),
            ]);
        }

        $user->forceFill(['password' => Hash::make($validated['password'])])->save();

        // Every other session for this user is now stale — rotate so the
        // password change actually ends them.
        $request->session()->regenerate();

        return redirect()->back()->with('success', __('admin.password_updated'));
    }
}
