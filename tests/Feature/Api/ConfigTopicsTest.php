<?php

use App\Helpers\FcmTopics;
use App\Models\Language;

/**
 * `GET /api/config` carries the FCM topic registry.
 *
 * Topic names are per-install (the `FCM_TOPICS` env, plus a variant per active language),
 * and the admin picks one per notification template. A client that hardcoded "users_en"
 * would silently stop receiving anything the day a project renamed a topic or added a
 * language, so the list is published rather than assumed.
 */
/** Two active languages, so the per-language variants have something to be built from. */
function configTopicsLanguages(): void
{
    foreach ([['en', 'English', 'ltr'], ['ar', 'العربية', 'rtl']] as [$code, $name, $direction]) {
        Language::firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'native_name' => $name, 'direction' => $direction, 'is_active' => true, 'is_default' => $code === 'en'],
        );
    }
}

function configHeaders(): array
{
    return [
        'X-API-TOKEN' => env('APP_X_API_TOKEN'),
        'Accept-Language' => 'en',
        'X-Device-Id' => 'config-topics-device',
        'X-Platform' => 'web',
        'Accept' => 'application/json',
    ];
}

it('publishes only the per-language topics a device can subscribe to', function () {
    configTopicsLanguages();

    $topics = collect(
        $this->withHeaders(configHeaders())->getJson('/api/config')->assertOk()->json('data.fcm_topics')
    );

    expect($topics)->not->toBeEmpty()
        // Every published topic names a language. A device has one language at a time, and
        // one subscribed to both `all` and `all_ar` would receive every broadcast twice.
        ->and($topics->pluck('lang')->filter(fn ($lang) => $lang === null))->toBeEmpty()
        ->and($topics->pluck('name'))->toContain('users_en', 'users_ar', 'guests_en')
        // Neither the bases nor `all`, which is a choice in the CMS picker and not a topic
        // anything can subscribe to.
        ->and($topics->pluck('name'))->not->toContain('all', 'all_en', 'users', 'guests');

    foreach ($topics as $topic) {
        expect($topic)->toHaveKeys(['name', 'base', 'lang']);
    }
});

it('marks the language-agnostic topic apart from its per-language variants', function () {
    configTopicsLanguages();

    $topics = collect(
        $this->withHeaders(configHeaders())->getJson('/api/config')->assertOk()->json('data.fcm_topics')
    );

    // The base itself is not offered; its variants are, and each one names its base so the
    // client can pick the right one for the device's state.
    expect($topics->firstWhere('name', FcmTopics::USERS))->toBeNull();

    foreach (Language::activeCodes() as $code) {
        $variant = $topics->firstWhere('name', FcmTopics::USERS.'_'.$code);

        expect($variant)->not->toBeNull()
            ->and($variant['lang'])->toBe($code)
            ->and($variant['base'])->toBe(FcmTopics::USERS);
    }
});

it('grows the published list when a project configures its own topics', function () {
    configTopicsLanguages();
    config(['features.fcm_topics' => 'guests,users,offers']);

    $names = collect(
        $this->withHeaders(configHeaders())->getJson('/api/config')->assertOk()->json('data.fcm_topics')
    )->pluck('name');

    foreach (Language::activeCodes() as $code) {
        expect($names)->toContain('offers_'.$code);
    }

    expect($names)->not->toContain('offers');
});

it('never publishes the all option, which is not a topic', function () {
    configTopicsLanguages();
    config(['features.fcm_topics' => 'guests,users']);

    $topics = collect(
        $this->withHeaders(configHeaders())->getJson('/api/config')->assertOk()->json('data.fcm_topics')
    );

    // A device subscribing to `all` would be subscribing to a name the studio never sends
    // to: choosing `all` in the CMS fans out to every base's variants instead.
    expect($topics->pluck('name'))->not->toContain(FcmTopics::ALL, FcmTopics::ALL.'_en', FcmTopics::ALL.'_ar')
        ->and($topics->pluck('base')->unique()->values()->all())->toBe(['guests', 'users']);
});

it('drops all from a configured topic list instead of making a topic of it', function () {
    configTopicsLanguages();
    config(['features.fcm_topics' => 'all,users']);

    $names = collect(
        $this->withHeaders(configHeaders())->getJson('/api/config')->assertOk()->json('data.fcm_topics')
    )->pluck('name');

    // Typed into the list by an admin, it is still the picker's option — not a shelf.
    expect($names->all())->toBe(['users_en', 'users_ar']);
});

it('falls back to the bare bases when the install has no active languages', function () {
    // Nothing to make a variant from, so the bases are the only topics that exist — and
    // sendTargets() delivers straight to them, so a device subscribing to one is reached.
    Language::query()->update(['is_active' => false]);

    $topics = collect(
        $this->withHeaders(configHeaders())->getJson('/api/config')->assertOk()->json('data.fcm_topics')
    );

    expect($topics->pluck('name'))->toContain(FcmTopics::USERS, FcmTopics::GUESTS)
        ->and($topics->pluck('name'))->not->toContain(FcmTopics::ALL)
        ->and($topics->pluck('lang')->unique()->values()->all())->toBe([null]);
});
