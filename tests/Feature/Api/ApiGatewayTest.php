<?php

use App\Models\Language;
use App\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);

    Language::firstOrCreate(
        ['code' => 'en'],
        ['name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_active' => true, 'is_default' => true],
    );
});

it('refuses a request with no X-API-TOKEN', function () {
    $headers = apiHeaders();
    unset($headers['X-API-TOKEN']);

    $this->withHeaders($headers)->getJson('/api/config')
        ->assertStatus(401)
        ->assertJsonPath('success', false);
});

it('refuses a request with the wrong X-API-TOKEN', function () {
    $this->withHeaders(apiHeaders(['X-API-TOKEN' => 'not-the-token']))
        ->getJson('/api/config')
        ->assertStatus(401)
        ->assertJsonPath('success', false);
});

it('refuses a token that only shares a prefix with the real one', function () {
    $prefix = substr((string) config('app.x_api_token'), 0, 4);

    $this->withHeaders(apiHeaders(['X-API-TOKEN' => $prefix]))
        ->getJson('/api/config')
        ->assertStatus(401);
});

it('accepts the configured X-API-TOKEN', function () {
    $this->withHeaders(apiHeaders())->getJson('/api/config')->assertOk();
});

it('answers every error in the standard envelope', function () {
    // A client parsing `data`/`errors` must not have to special-case which
    // layer rejected it, so unknown routes and bad verbs use the same shape.
    $unknown = $this->withHeaders(apiHeaders())->getJson('/api/no-such-endpoint');

    expect($unknown->getStatusCode())->toBe(404)
        ->and($unknown->json())->toHaveKeys(['success', 'message', 'errors', 'data'])
        ->and($unknown->json('success'))->toBeFalse();
});

it('answers a wrong http verb in the standard envelope', function () {
    $response = $this->withHeaders(apiHeaders())->putJson('/api/config');

    expect($response->getStatusCode())->toBe(405)
        ->and($response->json())->toHaveKeys(['success', 'message', 'errors', 'data']);
});

it('answers a missing bearer token in the standard envelope', function () {
    $response = $this->withHeaders(apiHeaders())->postJson('/api/logout');

    expect($response->getStatusCode())->toBe(401)
        ->and($response->json())->toHaveKeys(['success', 'message', 'errors', 'data'])
        ->and($response->json('success'))->toBeFalse();
});

it('reports the feature flags the mobile client branches on', function () {
    $this->withHeaders(apiHeaders())->getJson('/api/config')
        ->assertOk()
        ->assertJsonStructure(['data' => ['app_users', 'app_guests', 'auth_mode']]);
});

it('refuses content provisioning writes when seeding is off', function () {
    // These endpoints are authenticated only by the shared X-API-TOKEN, which
    // ships inside every mobile binary — so on a live install they must be shut.
    config()->set('features.content_seeding', false);

    $this->withHeaders(apiHeaders())
        ->postJson('/api/translations', ['sub_group' => 'home', 'translations' => ['hi' => 'Hi']])
        ->assertStatus(403);

    $this->withHeaders(apiHeaders())
        ->deleteJson('/api/translations', ['sub_group' => 'home', 'key' => 'hi'])
        ->assertStatus(403);
})->skip(fn () => ! config('features.translations'), 'translations feature disabled');

it('allows content provisioning writes while seeding is on', function () {
    // Deliberately independent of IS_TESTING: a production install is seeded once with
    // this flag on, then it goes back off.
    config()->set('app.is_testing', false);
    config()->set('features.content_seeding', true);

    $this->withHeaders(apiHeaders())
        ->postJson('/api/translations', ['sub_group' => 'home', 'translations' => ['hi' => 'Hi']])
        ->assertSuccessful();
})->skip(fn () => ! config('features.translations'), 'translations feature disabled');

it('reads translations with both flags off', function () {
    config()->set('app.is_testing', false);
    config()->set('features.content_seeding', false);

    $this->withHeaders(apiHeaders())->getJson('/api/translations')->assertOk();
})->skip(fn () => ! config('features.translations'), 'translations feature disabled');
