<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'verified_at' => now(),
            'is_active' => true,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_at' => null,
        ]);
    }

    /**
     * An admin-panel user holding the super_admin role on the web guard.
     * The role is created on demand so the factory works without RoleSeeder.
     */
    public function admin(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->assignRole(Role::findOrCreate('super_admin', 'web'));
        });
    }

    /**
     * An admin-panel user holding an arbitrary web-guard role.
     */
    public function withRole(string $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            $user->assignRole(Role::findOrCreate($role, 'web'));
        });
    }

    /**
     * An anonymous device-bound guest: no identifier, no password, keyed by guest_id.
     * Mirrors what User::findOrCreateGuest() builds at runtime.
     */
    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Guest',
            'email' => null,
            'password' => null,
            'remember_token' => null,
            'is_guest' => true,
            'platform' => fake()->randomElement(['ios', 'android', 'web']),
            'guest_id' => (string) Str::uuid(),
            'last_seen_at' => now(),
        ]);
    }

    /**
     * A mobile-app user on the api guard. Store reviewers are exempt from the
     * deletion purge, hence the separate `reviewer()` state.
     */
    public function appUser(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->assignRole(Role::findOrCreate('user', 'api'));
        });
    }

    public function reviewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_reviewer' => true,
        ]);
    }

    /**
     * A user who requested account deletion `$days` ago and is awaiting purge.
     */
    public function pendingDeletion(int $days = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'account_deleted_at' => now()->subDays($days),
        ]);
    }
}
