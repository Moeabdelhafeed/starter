<?php

namespace Database\Factories;

use App\Models\MediaItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Creates the keyed row only — the Image/Video/MediaFile morph is attached by
 * MediaItem::saveMedia() with a real upload, which a factory cannot fake.
 *
 * @extends Factory<MediaItem>
 */
class MediaItemFactory extends Factory
{
    protected $model = MediaItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => Str::slug(fake()->unique()->words(2, true), '_'),
            'group' => 'app',
            'sub_group' => '',
            'type' => 'image',
        ];
    }

    public function type(string $type): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => $type,
        ]);
    }

    public function in(string $group, string $subGroup = ''): static
    {
        return $this->state(fn (array $attributes): array => [
            'group' => $group,
            'sub_group' => $subGroup,
        ]);
    }
}
