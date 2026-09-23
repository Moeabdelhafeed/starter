<?php

use App\Models\Otp;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Registering always sends a verify OTP and never issues a token.
 *
 * There is no flag for this any more: an account is usable only once its email or phone
 * has been proven, so the client always continues with login + verify-otp. IS_TESTING
 * changes the code (the fixed ascending sequence, echoed in the response), never the flow.
 */
function registerHeaders(array $overrides = []): array
{
    return array_merge([
        'X-API-TOKEN' => env('APP_X_API_TOKEN'),
        'Accept-Language' => 'en',
        'X-Device-Id' => 'starter-register-device',
        'X-Platform' => 'ios',
        'X-FCM-Token' => 'register-test-fcm-token',
        'Accept' => 'application/json',
    ], $overrides);
}

function registerPayload(array $overrides = []): array
{
    return array_merge([
        'policy_agreed' => true,
        'name' => 'Jane Doe',
        'identifier' => 'jane@example.com',
        'type' => 'email',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
        // This kit ships HAS_USERNAME_FIELD=true, where username is required at register.
        'username' => 'janedoe',
    ], $overrides);
}

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
});

it('issues no token and leaves the account unverified', function () {
    $response = $this->withHeaders(registerHeaders())
        ->postJson('/api/register', registerPayload())
        ->assertOk();

    $user = User::where('email', 'jane@example.com')->first();

    expect($response->json('token'))->toBeNull()
        ->and($response->json('data.token'))->toBeNull()
        ->and($user->verified_at)->toBeNull();
});

it('sends exactly one verify OTP', function () {
    $this->withHeaders(registerHeaders())->postJson('/api/register', registerPayload())->assertOk();

    $user = User::where('email', 'jane@example.com')->first();

    expect($user->otps()->where('type', 'verify')->count())->toBe(1)
        ->and($user->otps()->count())->toBe(1);
});

it('tells the client how long the code lasts', function () {
    $this->withHeaders(registerHeaders())
        ->postJson('/api/register', registerPayload())
        ->assertOk()
        ->assertJsonPath('data.otp_expires_in_minutes', 5);
});

it('does not hand the code back outside testing mode', function () {
    // The code is only echoed where there is no mailbox to read it from.
    config()->set('app.is_testing', false);

    $this->withHeaders(registerHeaders())
        ->postJson('/api/register', registerPayload())
        ->assertOk()
        ->assertJsonMissingPath('data.otp');
});

it('completes the flow: register, login, verify', function () {
    config()->set('app.is_testing', true);

    $otp = $this->withHeaders(registerHeaders())
        ->postJson('/api/register', registerPayload())
        ->assertOk()
        ->json('data.otp');

    // Login issues a token even while unverified — that is what carries the verify call.
    $token = $this->withHeaders(registerHeaders())->postJson('/api/login', [
        'identifier' => 'jane@example.com',
        'type' => 'email',
        'password' => 'SecurePass123!',
    ])->assertOk()->json('token');

    $this->withHeaders(registerHeaders(['Authorization' => 'Bearer '.$token]))
        ->postJson('/api/verify-otp', ['otp' => $otp])
        ->assertOk();

    expect(User::where('email', 'jane@example.com')->first()->verified_at)->not->toBeNull();
});

it('refuses an identifier with no declared type', function () {
    $payload = registerPayload();
    unset($payload['type']);

    $this->withHeaders(registerHeaders())
        ->postJson('/api/register', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('type');
});

/**
 * Testing mode.
 *
 * IS_TESTING exists so a tester can walk the real flow without a real mailbox: it echoes the
 * code back in the response. A register that hands back an already-verified account skipped
 * the one screen that is there to be tested, so testing mode forces the two-step flow on and
 * fixes the code to the ascending sequence.
 */
it('leaves a testing-mode registration unverified and waiting on its OTP', function () {
    config()->set('app.is_testing', true);

    $response = $this->withHeaders(registerHeaders())
        ->postJson('/api/register', registerPayload())
        ->assertOk();

    $user = User::where('email', 'jane@example.com')->first();

    expect($response->json('token'))->toBeNull()
        ->and($user->verified_at)->toBeNull()
        ->and($user->otps()->where('type', 'verify')->count())->toBe(1);

    // And the code the response handed back actually clears it, over the documented
    // two-step flow: login (a token is issued even unverified), then verify-otp.
    $token = $this->withHeaders(registerHeaders())->postJson('/api/login', [
        'identifier' => 'jane@example.com',
        'type' => 'email',
        'password' => 'SecurePass123!',
    ])->assertOk()->json('token');

    $this->withHeaders(registerHeaders(['Authorization' => 'Bearer '.$token]))
        ->postJson('/api/verify-otp', ['otp' => $response->json('data.otp')])
        ->assertOk();

    expect($user->fresh()->verified_at)->not->toBeNull();
});

it('makes every testing-mode OTP the ascending sequence of the configured length', function () {
    config()->set('app.is_testing', true);

    $expected = substr('123456789', 0, Otp::LENGTH);

    // Register (verify code)...
    $registerOtp = $this->withHeaders(registerHeaders())
        ->postJson('/api/register', registerPayload())
        ->assertOk()
        ->json('data.otp');

    // ...and the other generator, behind forgot-password (reset_password code).
    $user = User::where('email', 'jane@example.com')->first();
    $user->forceFill(['verified_at' => now()])->save();

    $resetOtp = $this->withHeaders(registerHeaders())->postJson('/api/forgot-password', [
        'identifier' => 'jane@example.com',
        'type' => 'email',
    ])->assertOk()->json('data.otp');

    expect($registerOtp)->toBe($expected)
        ->and($resetOtp)->toBe($expected)
        ->and(Otp::generate())->toBe($expected)
        ->and(strlen($expected))->toBe(Otp::LENGTH);
});

it('keeps the same flow outside testing mode, with a random code', function () {
    config()->set('app.is_testing', false);

    $response = $this->withHeaders(registerHeaders())
        ->postJson('/api/register', registerPayload())
        ->assertOk();

    $user = User::where('email', 'jane@example.com')->first();

    // Same two-step shape; only the code differs and it is not handed back.
    expect($response->json('token'))->toBeNull()
        ->and($response->json('data.otp'))->toBeNull()
        ->and($user->verified_at)->toBeNull()
        ->and($user->otps()->where('type', 'verify')->count())->toBe(1);

    // Codes are random here: 20 draws off one generator do not land on one value.
    $codes = collect(range(1, 20))->map(fn (): string => Otp::generate());

    expect($codes->unique()->count())->toBeGreaterThan(1)
        ->and($codes->every(fn (string $code): bool => strlen($code) === Otp::LENGTH && ctype_digit($code)))->toBeTrue();
});
