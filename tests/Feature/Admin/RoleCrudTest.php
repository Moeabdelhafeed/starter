<?php

use App\Models\Role;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    foreach (['users', 'roles', 'pages'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    Role::firstOrCreate(['name' => 'fallback', 'guard_name' => 'web'], ['is_active' => true]);
});

it('renders the roles page with each role and its permission list', function () {
    $admin = adminUser();
    Role::factory()->create(['name' => 'content_editor']);

    $this->actingAs($admin)->get(route('roles'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Roles/Index')
            ->has('roles.data')
            ->has('permissions')
        );
});

it('creates a role holding exactly the submitted permissions', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('roles.store'), [
        'name' => 'content_editor',
        'permissions' => ['pages'],
        'is_active' => true,
    ])->assertRedirect();

    $role = Role::where('name', 'content_editor')->first();

    expect($role)->not->toBeNull()
        ->and($role->is_active)->toBeTrue()
        ->and($role->permissions->pluck('name')->all())->toBe(['pages']);
});

it('refuses a role name that already exists', function () {
    $admin = adminUser();
    Role::factory()->create(['name' => 'content_editor']);

    $this->actingAs($admin)->from(route('roles'))->post(route('roles.store'), [
        'name' => 'content_editor',
    ])->assertSessionHasErrors('name');

    expect(Role::where('name', 'content_editor')->count())->toBe(1);
});

it('syncs a role down to exactly the permissions submitted', function () {
    $admin = adminUser();
    $role = Role::factory()->create(['name' => 'content_editor']);
    $role->syncPermissions(['pages', 'users']);

    $this->actingAs($admin)->put(route('roles.update', $role), [
        'permissions' => ['pages'],
    ])->assertRedirect();

    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['pages']);
});

it('refuses to change a protected role', function () {
    // super_admin / fallback / user are seeded infrastructure: editing them from
    // the panel is how an install loses its last working login.
    $admin = adminUser();
    $protected = Role::where('name', 'super_admin')->where('guard_name', 'web')->first();

    $this->actingAs($admin)->from(route('roles'))->put(route('roles.update', $protected), [
        'permissions' => ['pages'],
    ])->assertRedirect()->assertSessionHas('error');

    expect($protected->fresh()->permissions)->toBeEmpty();
});

it('refuses to delete a protected role', function () {
    $admin = adminUser();

    foreach (Role::$protectedRoles as $name) {
        $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['is_active' => true]);

        $this->actingAs($admin)->from(route('roles'))
            ->delete(route('roles.destroy', $role))
            ->assertRedirect()->assertSessionHas('error');

        expect(Role::whereKey($role->id)->exists())->toBeTrue();
    }
});

it('refuses to delete the role the acting admin holds', function () {
    $manager = adminUser([], 'role_manager');
    $manager->roles->first()->syncPermissions(['roles']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $ownRole = $manager->fresh()->roles->first();

    $this->actingAs($manager->fresh())->from(route('roles'))
        ->delete(route('roles.destroy', $ownRole))
        ->assertRedirect()->assertSessionHas('error');

    expect(Role::whereKey($ownRole->id)->exists())->toBeTrue();
});

it('moves the holders of a deleted role onto the fallback role', function () {
    // A role in use is not blocked — the controller reassigns its holders first,
    // so nobody is left roleless (which EnsureUserIsActive would treat as locked out).
    $admin = adminUser();
    $role = Role::factory()->create(['name' => 'content_editor', 'is_active' => true]);
    $holder = User::factory()->create();
    $holder->assignRole($role);

    $this->actingAs($admin)->delete(route('roles.destroy', $role))->assertRedirect();

    expect(Role::whereKey($role->id)->exists())->toBeFalse()
        ->and($holder->fresh()->hasRole('fallback'))->toBeTrue();
});

it('locks the holders of a deactivated role out of the panel', function () {
    $admin = adminUser();
    $role = Role::factory()->create(['name' => 'content_editor', 'is_active' => true]);
    $holder = User::factory()->create();
    $holder->assignRole($role);

    // The holder can reach the panel while the role is live...
    $this->actingAs($holder)->get(route('dashboard'))->assertOk();

    $this->actingAs($admin)->put(route('roles.update', $role), [
        'is_active' => false,
    ])->assertRedirect();

    // ...and is bounced to login the moment it is switched off, because
    // EnsureUserIsActive requires an *active* web-guard role, not just any role.
    $this->actingAs($holder->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
});

it('bulk-deactivates exactly the submitted roles and never a protected one', function () {
    $admin = adminUser();
    $targeted = Role::factory()->create(['name' => 'content_editor', 'is_active' => true]);
    $untouched = Role::factory()->create(['name' => 'support_agent', 'is_active' => true]);
    $protected = Role::where('name', 'fallback')->first();

    $this->actingAs($admin)->put(route('roles.bulk-update'), [
        'ids' => [$targeted->id, $protected->id],
        'is_active' => false,
    ])->assertRedirect();

    expect($targeted->fresh()->is_active)->toBeFalse()
        ->and($protected->fresh()->is_active)->toBeTrue()
        ->and($untouched->fresh()->is_active)->toBeTrue();
});
