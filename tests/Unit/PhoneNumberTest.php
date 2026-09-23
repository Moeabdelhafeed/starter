<?php

use App\Helpers\PhoneNumber;

it('canonicalises an international number to E.164', function (string $input) {
    expect(PhoneNumber::normalize($input))->toBe('+966501234567');
})->with([
    'bare country code' => '966501234567',
    'plus prefixed' => '+966501234567',
    'spaced' => '+966 50 123 4567',
    'punctuated' => '+966-50-123-4567',
    'padded' => '  966501234567  ',
]);

it('rejects a number that does not carry its own country code', function (string $input) {
    // The whole point of the helper: parsing against a default region is how the
    // same human ends up as three rows, one per notation they happened to type.
    expect(PhoneNumber::normalize($input))->toBeNull();
})->with([
    'national with trunk zero' => '0501234567',
    'national without trunk zero' => '501234567',
    'short local' => '77123456',
]);

it('rejects input that is not a phone number at all', function (?string $input) {
    expect(PhoneNumber::normalize($input))->toBeNull();
})->with([
    'null' => null,
    'empty' => '',
    'letters' => 'not-a-number',
    'too long' => '+9665012345678901234',
]);

it('reports the country a normalised number belongs to', function () {
    expect(PhoneNumber::region('+966501234567'))->toBe('SA');
});
