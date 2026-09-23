<?php

use App\Helpers\AuthIdentity;
use App\Rules\AllowedEmailDomain;
use App\Rules\AllowedPhoneCountry;
use Tests\TestCase;

// AuthIdentity answers everything from config(), so it needs a booted application —
// but never the database.
uses(TestCase::class);

it('keeps only email and phone as login identifiers', function () {
    config()->set('auth.identifiers', ['email', 'phone', 'username', 'nickname']);

    expect(AuthIdentity::identifiers())->toBe(['email', 'phone']);
});

it('falls back to email when nothing usable is configured', function () {
    // An empty AUTH_IDENTIFIERS would otherwise leave a project with no way to log in.
    config()->set('auth.identifiers', ['username']);

    expect(AuthIdentity::identifiers())->toBe(['email']);
});

it('counts a field as present when it is an identifier or an enabled extra', function () {
    config()->set('auth.identifiers', ['email']);
    config()->set('auth.fields.phone', true);
    config()->set('auth.fields.username', false);

    expect(AuthIdentity::hasField('email'))->toBeTrue()
        ->and(AuthIdentity::hasField('phone'))->toBeTrue()
        ->and(AuthIdentity::hasField('username'))->toBeFalse();
});

it('offers username as a declarable type only while that field is enabled', function () {
    config()->set('auth.identifiers', ['email']);

    config()->set('auth.fields.username', false);
    expect(AuthIdentity::types())->toBe(['email']);

    config()->set('auth.fields.username', true);
    expect(AuthIdentity::types())->toBe(['email', 'username']);
});

it('normalises a value by its declared type rather than by guessing', function () {
    expect(AuthIdentity::normalize('  Jane@Example.COM ', 'email'))->toBe('jane@example.com')
        ->and(AuthIdentity::normalize('+966 50 123 4567', 'phone'))->toBe('+966501234567')
        ->and(AuthIdentity::normalize('JaneDoe', 'username'))->toBe('JaneDoe');
});

it('returns null for a value that is not valid for its declared type', function () {
    // Callers surface this as a validation error; silently accepting a country-less
    // number is exactly the bug the declared-type rule exists to prevent.
    expect(AuthIdentity::normalize('0501234567', 'phone'))->toBeNull()
        ->and(AuthIdentity::normalize('   ', 'email'))->toBeNull();
});

it('scopes the uniqueness rule to the row being edited', function () {
    config()->set('auth.allowed_email_domains', 'all');

    expect(AuthIdentity::emailRule(required: true))->toContain('unique:users,email')
        ->and(AuthIdentity::emailRule(required: false, excludeId: 7))
        ->toContain('unique:users,email,7')
        ->toContain('nullable');
});

it('only attaches the domain allow-list rule when one is configured', function () {
    config()->set('auth.allowed_email_domains', 'all');
    $unrestricted = AuthIdentity::emailRule(required: true);

    config()->set('auth.allowed_email_domains', 'example.com');
    $restricted = AuthIdentity::emailRule(required: true);

    expect(collect($unrestricted)->contains(fn ($rule) => $rule instanceof AllowedEmailDomain))->toBeFalse()
        ->and(collect($restricted)->contains(fn ($rule) => $rule instanceof AllowedEmailDomain))->toBeTrue();
});

it('always enforces the country allow-list on a phone', function () {
    // Unlike the email rule this one is unconditional — AllowedPhoneCountry itself
    // treats "all" as unrestricted.
    expect(collect(AuthIdentity::phoneRule(required: true))
        ->contains(fn ($rule) => $rule instanceof AllowedPhoneCountry))->toBeTrue();
});

it('drops the uniqueness check from the otp login rules', function () {
    config()->set('auth.allowed_email_domains', 'all');

    // OTP login expects the identifier to already exist, so a `unique` rule would
    // reject every returning user.
    expect(AuthIdentity::otpLoginRule('email'))->not->toContain('unique:users,email')
        ->and(AuthIdentity::otpLoginRule('email'))->toContain('email');
});
