<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeIso8601', function () {
    return $this->toMatch('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}/');
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * An admin-panel user holding an active web-guard role.
 *
 * The admin panel authenticates against the web guard, and EnsureUserIsActive
 * additionally requires the role itself to be active — a user missing either is
 * bounced straight back to the login screen.
 *
 * @param  array<string, mixed>  $attributes
 */
function adminUser(array $attributes = [], string $role = 'super_admin'): User
{
    $webRole = Role::firstOrCreate(
        ['name' => $role, 'guard_name' => 'web'],
        ['is_active' => true],
    );

    $webRole->forceFill(['is_active' => true])->save();

    $user = User::factory()->create($attributes);
    $user->assignRole($webRole);

    return $user->fresh();
}

/**
 * An admin user granted exactly the listed permissions and nothing else, so a
 * test can prove a route is gated on one specific permission.
 *
 * @param  array<int, string>  $permissions
 * @param  array<string, mixed>  $attributes
 */
function adminWithPermissions(array $permissions, array $attributes = []): User
{
    $user = adminUser($attributes, 'test_role_'.substr(md5(uniqid('', true)), 0, 8));

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $user->roles->first()->syncPermissions($permissions);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

/**
 * A verified mobile-app (api guard) user with the known password
 * `correct-horse-battery`.
 *
 * @param  array<string, mixed>  $attributes
 */
function appUser(array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'password' => Hash::make('correct-horse-battery'),
        'verified_at' => now(),
        'is_active' => true,
    ], $attributes));

    $user->assignRole(Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']));

    return $user->fresh();
}

/**
 * The headers every mobile API request must carry (see the mobile-device-tracking skill).
 *
 * @param  array<string, string|null>  $overrides
 * @return array<string, string|null>
 */
function apiHeaders(array $overrides = []): array
{
    return array_merge([
        'X-API-TOKEN' => config('app.x_api_token'),
        'Accept-Language' => 'en',
        'X-Device-Id' => 'test-device-uuid',
        'X-Platform' => 'web',
        'Accept' => 'application/json',
    ], $overrides);
}
