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
