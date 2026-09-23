<?php

use App\Helpers\FcmTopics;
use App\Models\Language;

/**
 * A base topic is a shorthand, not an address, and `all` is not even a topic.
 *
 * Devices subscribe to language variants only (`/api/config` publishes nothing else), so a
 * push sent to the bare `users` would reach not one phone. Firing a base has to fan out to
 * every variant of it, and firing the picker's `all` option to every variant of every base
 * — each carrying that language's copy.
 */
function fanOutLanguages(array $codes = ['en', 'ar']): void
{
    foreach ($codes as $i => $code) {
        Language::firstOrCreate(
            ['code' => $code],
            ['name' => $code, 'native_name' => $code, 'direction' => $code === 'ar' ? 'rtl' : 'ltr',
                'is_active' => true, 'is_default' => $i === 0],
        );
    }
}

function fanOutSetTopics(string $value): void
{
    config(['features.fcm_topics' => $value]);
}

it('sends a base topic to every language variant of that base alone', function () {
    fanOutLanguages();
    fanOutSetTopics('guests,users');

    // "users" means that audience in their own language — the guests never hear it.
    expect(FcmTopics::sendTargets(FcmTopics::USERS))
        ->toBe(['users_en' => 'en', 'users_ar' => 'ar']);
});

it('sends the all option to every base in every language', function () {
    fanOutLanguages();
    fanOutSetTopics('guests,users');

    // `all` is a choice in the picker, not a topic: nothing subscribes to it, so it can only
    // mean the full cross-product of the topics that do exist.
    expect(FcmTopics::sendTargets(FcmTopics::ALL))
        ->toBe(['guests_en' => 'en', 'guests_ar' => 'ar', 'users_en' => 'en', 'users_ar' => 'ar']);
});

it('keeps all out of the real topics whatever the install configures', function () {
    fanOutLanguages();
    fanOutSetTopics('all,guests,users');

    // Typed into FCM_TOPICS it would otherwise mint `all_en`/`all_ar` topics no device has.
    expect(FcmTopics::bases())->toBe(['guests', 'users'])
        ->and(FcmTopics::all())->not->toContain('all', 'all_en', 'all_ar')
        ->and(collect(FcmTopics::published())->pluck('name'))->not->toContain('all', 'all_en')
        // The picker still offers it, first, because it is the broadest choice there is.
        ->and(FcmTopics::selectable())->toContain('all')
        ->and(collect(FcmTopics::structured())->first()['name'])->toBe('all');
});

it('sends a language topic to itself alone', function () {
    fanOutLanguages();

    // Already specific: an Arabic-only announcement must not also go out in English.
    expect(FcmTopics::sendTargets('users_ar'))->toBe(['users_ar' => 'ar']);
});

it('splits a topic into its base and language', function () {
    fanOutLanguages();

    expect(FcmTopics::parse('guests_ar'))->toBe(['guests', 'ar'])
        ->and(FcmTopics::parse('guests'))->toBe(['guests', null]);
});

it('keeps a base that contains an underscore in one piece', function () {
    fanOutLanguages();
    fanOutSetTopics('new_arrivals');

    // Splitting on the last underscore would read this as base `new`, language `arrivals`.
    expect(FcmTopics::parse('new_arrivals_ar'))->toBe(['new_arrivals', 'ar'])
        ->and(FcmTopics::sendTargets('new_arrivals'))
        ->toBe(['new_arrivals_en' => 'en', 'new_arrivals_ar' => 'ar']);
});

it('sends to the bases themselves when no languages are active', function () {
    Language::query()->update(['is_active' => false]);
    fanOutSetTopics('guests,users');

    // Nothing to fan out to, and the config publishes the bases in that case — so a base is
    // a real address and a send must still arrive.
    expect(FcmTopics::sendTargets(FcmTopics::USERS))->toBe(['users' => null])
        ->and(FcmTopics::sendTargets(FcmTopics::ALL))->toBe(['guests' => null, 'users' => null]);
});
