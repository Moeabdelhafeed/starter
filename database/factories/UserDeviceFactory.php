<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * `personal_access_token_id` is nullable, so a device row can be built without
 * issuing a real Sanctum token. Pass one explicitly when the test needs it.
 *
 * @extends Factory<UserDevice>
 */
class UserDeviceFactory extends Factory
{
    protected $model = UserDevice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'personal_access_token_id' => null,
            'device_id' => (string) Str::uuid(),
            'fcm_token' => null,
            'device_name' => fake()->userAgent(),
            'platform' => fake()->randomElement(['ios', 'android', 'web']),
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'last_seen_at' => now(),
        ];
    }

    public function withFcmToken(?string $token = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'fcm_token' => $token ?? Str::random(64),
        ]);
    }

    public function platform(string $platform): static
    {
        return $this->state(fn (array $attributes): array => [
            'platform' => $platform,
        ]);
    }
}
