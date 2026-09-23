<?php

use App\Models\Language;
use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);

    Language::firstOrCreate(
        ['code' => 'en'],
        ['name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_active' => true, 'is_default' => true],
    );

    // routes/api.php only branches on auth.mode for /register vs /verify-login;
    // /login is registered in both modes and picks the OTP path at request time.
    config()->set('auth.mode', 'otp');
    config()->set('auth.identifiers', ['phone']);
});

it('stores the OTP-login phone in E.164 so the same number is one account', function () {
    $this->withHeaders(apiHeaders())->postJson('/api/login', [
        'identifier' => '213555000000',
        'type' => 'phone',
    ])->assertOk()->assertJsonPath('data.identifier', '+213555000000');

    expect(User::where('phone', '+213555000000')->exists())->toBeTrue()
        ->and(User::where('phone', '213555000000')->exists())->toBeFalse();

    $this->withHeaders(apiHeaders())->postJson('/api/login', [
        'identifier' => '+213555000000',
        'type' => 'phone',
    ])->assertOk();

    expect(User::whereNotNull('phone')->count())->toBe(1);
});
