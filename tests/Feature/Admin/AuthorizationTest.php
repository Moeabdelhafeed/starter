<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Every permission-gated GET page in the admin panel, discovered from the route
 * table rather than hand-listed, so a new feature is covered the day it is added.
 *
 * @return array<int, array{0: string, 1: string}> [route name, permission]
 */
function permissionGatedPages(): array
{
    $pages = [];

    foreach (Route::getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true) || ! $route->getName()) {
            continue;
        }

        if (str_starts_with($route->uri(), 'api/') || str_contains($route->uri(), '{')) {
            continue;
        }

        $permission = null;

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'permission:')) {
                $permission = substr($middleware, strlen('permission:'));
            }
        }

        if ($permission !== null && ! str_contains($route->getName(), 'export')) {
            $pages[] = [$route->getName(), $permission];
        }
    }

    return $pages;
}

it('discovers the permission-gated pages it is about to check', function () {
    // A broken filter above would silently turn every case below into a no-op.
    expect(permissionGatedPages())->not->toBeEmpty();
});

it('refuses a permission-gated page to an admin without that permission', function () {
    foreach (permissionGatedPages() as [$name, $permission]) {
        $user = adminWithPermissions([]);

        $this->actingAs($user)->get(route($name))->assertForbidden();
    }
})->skip(fn () => permissionGatedPages() === [], 'no permission-gated pages registered');

it('opens a permission-gated page to an admin holding exactly that permission', function () {
    foreach (permissionGatedPages() as [$name, $permission]) {
        $user = adminWithPermissions([$permission]);

        $response = $this->actingAs($user)->get(route($name));

        expect($response->getStatusCode())
            ->toBe(200, "route [{$name}] should open for permission [{$permission}]");
    }
})->skip(fn () => permissionGatedPages() === [], 'no permission-gated pages registered');

it('lets a super admin open every admin page', function () {
    $user = adminUser();

    foreach (permissionGatedPages() as [$name]) {
        $this->actingAs($user)->get(route($name))->assertOk();
    }

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $this->actingAs($user)->get(route('profile'))->assertOk();
});

it('stops a user manager from granting themselves the super admin role', function () {
    $manager = adminWithPermissions(['users']);
    $victim = adminUser([], 'fallback');

    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'], ['is_active' => true]);

    $this->actingAs($manager)->from(route('users'))->put(route('users.update', $victim), [
        'name' => $victim->name,
        'email' => $victim->email,
        'role' => 'super_admin',
    ])->assertSessionHasErrors('role');

    expect($victim->fresh()->hasRole('super_admin'))->toBeFalse();
});

it('stops a user manager from editing a super admin', function () {
    $manager = adminWithPermissions(['users']);
    $superAdmin = adminUser(['name' => 'Untouchable']);

    $this->actingAs($manager)->put(route('users.update', $superAdmin), [
        'name' => 'Hijacked',
        'email' => $superAdmin->email,
        'role' => 'fallback',
    ])->assertForbidden();

    expect($superAdmin->fresh()->name)->toBe('Untouchable');
});

it('stops a user manager from deleting a super admin', function () {
    $manager = adminWithPermissions(['users']);
    $superAdmin = adminUser();

    $this->actingAs($manager)->delete(route('users.destroy', $superAdmin))->assertForbidden();

    expect(User::whereKey($superAdmin->id)->exists())->toBeTrue();
});

it('stops an app-user manager from reaching an admin account', function () {
    $manager = adminWithPermissions(['app_users']);
    $admin = adminUser(['name' => 'Admin Account']);

    // The {user} binding is shared between both modules; without scoping, the
    // app-user screen is a back door into the admin table.
    $this->actingAs($manager)->put(route('app_users.update', $admin), [
        'name' => 'Hijacked',
    ])->assertForbidden();

    expect($admin->fresh()->name)->toBe('Admin Account');
});

it('stops a role manager from granting permissions to the role they hold', function () {
    $manager = adminWithPermissions(['roles']);
    $ownRole = $manager->roles->first();

    $this->actingAs($manager)->put(route('roles.update', $ownRole), [
        'name' => $ownRole->name,
        'permissions' => ['users', 'roles'],
        'is_active' => true,
    ])->assertForbidden();

    expect($manager->fresh()->can('users'))->toBeFalse();
});

it('keeps the developer settings panel to super admins', function () {
    $manager = adminWithPermissions(['users']);

    $this->actingAs($manager)->get(route('dev_settings'))->assertForbidden();
    $this->actingAs(adminUser())->get(route('dev_settings'))->assertOk();
})->skip(fn () => ! Route::has('dev_settings'), 'dev settings are local-only');
