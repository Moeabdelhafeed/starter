<?php

use App\Models\AppSetting;
use App\Models\Language;
use App\Models\Page;
use App\Models\Role;
use App\Models\TranslationKey;
use App\Models\TranslationValue;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);

    Language::firstOrCreate(
        ['code' => 'en'],
        ['name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_active' => true, 'is_default' => true],
    );

    Language::firstOrCreate(
        ['code' => 'ar'],
        ['name' => 'Arabic', 'native_name' => 'العربية', 'direction' => 'rtl', 'is_active' => true, 'is_default' => false],
    );
});

it('answers every public read endpoint in the standard envelope', function (string $endpoint) {
    $response = $this->withHeaders(apiHeaders())->getJson($endpoint)->assertOk();

    expect($response->json())->toHaveKeys(['success', 'message', 'errors', 'data'])
        ->and($response->json('success'))->toBeTrue();
})->with(['/api/pages', '/api/app-settings', '/api/languages', '/api/translations']);

it('lists only active pages', function () {
    Page::factory()->create(['slug' => 'published']);
    Page::factory()->inactive()->create(['slug' => 'draft']);

    $slugs = collect(
        $this->withHeaders(apiHeaders())->getJson('/api/pages')->assertOk()->json('data')
    )->pluck('slug');

    expect($slugs->all())->toBe(['published']);
});

it('returns a single active page by slug in the documented shape', function () {
    $page = Page::factory()->create(['slug' => 'about-us']);
    $page->saveTranslations(['name' => ['en' => 'About Us'], 'content' => ['en' => '<p>Hi</p>']]);

    $this->withHeaders(apiHeaders())->getJson('/api/pages/about-us')
        ->assertOk()
        ->assertJsonStructure(['data' => ['id', 'slug', 'name', 'content', 'image']])
        ->assertJsonPath('data.name', 'About Us')
        ->assertJsonPath('data.content', '<p>Hi</p>');
});

it('404s an inactive or unknown page slug in the standard envelope', function (string $slug) {
    Page::factory()->inactive()->create(['slug' => 'draft']);

    $response = $this->withHeaders(apiHeaders())->getJson('/api/pages/'.$slug);

    expect($response->getStatusCode())->toBe(404)
        ->and($response->json())->toHaveKeys(['success', 'message', 'errors', 'data'])
        ->and($response->json('success'))->toBeFalse();
})->with(['draft', 'no-such-page']);

it('serves a page in the requested language', function () {
    $page = Page::factory()->create(['slug' => 'about-us']);
    $page->saveTranslations(['name' => ['en' => 'About Us', 'ar' => 'من نحن']]);

    $this->withHeaders(apiHeaders(['Accept-Language' => 'ar']))->getJson('/api/pages/about-us')
        ->assertOk()
        ->assertJsonPath('data.name', 'من نحن');
});

it('falls back to the default language when a page has no translation for the requested one', function () {
    // A half-translated CMS is the normal state of a live app; a missing Arabic
    // title has to read as English, never as an empty heading.
    $page = Page::factory()->create(['slug' => 'about-us']);
    $page->saveTranslations(['name' => ['en' => 'About Us']]);

    $this->withHeaders(apiHeaders(['Accept-Language' => 'ar']))->getJson('/api/pages/about-us')
        ->assertOk()
        ->assertJsonPath('data.name', 'About Us');
});

it('groups active app settings under every block type and drops inactive rows', function () {
    $visible = AppSetting::factory()->ofType('social')->create(['url' => 'https://example.com/social']);
    $visible->saveTranslations(['text' => ['en' => 'Follow us', 'ar' => 'تابعنا']]);
    AppSetting::factory()->ofType('social')->inactive()->create();

    $response = $this->withHeaders(apiHeaders(['Accept-Language' => 'ar']))
        ->getJson('/api/app-settings')->assertOk();

    // Every block type is always present, so a client can render a section without
    // checking whether the key exists.
    expect(array_keys($response->json('data')))->toBe(AppSetting::TYPES);

    $response->assertJsonCount(1, 'data.social')
        ->assertJsonPath('data.social.0.text', 'تابعنا')
        ->assertJsonPath('data.social.0.url', 'https://example.com/social')
        ->assertJsonCount(0, 'data.contact');
});

it('lists only active languages', function () {
    Language::factory()->inactive()->create(['code' => 'fr']);

    $codes = collect($this->withHeaders(apiHeaders())->getJson('/api/languages')->assertOk()->json('data'))
        ->pluck('code');

    expect($codes->all())->toEqualCanonicalizing(['en', 'ar'])
        ->and($codes)->not->toContain('fr');
});

it('returns translations for the requested locale nested by sub group', function () {
    $key = TranslationKey::factory()->create(['key' => 'welcome', 'group' => 'app', 'sub_group' => 'home']);
    TranslationValue::factory()->forKey($key)->locale('en')->create(['value' => 'Welcome']);
    TranslationValue::factory()->forKey($key)->locale('ar')->create(['value' => 'أهلا']);

    $this->withHeaders(apiHeaders(['Accept-Language' => 'ar']))->getJson('/api/translations?group=app')
        ->assertOk()
        ->assertJsonPath('data.group', 'app')
        ->assertJsonPath('data.locale', 'ar')
        ->assertJsonPath('data.translations.home.welcome', 'أهلا');
});

it('returns an empty string for a translation key the requested locale has not been filled in for', function () {
    // Unlike the model-translation fallback above, the string table returns "" rather
    // than the default language — the client is expected to ship its own bundled copy.
    $key = TranslationKey::factory()->create(['key' => 'welcome', 'group' => 'app', 'sub_group' => 'home']);
    TranslationValue::factory()->forKey($key)->locale('en')->create(['value' => 'Welcome']);

    $this->withHeaders(apiHeaders(['Accept-Language' => 'ar']))->getJson('/api/translations?group=app')
        ->assertOk()
        ->assertJsonPath('data.translations.home.welcome', '');
});

it('returns the whole list when per_page is absent or "all"', function (?string $perPage) {
    Page::factory()->count(3)->create();

    $query = $perPage === null ? '' : '?per_page='.$perPage;

    $response = $this->withHeaders(apiHeaders())->getJson('/api/pages'.$query)->assertOk();

    expect($response->json('data'))->toHaveCount(3);
})->with([null, 'all']);

it('paginates a list endpoint when per_page names a page size', function () {
    Page::factory()->count(3)->create();

    $this->withHeaders(apiHeaders())->getJson('/api/pages?per_page=2')
        ->assertOk()
        ->assertJsonPath('data.per_page', 2)
        ->assertJsonPath('data.total', 3)
        ->assertJsonCount(2, 'data.data');
});
