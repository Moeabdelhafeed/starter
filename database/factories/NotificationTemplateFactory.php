<?php

namespace Database\Factories;

use App\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationTemplate>
 */
class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => Str::slug(fake()->unique()->words(3, true)),
            'topic' => fake()->randomElement(['users', 'guests']),
            'trigger_model' => null,
            'trigger_event' => null,
            'is_active' => true,
            'last_sent_at' => null,
        ];
    }

    /**
     * Fires automatically when the given model class emits the given event.
     */
    public function triggeredBy(string $modelClass, string $event = 'created'): static
    {
        return $this->state(fn (array $attributes): array => [
            'trigger_model' => $modelClass,
            'trigger_event' => $event,
        ]);
    }

    /**
     * A template application code sends by looking it up: copy editable, wiring frozen,
     * never deletable.
     */
    public function system(string $type): static
    {
        return $this->state(fn (array $attributes): array => [
            'system_type' => $type,
            'slug' => $type,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
