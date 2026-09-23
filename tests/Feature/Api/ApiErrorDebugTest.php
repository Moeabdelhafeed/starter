<?php

use App\Helpers\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;

/*
 * A crashed API request has to be readable from the response itself while testing —
 * the failing SQL included — and has to say nothing at all once debugging is off.
 */

function boomHeaders(): array
{
    return [
        'X-API-TOKEN' => env('APP_X_API_TOKEN'),
        'Accept-Language' => 'en',
        'X-Device-Id' => 'boom-test-device',
        'X-Platform' => 'web',
        'Accept' => 'application/json',
    ];
}

beforeEach(function () {
    Route::middleware('api')->get('/api/__boom', function () {
        throw new QueryException(
            'mysql',
            'select * from users where nope = ?',
            ['x'],
            new PDOException("SQLSTATE[42S22]: Column not found: 1054 Unknown column 'nope'"),
        );
    });
});

it('returns the failing sql and bindings when debugging is exposed', function () {
    config()->set('app.debug', true);

    $response = $this->withHeaders(boomHeaders())->getJson('/api/__boom')->assertStatus(500);

    expect($response->json('success'))->toBeFalse()
        ->and($response->json('debug.exception'))->toBe(QueryException::class)
        ->and($response->json('debug.sql'))->toBe('select * from users where nope = ?')
        ->and($response->json('debug.bindings'))->toBe(['x'])
        ->and($response->json('debug.message'))->toContain('Unknown column')
        ->and($response->json('debug.file'))->toContain('.php:')
        ->and($response->json('message'))->toContain('Unknown column');
});

it('keeps the envelope every other api error uses', function () {
    config()->set('app.debug', true);

    $response = $this->withHeaders(boomHeaders())->getJson('/api/__boom')->assertStatus(500);

    expect(array_keys($response->json()))->toContain('success', 'message', 'errors', 'data', 'debug');
});

it('says nothing about the internals when debugging is off', function () {
    config()->set('app.debug', false);
    config()->set('app.is_testing', false);

    $response = $this->withHeaders(boomHeaders())->getJson('/api/__boom')->assertStatus(500);

    expect($response->json('message'))->toBe('Server error')
        ->and($response->json('debug'))->toBeNull()
        ->and(json_encode($response->json()))->not->toContain('SQLSTATE')
        ->and(json_encode($response->json()))->not->toContain('nope');
});

it('leaves 4xx responses alone', function () {
    config()->set('app.debug', true);

    // 401 from the framework, not rewritten into a 500 debug payload.
    $this->getJson('/api/user')->assertUnauthorized();
});

it('exposes debug via IS_TESTING even when app.debug is off', function () {
    config()->set('app.debug', false);
    config()->set('app.is_testing', true);

    expect(ApiResponse::exposesDebug())->toBeTrue();
});
