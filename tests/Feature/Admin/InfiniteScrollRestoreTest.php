<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * A write redirects back to a list the admin had scrolled through. Without this, the
 * redirect response carried page 1 only while the InfiniteScroll component still thought it
 * had loaded page N — the next fetch appended page N+1 after page 1 and the rows in
 * between, the edited one included, were gone. See AppServiceProvider::configureScrollPagination().
 */

/** Headers of a real Inertia XHR — without the asset version one Inertia answers 409. */
function inertiaHeaders(array $extra = []): array
{
    $version = test()->get(route('users'))->viewData('page')['version'] ?? '';

    return ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version, ...$extra];
}

function adminUsers(int $count): void
{
    $role = Role::firstOrCreate(['name' => 'fallback', 'guard_name' => 'web']);
    User::factory()->count($count)->create()->each->assignRole($role);
}

it('pages normally without the restore header', function () {
    $admin = adminUser();
    adminUsers(35);

    $this->actingAs($admin)
        ->withHeaders(inertiaHeaders())
        ->get(route('users'))
        ->assertJsonCount(10, 'props.users.data')
        ->assertJsonPath('scrollProps.users.currentPage', 1)
        ->assertJsonPath('scrollProps.users.nextPage', 2);
});

it('returns every loaded page again when a write redirects back with the restore header', function () {
    $admin = adminUser();
    adminUsers(35); // 36 with the admin: pages of 10 → 4 pages

    $this->actingAs($admin)
        ->withHeaders(inertiaHeaders(['X-Inertia-Scroll-Restore' => json_encode(['page' => 3])]))
        ->get(route('users'))
        ->assertOk()
        // Pages 1..3 in one response, and the cursor sits on page 3 so the client's next
        // fetch is page 4 — not page 2 again.
        ->assertJsonCount(30, 'props.users.data')
        ->assertJsonPath('scrollProps.users.currentPage', 3)
        ->assertJsonPath('scrollProps.users.nextPage', 4);
});

it('ignores the restore header on the infinite scroll\'s own partial page fetches', function () {
    $admin = adminUser();
    adminUsers(35);

    $this->actingAs($admin)
        ->withHeaders(inertiaHeaders([
            'X-Inertia-Partial-Component' => 'User/Index',
            'X-Inertia-Partial-Data' => 'users',
            'X-Inertia-Scroll-Restore' => json_encode(['page' => 3]),
        ]))
        ->get(route('users', ['page' => 2]))
        ->assertOk()
        ->assertJsonCount(10, 'props.users.data')
        ->assertJsonPath('scrollProps.users.currentPage', 2);
});

it('caps a forged restore header instead of returning the whole table', function () {
    $admin = adminUser();
    adminUsers(35);

    $this->actingAs($admin)
        ->withHeaders(inertiaHeaders(['X-Inertia-Scroll-Restore' => json_encode(['page' => 999999])]))
        ->get(route('users'))
        ->assertOk()
        ->assertJsonPath('scrollProps.users.currentPage', 50);
});

it('flashes the created or edited row id so the list can glow it', function () {
    $admin = adminUser();
    Role::firstOrCreate(['name' => 'fallback', 'guard_name' => 'web']);

    $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Glow Me',
        'email' => 'glow@example.com',
        'password' => 'a-long-enough-password',
        'role' => 'fallback',
    ])->assertSessionHas('highlight', User::where('email', 'glow@example.com')->value('id'));

    $user = User::where('email', 'glow@example.com')->first();

    $this->actingAs($admin)->withSession(['highlight' => $user->id])
        ->get(route('users'))
        ->assertInertia(fn ($page) => $page->where('highlight', $user->id));
});

/**
 * The other half of the same bug, and the one that survived the first fix.
 *
 * `Inertia::scroll()` marks its prop mergeable, and `ScrollProp::configureMergeIntent()`
 * defaults to **append** whenever the merge-intent header is absent. So the redirect
 * after a write asked the client to append the restored pages 1..N onto the pages 1..N
 * it already had — every row twice. The client only escaped that when the visit named
 * the scroll prop in `reset:`, which is one array in one options object per call site,
 * on nine lists, forever. Two of them never had it.
 *
 * Merging is only ever right for the InfiniteScroll component's own fetch, and that
 * request is the only one that carries the header. So the rule is the header's, not
 * the call site's.
 */
it('never asks the client to merge a list into itself when a write redirects back', function () {
    $admin = adminUser();
    adminUsers(35);

    $response = $this->actingAs($admin)
        ->withHeaders(inertiaHeaders(['X-Inertia-Scroll-Restore' => json_encode(['page' => 3])]))
        ->get(route('users'))
        ->assertOk();

    // `mergeProps` is the instruction to append; its absence is the instruction to
    // replace. Asserted on the payload rather than on a call site's options, because
    // the whole point is that no call site has to remember.
    expect($response->json('mergeProps') ?? [])->not->toContain('users.data');
});

it('still merges for the infinite scroll component\'s own fetch', function () {
    $admin = adminUser();
    adminUsers(35);

    // The merge-intent header is what the component sends and nothing else does.
    $response = $this->actingAs($admin)
        ->withHeaders(inertiaHeaders([
            'X-Inertia-Partial-Component' => 'User/Index',
            'X-Inertia-Partial-Data' => 'users',
            'X-Inertia-Infinite-Scroll-Merge-Intent' => 'append',
        ]))
        ->get(route('users', ['page' => 2]))
        ->assertOk();

    expect($response->json('mergeProps') ?? [])->toContain('users.data');
});

/**
 * The same rule, checked on every list rather than on the one that happened to break.
 *
 * This is the guard, not the two tests above: the bug was never specific to admin users,
 * and the next list added to the CMS gets no review from anybody. If a package upgrade
 * puts the package's own ScrollProp back, or a controller finds some way around the
 * container binding, this names the list it happened on.
 */
it('never merges a list into itself on any admin list', function (string $routeName) {
    $owner = User::factory()->create();
    $owner->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

    $version = $this->actingAs($owner)->get(route($routeName))->viewData('page')['version'] ?? '';

    $response = $this->actingAs($owner)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $version,
            // What app.ts sends on the non-GET visit that precedes this redirect.
            'X-Inertia-Scroll-Restore' => json_encode(['page' => 2]),
        ])
        ->get(route($routeName))
        ->assertOk();

    // Any entry at all would be an instruction to append onto what the client holds.
    // Asserted as "no merge metadata whatsoever" rather than naming each list's prop,
    // so a list whose prop is renamed is still covered.
    expect($response->json('mergeProps') ?? [])->toBe([])
        ->and($response->json('prependProps') ?? [])->toBe([])
        ->and($response->json('deepMergeProps') ?? [])->toBe([]);
})->with([
    'users',
    'app_users',
    'pages',
    'translations',
    'notification_templates',
    'roles',
    'languages',
    'activity_logs',
    'media',
]);
