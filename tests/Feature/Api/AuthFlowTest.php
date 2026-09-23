<?php

use App\Models\Language;
use App\Models\Otp;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);

    Language::firstOrCreate(
        ['code' => 'en'],
        ['name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_active' => true, 'is_default' => true],
    );
});

it('logs in with the right password and returns a usable token', function () {
    $user = appUser(['email' => 'jane@example.com']);

    $response = $this->withHeaders(apiHeaders())->postJson('/api/login', [
        'identifier' => 'jane@example.com',
        'type' => 'email',
        'password' => 'correct-horse-battery',
    ])->assertOk();

    $token = $response->json('token');

    expect($token)->not->toBeEmpty();

    $this->withHeaders(apiHeaders(['Authorization' => 'Bearer '.$token]))
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('keys the invalid-password error under the password field', function () {
    appUser(['email' => 'jane@example.com']);

    // The mobile clients render errors under the field they belong to, so the
    // key matters as much as the status.
    $this->withHeaders(apiHeaders())->postJson('/api/login', [
        'identifier' => 'jane@example.com',
        'type' => 'email',
        'password' => 'not-the-password',
    ])->assertStatus(422)->assertJsonStructure(['errors' => ['password']]);
});

it('refuses an identifier with no declared type', function () {
    appUser(['email' => 'jane@example.com']);

    // Inferring the kind from the value is what let a bare number be read as a
    // local phone; the type is always declared.
    $this->withHeaders(apiHeaders())->postJson('/api/login', [
        'identifier' => 'jane@example.com',
        'password' => 'correct-horse-battery',
    ])->assertStatus(422)->assertJsonStructure(['errors' => ['type']]);
});

it('refuses a deactivated account on protected routes', function () {
    $user = appUser(['email' => 'jane@example.com']);

    $token = $this->withHeaders(apiHeaders())->postJson('/api/login', [
        'identifier' => 'jane@example.com',
        'type' => 'email',
        'password' => 'correct-horse-battery',
    ])->json('token');

    $user->forceFill(['is_active' => false])->save();

    $this->withHeaders(apiHeaders(['Authorization' => 'Bearer '.$token]))
        ->getJson('/api/devices')
        ->assertStatus(403);
});

it('revokes the token on logout', function () {
    appUser(['email' => 'jane@example.com']);

    $token = $this->withHeaders(apiHeaders())->postJson('/api/login', [
        'identifier' => 'jane@example.com',
        'type' => 'email',
        'password' => 'correct-horse-battery',
    ])->json('token');

    $authed = apiHeaders(['Authorization' => 'Bearer '.$token]);

    $this->withHeaders($authed)->postJson('/api/logout')->assertOk();

    // Assert on the stored token rather than a follow-up request: the test
    // client keeps the resolved user for the rest of the case, so a second
    // call would still look authenticated even though the token is gone.
    expect(DB::table('personal_access_tokens')->count())->toBe(0);
});

it('will not accept an expired token even on routes outside the sanctum guard', function () {
    $user = appUser();
    $token = $user->createToken('expired', ['*'], now()->subDay());

    // GET /api/user resolves through IdentifyDevice rather than auth:sanctum, so
    // the expiry has to be enforced there too.
    $this->withHeaders(apiHeaders(['Authorization' => 'Bearer '.$token->plainTextToken]))
        ->getJson('/api/user')
        ->assertStatus(401);
});

it('sends a password reset code and lets the user set a new password', function () {
    $user = appUser(['email' => 'jane@example.com']);
    config()->set('app.is_testing', true);

    $code = $this->withHeaders(apiHeaders())->postJson('/api/forgot-password', [
        'identifier' => 'jane@example.com',
        'type' => 'email',
    ])->assertOk()->json('data.otp');

    expect($code)->not->toBeEmpty();

    $this->withHeaders(apiHeaders())->postJson('/api/verify-forgot-password-otp', [
        'identifier' => 'jane@example.com',
        'type' => 'email',
        'otp' => $code,
    ])->assertOk();

    $this->withHeaders(apiHeaders())->postJson('/api/change-forgot-password', [
        'identifier' => 'jane@example.com',
        'type' => 'email',
        'otp' => $code,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertOk();

    expect(Hash::check('a-brand-new-password', $user->fresh()->password))->toBeTrue();
});

it('destroys an OTP after repeated wrong guesses', function () {
    $user = appUser(['email' => 'jane@example.com']);
    config()->set('app.is_testing', true);

    $this->withHeaders(apiHeaders())->postJson('/api/forgot-password', [
        'identifier' => 'jane@example.com',
        'type' => 'email',
    ])->assertOk();

    expect(Otp::where('user_id', $user->id)->count())->toBe(1);

    // A six-digit code with no attempt ceiling is guessable inside its lifetime.
    for ($attempt = 0; $attempt < Otp::MAX_ATTEMPTS; $attempt++) {
        $this->withHeaders(apiHeaders())->postJson('/api/verify-forgot-password-otp', [
            'identifier' => 'jane@example.com',
            'type' => 'email',
            'otp' => '000000',
        ]);
    }

    expect(Otp::where('user_id', $user->id)->count())->toBe(0);
});

it('does not leak whether an address is registered on password reset', function () {
    appUser(['email' => 'jane@example.com']);
    config()->set('app.is_testing', false);

    $known = $this->withHeaders(apiHeaders())->postJson('/api/forgot-password', [
        'identifier' => 'jane@example.com',
        'type' => 'email',
    ]);

    $unknown = $this->withHeaders(apiHeaders())->postJson('/api/forgot-password', [
        'identifier' => 'nobody@example.com',
        'type' => 'email',
    ]);

    expect($unknown->getStatusCode())->toBe($known->getStatusCode());
})->skip('documented product decision: the API answers 422 user_not_found on an unknown identifier');
