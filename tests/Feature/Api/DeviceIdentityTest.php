<?php

use App\Models\Language;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDevice;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);

    Language::firstOrCreate(
        ['code' => 'en'],
        ['name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_active' => true, 'is_default' => true],
    );

    // Pinned rather than inherited: phpunit.xml does not fix MULTI_SESSION_ENABLED,
    // and with it off a second login silently revokes the first token — which would
    // make the multi-device cases below fail for a reason that has nothing to do
    // with what they are testing.
    config()->set('auth.multi_session_enabled', true);
});

/**
 * Log a mobile user in and hand back their bearer token. Going through the real
 * endpoint matters here: it is login — not the device middleware — that stamps a
 * device row with the Sanctum token id that `is_current` compares against.
 */
function loginAppUser(User $user, string $deviceId): string
{
    return test()->withHeaders(apiHeaders(['X-Device-Id' => $deviceId]))
        ->postJson('/api/login', [
            'identifier' => $user->email,
            'type' => 'email',
            'password' => 'correct-horse-battery',
        ])->assertOk()->json('token');
}

it('rejects a mobile request that carries no X-FCM-Token', function (string $platform) {
    // ios/android are the push platforms — a session with no token can never be
    // reached again, so the middleware refuses it up front.
    $this->withHeaders(apiHeaders(['X-Platform' => $platform, 'X-Device-Id' => 'no-fcm-device']))
        ->getJson('/api/config')
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['fcm_token']]);
})->with(['ios', 'android']);

it('accepts a web request with no X-FCM-Token', function () {
    // The counterweight: web has no push channel, so the token is not demanded.
    $this->withHeaders(apiHeaders(['X-Platform' => 'web']))->getJson('/api/config')->assertOk();
});

it('rejects a device id longer than the column allows', function () {
    // 64 chars is the storage limit; a longer id would be silently truncated and
    // start colliding with someone else's device.
    $this->withHeaders(apiHeaders(['X-Device-Id' => str_repeat('a', 65)]))
        ->getJson('/api/config')
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['device_id']]);

    $this->withHeaders(apiHeaders(['X-Device-Id' => str_repeat('a', 64)]))
        ->getJson('/api/config')
        ->assertOk();
});

it('lists the signed-in user devices and flags the one making the request', function () {
    $user = appUser(['email' => 'jane@example.com']);
    $token = loginAppUser($user, 'current-device');

    $otherSession = UserDevice::factory()->create([
        'user_id' => $user->id,
        'device_id' => 'other-device',
        'last_seen_at' => now()->subDay(),
    ]);

    $response = $this->withHeaders(apiHeaders(['X-Device-Id' => 'current-device', 'Authorization' => 'Bearer '.$token]))
        ->getJson('/api/devices')
        ->assertOk()
        ->assertJsonStructure(['data' => ['devices' => [['id', 'platform', 'last_seen_at', 'is_current']]]]);

    $devices = collect($response->json('data.devices'))->keyBy('id');

    expect($devices)->toHaveCount(2)
        ->and($devices->firstWhere('is_current', true)['id'])->not->toBe($otherSession->id)
        ->and($devices[$otherSession->id]['is_current'])->toBeFalse();
});

it('never lists another users devices', function () {
    $user = appUser(['email' => 'jane@example.com']);
    $token = loginAppUser($user, 'current-device');

    UserDevice::factory()->create(['user_id' => appUser(['email' => 'someone@example.com'])->id]);

    $response = $this->withHeaders(apiHeaders(['X-Device-Id' => 'current-device', 'Authorization' => 'Bearer '.$token]))
        ->getJson('/api/devices')->assertOk();

    expect($response->json('data.devices'))->toHaveCount(1);
});

it('revokes one of the callers own devices', function () {
    $user = appUser(['email' => 'jane@example.com']);
    $token = loginAppUser($user, 'current-device');
    $otherToken = loginAppUser($user, 'second-device');

    $second = UserDevice::where('user_id', $user->id)->where('device_id', 'second-device')->firstOrFail();

    $this->withHeaders(apiHeaders(['X-Device-Id' => 'current-device', 'Authorization' => 'Bearer '.$token]))
        ->deleteJson('/api/devices/'.$second->id)
        ->assertOk()
        ->assertJsonPath('success', true);

    // Revoking kills the Sanctum token; the device row goes with it by FK cascade.
    // Asserted against the token store rather than by replaying the dead bearer,
    // because the auth guard caches its resolved user for the lifetime of the test
    // process and would answer the replay from that cache.
    expect(UserDevice::whereKey($second->id)->exists())->toBeFalse()
        ->and(PersonalAccessToken::findToken($otherToken))->toBeNull()
        ->and(PersonalAccessToken::findToken($token))->not->toBeNull();
});

it('answers a revoke of somebody elses device with a 404 in the standard envelope', function () {
    $user = appUser(['email' => 'jane@example.com']);
    $token = loginAppUser($user, 'current-device');

    $strangersDevice = UserDevice::factory()->create([
        'user_id' => appUser(['email' => 'someone@example.com'])->id,
    ]);

    $response = $this->withHeaders(apiHeaders(['X-Device-Id' => 'current-device', 'Authorization' => 'Bearer '.$token]))
        ->deleteJson('/api/devices/'.$strangersDevice->id);

    // 404 rather than 403: confirming the id exists would leak another account's
    // session inventory. The envelope has to match every other API error.
    expect($response->getStatusCode())->toBe(404)
        ->and($response->json())->toHaveKeys(['success', 'message', 'errors', 'data'])
        ->and($response->json('success'))->toBeFalse();

    expect(UserDevice::whereKey($strangersDevice->id)->exists())->toBeTrue();
});
