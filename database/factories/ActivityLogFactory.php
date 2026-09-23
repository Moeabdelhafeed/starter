<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'causer_name' => fake()->name(),
            'causer_email' => fake()->safeEmail(),
            'subject_type' => User::class,
            'subject_id' => (string) fake()->numberBetween(1, 100),
            'action' => fake()->randomElement(['created', 'updated', 'deleted']),
            'old_data' => null,
            'new_data' => ['name' => fake()->name()],
        ];
    }

    /**
     * Attribute the entry to a real record and the admin who touched it.
     * Named forSubject, not `for` — Factory::for() already means "belongs to".
     */
    public function forSubject(object $subject, ?User $causer = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'subject_type' => $subject::class,
            'subject_id' => (string) $subject->getKey(),
            'causer_name' => $causer?->name ?? $attributes['causer_name'],
            'causer_email' => $causer?->email ?? $attributes['causer_email'],
        ]);
    }

    public function action(string $action): static
    {
        return $this->state(fn (array $attributes): array => [
            'action' => $action,
        ]);
    }
}
