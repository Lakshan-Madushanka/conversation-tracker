<?php

declare(strict_types=1);

namespace App\Actions\PracticeSessions;

use App\Actions\PracticeQuestions\SavePracticeQuestions;
use App\Actions\Topics\FindOrCreateTopic;
use App\Models\PracticeSession;
use Illuminate\Support\Facades\DB;

/**
 * Starts a new practice session from the submitted questions.
 */
final readonly class CreatePracticeSession
{
    public function __construct(
        private FindOrCreateTopic $findOrCreateTopic,
        private SavePracticeQuestions $savePracticeQuestions,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): PracticeSession
    {
        return DB::transaction(function () use ($data): PracticeSession {
            $session = PracticeSession::create([
                'topic_id' => $this->findOrCreateTopic->handle((string) $data['topic'])->id,
            ]);

            /** @var array<int, array<string, mixed>> $questions */
            $questions = is_array($data['questions'] ?? null) ? array_values($data['questions']) : [];

            $this->savePracticeQuestions->handle($session, $questions);

            return $session;
        });
    }
}
