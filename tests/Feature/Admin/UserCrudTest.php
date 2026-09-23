<?php

use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    // `fallback` is the role the CRUD tests hand out; `super_admin` is created by
    // adminUser() for the actor.
    Role::firstOrCreate(['name' => 'fallback', 'guard_name' => 'web'], ['is_active' => true]);
});

it('renders the users page with the admin accounts', function () {
    $admin = adminUser();
    User::factory()->count(2)->withRole('fallback')->create();

    $this->actingAs($admin)->get(route('users'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('User/Index')
            ->has('users.data', 3) // the two fallbacks plus the acting super admin
            ->has('roles')
        );
});

it('narrows the list to accounts matching the search term', function () {
    $admin = adminUser(['name' => 'The Actor']);
    User::factory()->withRole('fallback')->create(['name' => 'Findable Person']);
    User::factory()->withRole('fallback')->create(['name' => 'Someone Else']);

    $this->actingAs($admin)->get(route('users', ['search' => 'Findable']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Findable Person')
        );
});

it('lists only trashed accounts under the trashed filter', function () {
    $admin = adminUser();
    $deleted = User::factory()->withRole('fallback')->create(['name' => 'Gone']);
    User::factory()->withRole('fallback')->create(['name' => 'Still Here']);
    $deleted->delete();

    $this->actingAs($admin)->get(route('users', ['trashed' => 'only']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Gone')
        );
});

it('creates an account and gives it the submitted role', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'New Admin',
        'email' => 'new-admin@example.com',
        'password' => 'a-long-enough-password',
        'role' => 'fallback',
    ])->assertRedirect();

    $created = User::where('email', 'new-admin@example.com')->first();

    expect($created)->not->toBeNull()
        ->and($created->hasRole('fallback'))->toBeTrue()
        ->and($created->is_active)->toBeTrue();
});

it('refuses to create a second account on an email already in use', function () {
    $admin = adminUser();
    $existing = User::factory()->withRole('fallback')->create();

    $this->actingAs($admin)->from(route('users'))->post(route('users.store'), [
        'name' => 'Impostor',
        'email' => $existing->email,
        'password' => 'a-long-enough-password',
        'role' => 'fallback',
    ])->assertSessionHasErrors('email');

    expect(User::where('email', $existing->email)->count())->toBe(1);
});

it('refuses to create an account with no password', function () {
    $admin = adminUser();

    $this->actingAs($admin)->from(route('users'))->post(route('users.store'), [
        'name' => 'No Password',
        'email' => 'no-password@example.com',
        'role' => 'fallback',
    ])->assertSessionHasErrors('password');

    expect(User::where('email', 'no-password@example.com')->exists())->toBeFalse();
});

it('updates an account name, email and role', function () {
    $admin = adminUser();
    $target = User::factory()->withRole('fallback')->create(['name' => 'Before']);
    Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web'], ['is_active' => true]);

    $this->actingAs($admin)->put(route('users.update', $target), [
        'name' => 'After',
        'email' => 'after@example.com',
        'role' => 'editor',
    ])->assertRedirect();

    $target->refresh();

    expect($target->name)->toBe('After')
        ->and($target->email)->toBe('after@example.com')
        ->and($target->hasRole('editor'))->toBeTrue()
        ->and($target->hasRole('fallback'))->toBeFalse();
});

it('refuses to let an admin deactivate their own account', function () {
    // Locking yourself out of the only super-admin account is unrecoverable
    // from inside the panel, so the controller short-circuits before saving.
    $admin = adminUser();

    $this->actingAs($admin)->from(route('users'))->put(route('users.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'role' => 'super_admin',
        'is_active' => false,
    ])->assertRedirect()->assertSessionHas('error');

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('refuses to let an admin delete their own account', function () {
    $admin = adminUser();

    $this->actingAs($admin)->from(route('users'))
        ->delete(route('users.destroy', $admin))
        ->assertRedirect()->assertSessionHas('error');

    expect(User::whereKey($admin->id)->exists())->toBeTrue();
});

it('soft-deletes an account so it survives in the trash', function () {
    $admin = adminUser();
    $target = User::factory()->withRole('fallback')->create();

    $this->actingAs($admin)->delete(route('users.destroy', $target))->assertRedirect();

    // Asserted on the DB rather than a follow-up index request because the point
    // is that the row survives — the default list would hide it either way.
    expect(User::whereKey($target->id)->exists())->toBeFalse()
        ->and(User::withTrashed()->whereKey($target->id)->exists())->toBeTrue();
});

it('brings a trashed account back with restore', function () {
    $admin = adminUser();
    $target = User::factory()->withRole('fallback')->create();
    $target->delete();

    $this->actingAs($admin)->post(route('users.restore', $target->id))->assertRedirect();

    expect(User::whereKey($target->id)->exists())->toBeTrue();
});

it('removes a trashed account permanently with force-delete', function () {
    $admin = adminUser();
    $target = User::factory()->withRole('fallback')->create();
    $target->delete();

    $this->actingAs($admin)->delete(route('users.force-delete', $target->id))->assertRedirect();

    expect(User::withTrashed()->whereKey($target->id)->exists())->toBeFalse();
});

it('bulk-deactivates exactly the submitted accounts', function () {
    $admin = adminUser();
    [$targeted, $untouched] = User::factory()->count(2)->withRole('fallback')->create()->all();

    $this->actingAs($admin)->put(route('users.bulk-update'), [
        'ids' => [$targeted->id],
        'is_active' => false,
    ])->assertRedirect();

    expect($targeted->fresh()->is_active)->toBeFalse()
        ->and($untouched->fresh()->is_active)->toBeTrue();
});

it('never deactivates the acting admin through a bulk update', function () {
    $admin = adminUser();
    $other = User::factory()->withRole('fallback')->create();

    $this->actingAs($admin)->put(route('users.bulk-update'), [
        'ids' => [$admin->id, $other->id],
        'is_active' => false,
    ])->assertRedirect();

    expect($admin->fresh()->is_active)->toBeTrue()
        ->and($other->fresh()->is_active)->toBeFalse();
});

it('bulk-deletes and bulk-force-deletes exactly the submitted accounts', function () {
    $admin = adminUser();
    [$targeted, $untouched] = User::factory()->count(2)->withRole('fallback')->create()->all();

    $this->actingAs($admin)->delete(route('users.bulk-destroy'), ['ids' => [$targeted->id]])
        ->assertRedirect();

    expect(User::whereKey($targeted->id)->exists())->toBeFalse()
        ->and(User::whereKey($untouched->id)->exists())->toBeTrue();

    $this->actingAs($admin)->post(route('users.bulk-force-delete'), ['ids' => [$targeted->id]])
        ->assertRedirect();

    expect(User::withTrashed()->whereKey($targeted->id)->exists())->toBeFalse()
        ->and(User::whereKey($untouched->id)->exists())->toBeTrue();
});

it('bulk-restores exactly the submitted accounts', function () {
    $admin = adminUser();
    [$targeted, $untouched] = User::factory()->count(2)->withRole('fallback')->create()->all();
    $targeted->delete();
    $untouched->delete();

    $this->actingAs($admin)->post(route('users.bulk-restore'), ['ids' => [$targeted->id]])
        ->assertRedirect();

    expect(User::whereKey($targeted->id)->exists())->toBeTrue()
        ->and(User::whereKey($untouched->id)->exists())->toBeFalse();
});
