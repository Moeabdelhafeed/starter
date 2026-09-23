<?php

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * One row per device, one row per FCM token.
 *
 * `User::fcmTokens()` feeds `sendMulticast()`, which delivers one push per token it is
 * handed — so a token appearing twice is the same notification twice on the same phone.
 * That is exactly what customers saw: a duplicate `user_devices` row per request race, each
 * carrying the same token, and a tray full of the same message.
 */
function tokenTestUser(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']));

    return $user;
}

function tokenTestHeaders(User $user, string $deviceId, string $fcmToken): array
{
    return [
        'X-API-TOKEN' => env('APP_X_API_TOKEN'),
        'Accept-Language' => 'en',
        'X-Device-Id' => $deviceId,
        'X-Platform' => 'android',
        'X-FCM-Token' => $fcmToken,
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken,
    ];
}

it('keeps one device row however many requests the phone makes', function () {
    $user = tokenTestUser();

    foreach (range(1, 5) as $ignored) {
        app('auth')->forgetGuards();
        $this->withHeaders(tokenTestHeaders($user, 'phone-1', 'token-abc'))
            ->getJson('/api/config')
            ->assertOk();
    }

    expect(UserDevice::where('user_id', $user->id)->count())->toBe(1)
        ->and($user->fcmTokens())->toBe(['token-abc']);
});

it('refuses a second row for the same device at the database level', function () {
    $user = tokenTestUser();

    UserDevice::create(['user_id' => $user->id, 'device_id' => 'phone-1', 'fcm_token' => 'token-abc']);

    // The index is the real guard — a racing insert that slips past the read must fail, not
    // quietly double every push this customer receives from then on.
    expect(fn () => UserDevice::create(['user_id' => $user->id, 'device_id' => 'phone-1', 'fcm_token' => 'token-abc']))
        ->toThrow(QueryException::class);
});

it('moves an fcm token to the device that reported it', function () {
    $user = tokenTestUser();

    // A reinstall hands the same token to a fresh device id. The old row keeping its copy
    // is a second delivery to the same handset.
    UserDevice::create(['user_id' => $user->id, 'device_id' => 'old-install', 'fcm_token' => 'token-abc']);

    $this->withHeaders(tokenTestHeaders($user, 'new-install', 'token-abc'))
        ->getJson('/api/config')
        ->assertOk();

    expect(UserDevice::where('device_id', 'old-install')->first()->fcm_token)->toBeNull()
        ->and(UserDevice::where('device_id', 'new-install')->first()->fcm_token)->toBe('token-abc')
        ->and($user->fcmTokens())->toBe(['token-abc']);
});

it('takes a token off another user who signed out of that phone', function () {
    $previous = tokenTestUser();
    $current = tokenTestUser();

    UserDevice::create(['user_id' => $previous->id, 'device_id' => 'shared-phone', 'fcm_token' => 'token-abc']);

    $this->withHeaders(tokenTestHeaders($current, 'shared-phone', 'token-abc'))
        ->getJson('/api/config')
        ->assertOk();

    // Otherwise the previous account's notifications keep arriving on a handset somebody
    // else is now signed in on.
    expect($previous->fcmTokens())->toBe([])
        ->and($current->fcmTokens())->toBe(['token-abc']);
});

it('never hands the same token to a multicast twice', function () {
    $user = tokenTestUser();

    // Rows written before the index existed, or by anything that bypasses the middleware.
    UserDevice::create(['user_id' => $user->id, 'device_id' => 'a', 'fcm_token' => 'token-abc']);
    UserDevice::create(['user_id' => $user->id, 'device_id' => 'b', 'fcm_token' => 'token-abc']);
    UserDevice::create(['user_id' => $user->id, 'device_id' => 'c', 'fcm_token' => 'token-xyz']);

    expect($user->fcmTokens())->toBe(['token-abc', 'token-xyz']);
});

it('keeps one device row however many times the customer signs in on that phone', function () {
    $user = tokenTestUser();
    // This kit's suite runs on the email identifier (phpunit.xml), so sign in with one.
    $user->forceFill([
        'email' => 'device-token@example.com',
        'password' => Hash::make('Password@123#'),
        'verified_at' => now(),
    ])->save();

    $headers = [
        'X-API-TOKEN' => env('APP_X_API_TOKEN'),
        'Accept-Language' => 'en',
        'X-Device-Id' => 'phone-1',
        'X-Platform' => 'android',
        'X-FCM-Token' => 'token-abc',
        'Accept' => 'application/json',
    ];

    foreach (range(1, 3) as $ignored) {
        app('auth')->forgetGuards();
        $this->withHeaders($headers)
            ->postJson('/api/login', ['identifier' => 'device-token@example.com', 'type' => 'email', 'password' => 'Password@123#'])
            ->assertOk();
    }

    // Signing in used to insert a row beside the one the device middleware keeps, so the
    // same token was stored once per sign-in and every push arrived that many times.
    expect(UserDevice::where('user_id', $user->id)->count())->toBe(1)
        ->and($user->fcmTokens())->toBe(['token-abc']);
});
