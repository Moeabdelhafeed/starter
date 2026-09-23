<?php

use App\Models\Language;
use App\Models\Page;
use Database\Seeders\PageSeeder;

/**
 * Pages listed in PROTECTED_PAGES are what a shipped mobile build links to by slug, so
 * the CMS may neither delete them nor rename them out of the list. `phpunit.xml` pins the
 * list to `terms`.
 */
beforeEach(function () {
    Language::firstOrCreate(
        ['code' => 'en'],
        ['name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_active' => true, 'is_default' => true],
    );
});

it('refuses to delete a protected page from the CMS', function () {
    $page = Page::factory()->create(['slug' => 'terms']);

    $this->actingAs(adminUser())->from(route('pages'))
        ->delete(route('pages.destroy', $page))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Page::find($page->id))->not->toBeNull();
});

it('refuses the delete even when the controller is bypassed', function () {
    $page = Page::factory()->create(['slug' => 'terms']);
    $page->saveTranslations(['name' => ['en' => 'Terms']]);

    expect($page->delete())->toBeFalse()
        ->and(Page::find($page->id))->not->toBeNull()
        // The guard runs before HasTranslations' own deleting listener, so a cancelled
        // delete must not have taken the page's copy with it.
        ->and($page->fresh()->getTranslation('name', 'en'))->toBe('Terms');
});

it('skips protected pages in a bulk delete and deletes the rest', function () {
    $protected = Page::factory()->create(['slug' => 'terms']);
    $ordinary = Page::factory()->create(['slug' => 'about-us']);

    $this->actingAs(adminUser())->from(route('pages'))
        ->delete(route('pages.bulk-destroy'), ['ids' => [$protected->id, $ordinary->id]])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Page::find($protected->id))->not->toBeNull()
        ->and(Page::find($ordinary->id))->toBeNull();
});

it('keeps a protected page on its slug when an admin tries to rename it', function () {
    // Renaming is the way out of the list: rename, then delete what is no longer listed.
    $page = Page::factory()->create(['slug' => 'terms']);

    $this->actingAs(adminUser())
        ->put(route('pages.update', $page), [
            'slug' => 'terms-old',
            'is_active' => true,
            'translations' => ['name' => ['en' => 'Terms']],
        ])->assertRedirect();

    expect($page->fresh()->slug)->toBe('terms');
});

it('refuses to deactivate a protected page', function () {
    // An inactive page 404s on the API exactly like a deleted one, so switching it off
    // is the same accident as deleting it — with one click and no confirm dialog.
    $page = Page::factory()->create(['slug' => 'terms', 'is_active' => true]);

    $this->actingAs(adminUser())->from(route('pages'))
        ->put(route('pages.update', $page), [
            'slug' => 'terms',
            'is_active' => false,
            'translations' => ['name' => ['en' => 'Terms']],
        ])->assertRedirect()->assertSessionHas('error');

    expect($page->fresh()->is_active)->toBeTrue();
});

it('still saves the copy of a protected page whose deactivation was refused', function () {
    $page = Page::factory()->create(['slug' => 'terms', 'is_active' => true]);

    $this->actingAs(adminUser())
        ->put(route('pages.update', $page), [
            'slug' => 'terms',
            'is_active' => false,
            'translations' => ['name' => ['en' => 'Reworded Terms']],
        ])->assertRedirect();

    expect($page->fresh()->getTranslation('name', 'en'))->toBe('Reworded Terms')
        ->and($page->fresh()->is_active)->toBeTrue();
});

it('refuses the deactivation even when the controller is bypassed', function () {
    $page = Page::factory()->create(['slug' => 'terms', 'is_active' => true]);

    $page->is_active = false;
    $page->save();

    expect($page->fresh()->is_active)->toBeTrue();
});

it('skips protected pages in a bulk deactivate and switches off the rest', function () {
    $protected = Page::factory()->create(['slug' => 'terms', 'is_active' => true]);
    $ordinary = Page::factory()->create(['slug' => 'about-us', 'is_active' => true]);

    $this->actingAs(adminUser())->from(route('pages'))
        ->put(route('pages.bulk-update'), ['ids' => [$protected->id, $ordinary->id], 'is_active' => false])
        ->assertRedirect()->assertSessionHas('error');

    expect($protected->fresh()->is_active)->toBeTrue()
        ->and($ordinary->fresh()->is_active)->toBeFalse();
});

