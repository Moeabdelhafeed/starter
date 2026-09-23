<?php

namespace Database\Factories;

use App\Models\TranslationKey;
use App\Models\TranslationValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * One row per (translation_key_id, locale) — that pair is uniquely indexed.
 *
 * @extends Factory<TranslationValue>
 */
class TranslationValueFactory extends Factory
{
    protected $model = TranslationValue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'translation_key_id' => TranslationKey::factory(),
            'locale' => 'en',
            'value' => fake()->sentence(),
        ];
    }

    public function forKey(TranslationKey $key): static
    {
        return $this->state(fn (array $attributes): array => [
            'translation_key_id' => $key->id,
        ]);
    }

    public function locale(string $locale): static
    {
        return $this->state(fn (array $attributes): array => [
            'locale' => $locale,
        ]);
    }
}
