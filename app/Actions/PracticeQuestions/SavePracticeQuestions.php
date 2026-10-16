<?php

declare(strict_types=1);

namespace App\Actions\PracticeQuestions;

use App\Models\PracticeSession;

/**
 * Saves all questions for a session in the order they were submitted, reusing
 * existing records by position and removing any that no longer exist.
 */
final class SavePracticeQuestions
{
    public function __construct(
        private readonly SavePracticeQuestion $savePracticeQuestion,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $questions
     */
    public function handle(PracticeSession $session, array $questions): void
    {
        $questionCount = count($questions);

        foreach (array_values($questions) as $index => $data) {
            $this->savePracticeQuestion->handle($session, $index + 1, $data);
        }

        if ($questionCount === 0) {
            return;
        }

        $session->questions()->where('position', '>', $questionCount)->delete();
    }
}
