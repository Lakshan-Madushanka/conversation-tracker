<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PracticeSession;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PracticeSession>
 */
class PracticeSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'topic_id' => Topic::factory(),
        ];
    }

    /** A session the learner has finished. */
    public function finished(): static
    {
        return $this->state(fn (): array => [
            'status' => PracticeSession::STATUS_FINISHED,
            'finished_at' => now(),
        ]);
    }

    /** A finished session the learner has put away in the Archived view. */
    public function archived(): static
    {
        return $this->finished()->state(fn (): array => [
            'archived_at' => now(),
        ]);
    }
}
