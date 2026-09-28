<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'disk' => 'public',
            'path' => "media/{$name}.png",
            'filename' => "{$name}.png",
            'mime_type' => 'image/png',
            'size' => 1024,
            'width' => 800,
            'height' => 600,
            'alt' => null,
        ];
    }
}
