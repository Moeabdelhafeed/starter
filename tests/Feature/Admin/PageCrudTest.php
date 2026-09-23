<?php

use App\Models\Language;
use App\Models\Page;

beforeEach(function () {
    Language::firstOrCreate(
        ['code' => 'en'],
        ['name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_active' => true, 'is_default' => true],
    );
});

it('creates a page with its translated name and content', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('pages.store'), [
        'slug' => 'about-us',
        'is_active' => true,
        'translations' => [
            'name' => ['en' => 'About Us'],
            'content' => ['en' => '<p>Who we are.</p>'],
        ],
    ])->assertRedirect();

    $page = Page::where('slug', 'about-us')->first();

    expect($page)->not->toBeNull()
        ->and($page->getTranslation('name', 'en'))->toBe('About Us')
        ->and($page->getTranslation('content', 'en'))->toBe('<p>Who we are.</p>');
});

it('refuses a slug another page already owns', function () {
    $admin = adminUser();
    Page::factory()->create(['slug' => 'about-us']);

    $this->actingAs($admin)->from(route('pages'))->post(route('pages.store'), [
        'slug' => 'about-us',
        'translations' => ['name' => ['en' => 'Duplicate']],
    ])->assertSessionHasErrors('slug');

    expect(Page::where('slug', 'about-us')->count())->toBe(1);
});

it('renders an active page publicly by slug', function () {
    $page = Page::factory()->create(['slug' => 'terms', 'is_active' => true]);
    $page->saveTranslations(['name' => ['en' => 'Terms'], 'content' => ['en' => '<p>Legal.</p>']]);

    $this->get(route('public_page.show', 'terms'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('Page/Show')
            ->where('page.slug', 'terms')
            ->where('page.name', 'Terms')
            ->where('page.content', '<p>Legal.</p>')
        );
});

it('hides an inactive page from the public route', function () {
    Page::factory()->inactive()->create(['slug' => 'draft']);

    $this->get(route('public_page.show', 'draft'))->assertNotFound();
});

it('404s an unknown public slug', function () {
    $this->get(route('public_page.show', 'no-such-page'))->assertNotFound();
});

it('strips executable markup from page content but keeps ordinary formatting', function () {
    // Page content is rendered with v-html on the admin panel's own origin, so a
    // stored <script> would be same-origin XSS against every admin who opens it.
    $admin = adminUser();

    $hostile = '<p>Hello <strong>world</strong></p>'
        .'<script>alert(1)</script>'
        .'<img src="x" onerror="alert(1)">'
        .'<a href="javascript:alert(1)">click</a>'
        .'<a href="https://example.com">safe link</a>';

    $this->actingAs($admin)->post(route('pages.store'), [
        'slug' => 'hostile',
        'translations' => [
            'name' => ['en' => 'Hostile'],
            'content' => ['en' => $hostile],
        ],
    ])->assertRedirect();

    // Read the stored value, not the response: the sanitiser runs on write, and a
    // page that only looked clean on the way back out would still be poisoned.
    $stored = Page::where('slug', 'hostile')->first()->getTranslation('content', 'en');

    expect($stored)
        ->not->toContain('<script')
        ->not->toContain('alert(1)')
        ->not->toContain('onerror')
        ->not->toContain('javascript:')
        // …while the formatting an author actually needs survives untouched.
        ->toContain('<p>Hello <strong>world</strong></p>')
        ->toContain('<a href="https://example.com">safe link</a>');
});

it('deactivates exactly the submitted pages in bulk', function () {
    $admin = adminUser();
    $targeted = Page::factory()->create();
    $untouched = Page::factory()->create();

    $this->actingAs($admin)->put(route('pages.bulk-update'), [
        'ids' => [$targeted->id],
        'is_active' => false,
    ])->assertRedirect();

    expect($targeted->fresh()->is_active)->toBeFalse()
        ->and($untouched->fresh()->is_active)->toBeTrue();
});

it('drops a page and its translations on delete', function () {
    $admin = adminUser();
    $page = Page::factory()->create();
    $page->saveTranslations(['name' => ['en' => 'Doomed']]);

    $this->actingAs($admin)->delete(route('pages.destroy', $page))->assertRedirect();

    expect(Page::whereKey($page->id)->exists())->toBeFalse();
    $this->assertDatabaseMissing('model_translations', [
        'translatable_type' => Page::class,
        'translatable_id' => $page->id,
    ]);
});
