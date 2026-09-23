<?php

use App\Helpers\FCMHelper;
use App\Helpers\Firebase;
use App\Models\User;
use Spatie\Permission\Models\Role;

/*
 * Firebase is optional. With no service-account JSON on disk the app must stay usable:
 * social sign-in reports itself as unavailable, push turns into a no-op, and every
 * unrelated endpoint behaves exactly as it always did.
 */

function noFirebaseHeaders(array $overrides = []): array
{
    return array_merge([
        'X-API-TOKEN' => env('APP_X_API_TOKEN'),
        'Accept-Language' => 'en',
        'X-Device-Id' => 'firebase-optional-device',
        'X-Platform' => 'web',
        'Accept' => 'application/json',
    ], $overrides);
}

beforeEach(function () {
    Firebase::forget();
    config()->set('firebase.projects.app.credentials', base_path('storage/app/private/does-not-exist.json'));
});

afterEach(fn () => Firebase::forget());

it('reports firebase as unavailable instead of throwing', function () {
    expect(Firebase::available())->toBeFalse()
        ->and(Firebase::auth())->toBeNull()
        ->and(Firebase::messaging())->toBeNull();
});

it('keeps serving /api/config and flags social auth as off', function () {
    $response = $this->withHeaders(noFirebaseHeaders())->getJson('/api/config')->assertOk();

    expect($response->json('data.social_auth_available'))->toBeFalse();
});

it('answers social sign-in with a clear error, not a crash', function () {
    $this->withHeaders(noFirebaseHeaders())
        ->postJson('/api/firebase-login', ['token' => 'whatever', 'provider' => 'google.com'])
        ->assertStatus(503)
        ->assertJsonPath('success', false);
});

it('turns push into a no-op rather than an exception', function () {
    expect(FCMHelper::send('some-token', 'title', 'body'))
        ->toMatchArray(['success' => false])
        ->and(FCMHelper::sendToTopic('users', 'title', 'body'))
        ->toMatchArray(['success' => false]);
});

it('still reaches the identifier-change logic instead of dying on the container', function () {
    $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    // Built directly rather than through the factory: this test only needs a signed-in,
    // verified account, and the factory carries fields that differ between projects.
    $user = new User;
    $user->forceFill([
        'name' => 'Firebase Optional',
        'phone' => '+966500000900',
        'password' => bcrypt('password123'),
        'verified_at' => now(),
        'is_active' => true,
    ])->save();
    $user->assignRole($role);

    // This endpoint used to type-hint Firebase's Auth contract, so with no credentials it
    // 500'd in the container before running a line of its own code. A wrong OTP now gets
    // the endpoint's own 422 — proof the request got all the way in.
    $this->actingAs($user, 'sanctum')
        ->withHeaders(noFirebaseHeaders())
        ->postJson('/api/verify-identifier-change', [
            'new_identifier' => '+966500000901',
            'type' => 'phone',
            'otp' => '000000',
        ])
        ->assertStatus(422);
});
