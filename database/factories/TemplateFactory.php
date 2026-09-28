<?php

namespace Database\Factories;

use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->word().' '.fake()->word());

        return [
            'name' => $name,
            'handle' => Str::slug($name),
            'description' => null,
            'fields' => [
                ['handle' => 'body', 'label' => 'Body', 'type' => 'markdown', 'required' => false, 'options' => []],
            ],
            'layout' => '{{ body }}',
        ];
    }
}
