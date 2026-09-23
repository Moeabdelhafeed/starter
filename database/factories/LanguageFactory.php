<?php

namespace Database\Factories;

use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Language>
 */
class LanguageFactory extends Factory
{
    protected $model = Language::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->languageCode();

        return [
            'code' => $name,
            'name' => ucfirst($name),
            'native_name' => ucfirst($name),
            'direction' => 'ltr',
            'is_active' => true,
            'is_default' => false,
        ];
    }

    /**
     * The app's default language. Only one row may hold this at a time.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function rtl(): static
    {
        return $this->state(fn (array $attributes): array => [
            'direction' => 'rtl',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
