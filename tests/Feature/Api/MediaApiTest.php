<?php

use App\Models\Language;
use App\Models\MediaItem;
use App\Models\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);

    Language::firstOrCreate(
        ['code' => 'en'],
        ['name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_active' => true, 'is_default' => true],
    );

    Storage::fake('public');

    // The write endpoints sit behind `testing-only`; that gate itself is covered
    // in ApiGatewayTest, so here it is just opened.
    // The media writes are seeding endpoints; IS_TESTING no longer opens them.
    config()->set('features.content_seeding', true);
});

it('stores an uploaded image and returns it in the keyed media shape', function () {
    $response = $this->withHeaders(apiHeaders())->post('/api/media', [
        'group' => 'web',
        'sub_group' => 'auth',
        'key' => 'login_image',
        'file' => UploadedFile::fake()->image('login.png', 32, 32),
    ]);

    $response->assertOk()
        ->assertJsonPath('data.group', 'web')
        ->assertJsonPath('data.sub_group', 'auth')
        ->assertJsonPath('data.key', 'login_image')
        ->assertJsonPath('data.type', 'image')
        ->assertJsonStructure([
            'data' => ['group', 'sub_group', 'key', 'type', 'image' => ['id', 'url', 'type', 'blurhash', 'image_api']],
        ]);

    Storage::disk('public')->assertExists($response->json('data.image.url'));
});

it('replaces the asset at an existing group, sub_group and key instead of adding a second one', function () {
    $upload = fn () => $this->withHeaders(apiHeaders())->post('/api/media', [
        'group' => 'web',
        'sub_group' => 'auth',
        'key' => 'login_image',
        'file' => UploadedFile::fake()->image('login.png', 32, 32),
    ]);

    $first = $upload()->assertOk();
    $second = $upload()->assertOk();

    expect(MediaItem::where('key', 'login_image')->count())->toBe(1)
        ->and($second->json('data.image.url'))->not->toBe($first->json('data.image.url'));

    // The superseded file is deleted, not orphaned on disk.
    Storage::disk('public')->assertMissing($first->json('data.image.url'));
    Storage::disk('public')->assertExists($second->json('data.image.url'));
});

it('rejects an image above the configured size cap', function () {
    config()->set('dynamic-storage.max_image_kb', 100);

    $this->withHeaders(apiHeaders())->post('/api/media', [
        'group' => 'web',
        'sub_group' => 'auth',
        'key' => 'oversized',
        'file' => UploadedFile::fake()->image('big.png', 32, 32)->size(500),
    ])->assertStatus(422)
        ->assertJsonStructure(['errors' => ['file']]);

    expect(MediaItem::where('key', 'oversized')->exists())->toBeFalse();
});

it('rejects an upload whose extension is not on the allowlist', function (string $filename) {
    // The public disk serves these back from the app's own origin, so anything
    // executable or renderable as HTML must never land there.
    $this->withHeaders(apiHeaders())->post('/api/media', [
        'group' => 'web',
        'sub_group' => 'general',
        'key' => 'payload',
        'file' => UploadedFile::fake()->create($filename, 8),
    ])->assertStatus(422)
        ->assertJsonStructure(['errors' => ['file']]);

    expect(MediaItem::where('key', 'payload')->exists())->toBeFalse();
})->with(['shell.php', 'page.html', 'logo.svg']);

it('accepts a document type that is on the allowlist', function () {
    // The counterweight to the case above: the allowlist has to still let real
    // documents through, or the rejection test would pass on a broken endpoint.
    $this->withHeaders(apiHeaders())->post('/api/media', [
        'group' => 'web',
        'sub_group' => 'legal',
        'key' => 'terms_pdf',
        'file' => UploadedFile::fake()->create('terms.pdf', 8, 'application/pdf'),
    ])->assertOk()
        ->assertJsonPath('data.type', 'file')
        ->assertJsonStructure(['data' => ['file' => ['id', 'url', 'type', 'name', 'size', 'file_api']]]);
});

it('lists only the requested group, nested by sub_group', function () {
    foreach ([['web', 'auth', 'login_image'], ['app', 'home', 'banner']] as [$group, $subGroup, $key]) {
        $this->withHeaders(apiHeaders())->post('/api/media', [
            'group' => $group,
            'sub_group' => $subGroup,
            'key' => $key,
            'file' => UploadedFile::fake()->image('asset.png', 32, 32),
        ])->assertOk();
    }

    $this->withHeaders(apiHeaders())->getJson('/api/media?group=web')
        ->assertOk()
        ->assertJsonPath('data.group', 'web')
        ->assertJsonPath('data.media.auth.login_image.type', 'image')
        ->assertJsonMissingPath('data.media.home');
});

it('refuses a group outside app and web', function () {
    $this->withHeaders(apiHeaders())->getJson('/api/media?group=secret')
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['group']]);
});
