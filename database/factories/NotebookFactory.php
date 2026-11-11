<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Notebook;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notebook>
 */
class NotebookFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst(is_array($name) ? implode(' ', $name) : $name),
            'topic_id' => Topic::factory(),
            'description_html' => null,
            'description_text' => null,
            'client_key' => null,
        ];
    }
}
