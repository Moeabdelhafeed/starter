<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Custom (non-protected) roles. The three protected ones — super_admin, fallback
 * and the api-guard `user` — are owned by RoleSeeder; don't create those here.
 *
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => Str::slug(fake()->unique()->jobTitle(), '_'),
            'guard_name' => 'web',
        ];
    }

    /**
     * A role on the mobile-app (api) guard. Permissions are web-guard only.
     */
    public function api(): static
    {
        return $this->state(fn (array $attributes): array => [
            'guard_name' => 'api',
        ]);
    }
}
