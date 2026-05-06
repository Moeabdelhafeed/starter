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

    $response = $this->withHeaders($headers)->getJson('/api/auth-config');

    $response->assertStatus(422)
        ->assertJsonPath('errors.device_id.0', 'X-Device-Id header is required.');
});

it('rejects requests with invalid X-Platform', function () {
    $response = $this->withHeaders(deviceHeaders(['X-Platform' => 'desktop']))
        ->getJson('/api/auth-config');

    $response->assertStatus(422)
        ->assertJsonPath('errors.platform.0', 'X-Platform header must be one of: web, ios, android.');
});

it('creates a guest user on first hit when APP_GUESTS=true', function () {
    config(['app.env' => 'testing']);
    putenv('APP_GUESTS=true');

    $response = $this->withHeaders(deviceHeaders([
        'X-Device-Id' => 'guest-1',
        'X-Platform' => 'ios',
        'Accept-Language' => 'en',
    ]))->getJson('/api/auth-config');

    $response->assertOk();

    $user = User::where('guest_id', 'guest-1')->first();

    expect($user)->not->toBeNull()
        ->and($user->is_guest)->toBeTrue()
        ->and($user->platform)->toBe('ios')
        ->and($user->guest_id)->toBe('guest-1');
});

it('reuses the same row for repeated guest hits', function () {
    putenv('APP_GUESTS=true');

    $headers = deviceHeaders(['X-Device-Id' => 'reuse-1', 'X-Platform' => 'android']);

    $this->withHeaders($headers)->getJson('/api/auth-config');
    $this->withHeaders($headers)->getJson('/api/auth-config');

    expect(User::where('guest_id', 'reuse-1')->count())->toBe(1);
});

it('skips guest creation when APP_GUESTS=false', function () {
    putenv('APP_GUESTS=false');

    $this->withHeaders(deviceHeaders(['X-Device-Id' => 'no-guest', 'X-Platform' => 'web']))
        ->getJson('/api/auth-config')
        ->assertOk();

    expect(User::where('guest_id', 'no-guest')->count())->toBe(0);
});

it('exposes app_users and app_guests flags in auth-config', function () {
    putenv('APP_GUESTS=true');
    putenv('APP_USERS=true');

    $this->withHeaders(deviceHeaders())
        ->getJson('/api/auth-config')
        ->assertOk()
        ->assertJsonPath('data.app_users', true)
        ->assertJsonPath('data.app_guests', true);
});

it('keeps user_devices empty across guest activity', function () {
    putenv('APP_GUESTS=true');

    $this->withHeaders(deviceHeaders(['X-Device-Id' => 'still-guest']))
        ->getJson('/api/auth-config');

    $this->assertDatabaseCount('user_devices', 0);
});
