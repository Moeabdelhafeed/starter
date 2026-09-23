<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('logs in an active user holding an active web role', function () {
    $user = adminUser(['password' => Hash::make('correct-horse-battery')]);

    $this->post(route('login.post'), [
        'email' => $user->email,
        'password' => 'correct-horse-battery',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rotates the session id on login so a fixated session is useless', function () {
    $user = adminUser(['password' => Hash::make('correct-horse-battery')]);

    $this->get(route('login'));
    $before = session()->getId();

    $this->post(route('login.post'), [
        'email' => $user->email,
        'password' => 'correct-horse-battery',
    ]);

    expect(session()->getId())->not->toBe($before);
});

it('accepts the email in any casing', function () {
    $user = adminUser(['email' => 'casing@example.com', 'password' => Hash::make('correct-horse-battery')]);

    $this->post(route('login.post'), [
        'email' => '  CASING@Example.COM ',
        'password' => 'correct-horse-battery',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('gives the same error for a wrong password and an unknown address', function () {
    adminUser(['email' => 'known@example.com', 'password' => Hash::make('correct-horse-battery')]);

    // Identical wording for both is what stops the form being used to
    // enumerate which admin addresses exist.
    $wrongPassword = $this->from(route('login'))->post(route('login.post'), [
        'email' => 'known@example.com',
        'password' => 'not-the-password',
    ]);

    $unknownEmail = $this->from(route('login'))->post(route('login.post'), [
        'email' => 'nobody@example.com',
        'password' => 'not-the-password',
    ]);

    $wrongPassword->assertRedirect(route('login'))->assertSessionHasErrors('email');
    $unknownEmail->assertRedirect(route('login'))->assertSessionHasErrors('email');

    expect($wrongPassword->getSession()->get('errors')->first('email'))
        ->toBe($unknownEmail->getSession()->get('errors')->first('email'));

    $this->assertGuest();
});

it('refuses a deactivated account', function () {
    $user = adminUser(['is_active' => false, 'password' => Hash::make('correct-horse-battery')]);

    $this->from(route('login'))->post(route('login.post'), [
        'email' => $user->email,
        'password' => 'correct-horse-battery',
    ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('refuses an account with no active web role', function () {
    $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);
    $user->assignRole(Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']));

    $this->from(route('login'))->post(route('login.post'), [
        'email' => $user->email,
        'password' => 'correct-horse-battery',
    ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('throttles repeated login attempts from one address', function () {
    adminUser(['email' => 'throttled@example.com', 'password' => Hash::make('correct-horse-battery')]);

    $status = null;

    for ($attempt = 0; $attempt < 12; $attempt++) {
        $status = $this->from(route('login'))->post(route('login.post'), [
            'email' => 'throttled@example.com',
            'password' => 'wrong',
        ])->getStatusCode();
    }

    expect($status)->toBe(429);
});

it('logs out and invalidates the session', function () {
    $user = adminUser();

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
});

it('kicks a session whose account was deactivated mid-session', function () {
    $user = adminUser();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $user->forceFill(['is_active' => false])->save();

    $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
});

it('sends a guest hitting a protected page to the login screen', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
