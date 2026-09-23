<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('renders the profile page with only the fields the form needs', function () {
    $user = adminUser(['name' => 'Original Name']);

    $this->actingAs($user)->get(route('profile'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Profile/Index')
            ->where('user.name', 'Original Name')
            // The full model would ship device ids, social links and OTP state
            // into the page payload of a page that only edits a name and avatar.
            ->missing('user.password')
            ->missing('user.remember_token')
        );
});

it('updates the display name', function () {
    $user = adminUser(['name' => 'Original Name']);

    $this->actingAs($user)->put(route('profile.update'), ['name' => 'New Name'])
        ->assertRedirect();

    expect($user->fresh()->name)->toBe('New Name');
});

it('rejects an empty name', function () {
    $user = adminUser(['name' => 'Original Name']);

    $this->actingAs($user)->from(route('profile'))
        ->put(route('profile.update'), ['name' => ''])
        ->assertSessionHasErrors('name');

    expect($user->fresh()->name)->toBe('Original Name');
});

it('changes the password when the current one is given', function () {
    $user = adminUser(['password' => Hash::make('old-password-value')]);

    $this->actingAs($user)->put(route('profile.password'), [
        'current_password' => 'old-password-value',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertRedirect();

    expect(Hash::check('brand-new-password', $user->fresh()->password))->toBeTrue();
});

it('refuses a password change without the correct current password', function () {
    $user = adminUser(['password' => Hash::make('old-password-value')]);

    // Without this check a hijacked session could lock the real owner out.
    $this->actingAs($user)->from(route('profile'))->put(route('profile.password'), [
        'current_password' => 'wrong-password',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertSessionHasErrors('current_password');

    expect(Hash::check('old-password-value', $user->fresh()->password))->toBeTrue();
});

it('requires the new password to be confirmed', function () {
    $user = adminUser(['password' => Hash::make('old-password-value')]);

    $this->actingAs($user)->from(route('profile'))->put(route('profile.password'), [
        'current_password' => 'old-password-value',
        'password' => 'brand-new-password',
        'password_confirmation' => 'something-else',
    ])->assertSessionHasErrors('password');

    expect(Hash::check('old-password-value', $user->fresh()->password))->toBeTrue();
});

it('keeps the profile pages behind authentication', function () {
    $this->get(route('profile'))->assertRedirect(route('login'));
    $this->put(route('profile.update'), ['name' => 'x'])->assertRedirect(route('login'));
    $this->put(route('profile.password'), [])->assertRedirect(route('login'));

    expect(User::count())->toBe(0);
});
