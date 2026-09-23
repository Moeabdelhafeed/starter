<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
});

function deviceHeaders(array $overrides = []): array
{
    return array_merge([
        'X-API-TOKEN' => env('APP_X_API_TOKEN'),
        'Accept-Language' => 'en',
        'X-Device-Id' => 'test-device-uuid',
        'X-Platform' => 'web',
        'Accept' => 'application/json',
    ], $overrides);
}

it('rejects requests missing X-Device-Id', function () {
    $headers = deviceHeaders();
    unset($headers['X-Device-Id']);

    $response = $this->withHeaders($headers)->getJson('/api/config');

    // Keyed under the field, per the field-keyed error convention. The wording itself is
    // DB-backed and CMS-editable, so asserting the sentence would test the seed data.
    $response->assertStatus(422)
        ->assertJsonStructure(['errors' => ['device_id']]);
});

it('rejects requests with invalid X-Platform', function () {
    $response = $this->withHeaders(deviceHeaders(['X-Platform' => 'desktop']))
        ->getJson('/api/config');

    $response->assertStatus(422)
        ->assertJsonStructure(['errors' => ['platform']]);
});

it('creates a guest user when the client registers one', function () {
    // Guests are NOT auto-created on first hit any more — IdentifyDevice resolves an
    // existing guest but never makes one, so the client registers explicitly.
    $response = $this->withHeaders(deviceHeaders([
        'X-Device-Id' => 'guest-1',
        'X-Platform' => 'ios',
        // ios/android are FCM platforms; IdentifyDevice rejects them without a token.
        'X-FCM-Token' => 'guest-1-fcm',
        'Accept-Language' => 'en',
    ]))->postJson('/api/guest');

    $response->assertOk();

    $user = User::where('guest_id', 'guest-1')->first();

    expect($user)->not->toBeNull()
        ->and($user->is_guest)->toBeTrue()
        ->and($user->platform)->toBe('ios')
        ->and($user->guest_id)->toBe('guest-1');
});

it('reuses the same row for repeated guest registrations', function () {
    $headers = deviceHeaders(['X-Device-Id' => 'reuse-1', 'X-Platform' => 'android', 'X-FCM-Token' => 'reuse-1-fcm']);

    // Registering twice from one device must not leave two guest rows behind.
    $this->withHeaders($headers)->postJson('/api/guest');
    $this->withHeaders($headers)->postJson('/api/guest');

    expect(User::where('guest_id', 'reuse-1')->count())->toBe(1);
});

it('skips guest creation when APP_GUESTS=false', function () {
    config()->set('features.app_guests', false);

    $this->withHeaders(deviceHeaders(['X-Device-Id' => 'no-guest', 'X-Platform' => 'web']))
        ->getJson('/api/config')
        ->assertOk();

    expect(User::where('guest_id', 'no-guest')->count())->toBe(0);
});

it('exposes app_users and app_guests flags in auth-config', function () {
    config()->set('features.app_guests', true);
    config()->set('features.app_users', true);

    $this->withHeaders(deviceHeaders())
        ->getJson('/api/config')
        ->assertOk()
        ->assertJsonPath('data.app_users', true)
        ->assertJsonPath('data.app_guests', true);
});

it('keeps user_devices empty across guest activity', function () {
    config()->set('features.app_guests', true);

    $this->withHeaders(deviceHeaders(['X-Device-Id' => 'still-guest']))
        ->getJson('/api/config');

    $this->assertDatabaseCount('user_devices', 0);
});
