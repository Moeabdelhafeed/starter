<?php

use App\Models\Language;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * @return array<int, string> the group keys present in the response
 */
function searchGroups(TestResponse $response): array
{
    return array_column($response->json('groups'), 'key');
}

it('keeps the palette behind authentication', function () {
    // The palette fetches as JSON, so a signed-out session has to get a status
    // it can act on rather than the login page's HTML.
    $this->getJson(route('search', ['q' => 'anything']))->assertStatus(401);

    $this->get(route('search', ['q' => 'anything']))->assertRedirect(route('login'));
});

it('returns nothing for an empty query rather than the whole database', function () {
    $this->actingAs(adminUser())->getJson(route('search', ['q' => '   ']))
        ->assertOk()
        ->assertExactJson(['groups' => []]);
});

it('finds an admin by name and by email', function () {
    adminUser(['name' => 'Wilhelmina Targe', 'email' => 'wilhelmina@example.com']);

    $byName = $this->actingAs(adminUser())->getJson(route('search', ['q' => 'Wilhelmina']))->assertOk();
    $byEmail = $this->actingAs(adminUser())->getJson(route('search', ['q' => 'wilhelmina@example']))->assertOk();

    expect(searchGroups($byName))->toContain('users')
        ->and(searchGroups($byEmail))->toContain('users');
});

it('links a hit to its own list, filtered and highlighted', function () {
    $target = adminUser(['name' => 'Findable Person']);

    $response = $this->actingAs(adminUser())->getJson(route('search', ['q' => 'Findable']))->assertOk();

    $users = collect($response->json('groups'))->firstWhere('key', 'users');
    $hit = collect($users['items'])->firstWhere('id', $target->id);

    // The row has to be on the first page to be highlighted, so the link carries
    // both the search term and the id.
    expect($hit['url'])->toContain('search=Findable')
        ->and($hit['url'])->toContain('highlight='.$target->id);
});

it('produces a link that actually narrows the destination list to the hit', function () {
    $target = adminUser(['name' => 'Zebediah Quill']);
    adminUser(['name' => 'Someone Else']);

    $response = $this->actingAs(adminUser())->getJson(route('search', ['q' => 'Zebediah']))->assertOk();

    $users = collect($response->json('groups'))->firstWhere('key', 'users');
    $url = collect($users['items'])->firstWhere('id', $target->id)['url'];

    // Following the link has to land on a list already filtered down to the row,
    // otherwise the highlight has nothing to glow on.
    $this->actingAs(adminUser())->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('User/Index')
            ->where('filters.search', 'Zebediah')
            ->has('users.data', 1)
            ->where('users.data.0.id', $target->id)
        );
});

it('hides a module the admin has no permission for', function () {
    adminUser(['name' => 'Hidden Person']);
    $restricted = adminWithPermissions(['roles']);

    $response = $this->actingAs($restricted)->getJson(route('search', ['q' => 'Hidden']))->assertOk();

    expect(searchGroups($response))->not->toContain('users');
});

it('searches only the modules the admin holds permissions for', function () {
    Page::factory()->create(['slug' => 'shared-term-page']);
    adminUser(['name' => 'shared-term person']);

    $pagesOnly = adminWithPermissions(['pages']);

    $response = $this->actingAs($pagesOnly)->getJson(route('search', ['q' => 'shared-term']))->assertOk();

    expect(searchGroups($response))->toContain('pages')->not->toContain('users');
});

it('hides a module whose feature flag is off', function () {
    Page::factory()->create(['slug' => 'flagged-page']);
    config()->set('features.pages', false);

    $response = $this->actingAs(adminUser())->getJson(route('search', ['q' => 'flagged']))->assertOk();

    expect(searchGroups($response))->not->toContain('pages');
});

it('keeps admin accounts out of the app-users group and app users out of the admin group', function () {
    $admin = adminUser(['name' => 'Crossover Name']);

    $appUser = User::factory()->create(['name' => 'Crossover Name', 'email' => 'app@example.com']);
    $appUser->assignRole(Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']));

    $response = $this->actingAs(adminUser())->getJson(route('search', ['q' => 'Crossover']))->assertOk();

    $groups = collect($response->json('groups'))->keyBy('key');

    // The two modules share a table; each must only ever surface its own side.
    expect(collect($groups['users']['items'])->pluck('id'))->toContain($admin->id)->not->toContain($appUser->id)
        ->and(collect($groups['app_users']['items'])->pluck('id'))->toContain($appUser->id)->not->toContain($admin->id);
});

it('matches a language by code as well as by name', function () {
    Language::firstOrCreate(
        ['code' => 'fr'],
        ['name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'is_active' => true, 'is_default' => false],
    );

    $response = $this->actingAs(adminUser())->getJson(route('search', ['q' => 'fr']))->assertOk();

    expect(searchGroups($response))->toContain('languages');
});

it('treats a wildcard character as a literal, not as a match-anything', function () {
    adminUser(['name' => 'Alpha']);
    adminUser(['name' => 'Beta']);

    // Unescaped, '%' would match every row in the table.
    $response = $this->actingAs(adminUser())->getJson(route('search', ['q' => '%']))->assertOk();

    expect(searchGroups($response))->not->toContain('users');
});

it('caps how many rows a single module contributes', function () {
    foreach (range(1, 9) as $index) {
        adminUser(['name' => "Repeated Name {$index}"]);
    }

    $response = $this->actingAs(adminUser())->getJson(route('search', ['q' => 'Repeated Name']))->assertOk();

    $users = collect($response->json('groups'))->firstWhere('key', 'users');

    expect(count($users['items']))->toBeLessThanOrEqual(5);
});

it('rejects an absurdly long term instead of sending it to the database', function () {
    $this->actingAs(adminUser())
        ->getJson(route('search', ['q' => str_repeat('a', 500)]))
        ->assertStatus(422);
});