it('still bulk-activates a protected page without complaining', function () {
    // Only switching *off* is refused; the other direction is the guard's own goal.
    $protected = Page::factory()->create(['slug' => 'terms', 'is_active' => true]);
    $ordinary = Page::factory()->create(['slug' => 'about-us', 'is_active' => false]);

    $this->actingAs(adminUser())->from(route('pages'))
        ->put(route('pages.bulk-update'), ['ids' => [$protected->id, $ordinary->id], 'is_active' => true])
        ->assertRedirect()->assertSessionHas('success');

    expect($ordinary->fresh()->is_active)->toBeTrue();
});

it('lets an unprotected page be deactivated as before', function () {
    $page = Page::factory()->create(['slug' => 'about-us', 'is_active' => true]);

    $this->actingAs(adminUser())->from(route('pages'))
        ->put(route('pages.update', $page), [
            'slug' => 'about-us',
            'is_active' => false,
            'translations' => ['name' => ['en' => 'About']],
        ])->assertRedirect()->assertSessionHas('success');

    expect($page->fresh()->is_active)->toBeFalse();
});

it('deletes an unprotected page as before', function () {
    $page = Page::factory()->create(['slug' => 'about-us']);

    $this->actingAs(adminUser())->from(route('pages'))
        ->delete(route('pages.destroy', $page))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Page::find($page->id))->toBeNull();
});

it('protects nothing when the list is empty', function () {
    config(['features.protected_pages' => '']);
    $page = Page::factory()->create(['slug' => 'terms']);

    expect($page->is_protected)->toBeFalse()
        ->and($page->delete())->toBeTrue();
});

it('seeds every listed page that does not exist yet, and only once', function () {
    (new PageSeeder)->run();

    $page = Page::where('slug', 'terms')->first();

    expect($page)->not->toBeNull()
        ->and($page->is_active)->toBeTrue()
        ->and($page->getTranslation('name', 'en'))->toBe('Terms & Conditions');

    (new PageSeeder)->run();

    expect(Page::where('slug', 'terms')->count())->toBe(1);
});

it('creates a slug added to the list after the fact', function () {
    // What DevSettings does on save: rewrite the list, then run the seeder. The endpoint
    // itself is not exercised over HTTP — it edits the real .env file.
    (new PageSeeder)->run();

    config(['features.protected_pages' => 'terms,refunds']);
    (new PageSeeder)->run();

    expect(Page::where('slug', 'refunds')->first())->not->toBeNull()
        // No copy is shipped for an unknown slug, so it gets a headline to reword.
        ->and(Page::where('slug', 'refunds')->first()->getTranslation('name', 'en'))->toBe('Refunds');
});

it('leaves a page alone when the seeder runs without it in the list', function () {
    // Deleting is the DevSettings action's job, because only it knows which slugs were
    // just removed. The seeder only ever creates — a deploy must not delete content on
    // the strength of a config string it cannot diff, and a blank PROTECTED_PAGES reads
    // as an empty list rather than falling back to the default.
    $page = Page::factory()->create(['slug' => 'terms']);

    config(['features.protected_pages' => '']);
    (new PageSeeder)->run();

    expect(Page::find($page->id))->not->toBeNull();
});

it('deletes a page and its translations once its slug leaves the list', function () {
    // What DevSettings does on save, in order: rewrite the list, then delete what the
    // list dropped. The guard has to let it go by then.
    $page = Page::factory()->create(['slug' => 'refunds']);
    $page->saveTranslations(['name' => ['en' => 'Refunds']]);

    config(['features.protected_pages' => 'terms']);

    expect($page->delete())->toBeTrue()
        ->and(Page::find($page->id))->toBeNull()
        ->and($page->translations()->count())->toBe(0);
});

it('leaves a page the admin already wrote alone', function () {
    $page = Page::factory()->create(['slug' => 'terms']);
    $page->saveTranslations(['name' => ['en' => 'Our Terms']]);

    (new PageSeeder)->run();

    expect($page->fresh()->getTranslation('name', 'en'))->toBe('Our Terms');
});
