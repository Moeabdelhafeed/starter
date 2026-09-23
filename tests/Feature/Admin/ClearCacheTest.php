<?php

use App\Models\Language;
use Illuminate\Support\Facades\Cache;

it('refuses an anonymous visitor', function () {
    $this->post(route('cache.clear'))->assertRedirect(route('login'));
});

it('flushes the cache store for a signed-in admin', function () {
    Cache::put('something', 'value', 600);

    $this->actingAs(adminUser())->from(route('dashboard'))
        ->post(route('cache.clear'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success');

    expect(Cache::get('something'))->toBeNull();
});

it('leaves the data the cache was derived from alone', function () {
    // Nothing cached here is a source of truth: flushing costs a rebuild, never a row.
    $language = Language::firstOrCreate(
        ['code' => 'en'],
        ['name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_active' => true, 'is_default' => true],
    );

    $this->actingAs(adminUser())->post(route('cache.clear'))->assertRedirect();

    expect(Language::find($language->id))->not->toBeNull()
        ->and(Language::active()->count())->toBeGreaterThan(0);
});
