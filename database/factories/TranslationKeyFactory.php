<?php

namespace Database\Factories;

use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TranslationKey>
 */
class TranslationKeyFactory extends Factory
{
    protected $model = TranslationKey::class;

    /**
     * `sub_group` is an empty-string sentinel, never null — the composite unique
     * (key, group, sub_group) would otherwise let duplicates through on MySQL.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => Str::slug(fake()->unique()->words(3, true), '_'),
            'group' => 'custom',
            'sub_group' => '',
        ];
    }

    /**
     * A key consumed by the mobile app through Trans::get('api.key').
     */
    public function api(string $subGroup = ''): static
    {
        return $this->state(fn (array $attributes): array => [
            'group' => 'api',
            'sub_group' => $subGroup,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'group' => 'admin',
        ]);
    }
}
