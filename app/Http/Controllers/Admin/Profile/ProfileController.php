<?php

namespace App\Http\Controllers\Admin\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function index()
    {
        $user = auth()->user()->load('image');

        return Inertia::render('Profile/Index', [
            'user' => $user,
        ]);
    }

    public function update(Request $request)
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
}
