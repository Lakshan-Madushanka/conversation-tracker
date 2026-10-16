<?php

declare(strict_types=1);

namespace App\Actions\PracticeSessions;

use App\Actions\PracticeQuestions\SavePracticeQuestion;
use App\Actions\Topics\FindOrCreateTopic;
use App\Models\PracticeSession;
use Illuminate\Support\Facades\DB;

/**
 * Starts a new practice session from the first question the learner pasted.
 */
final class CreatePracticeSession
{
    public function __construct(
        private readonly FindOrCreateTopic $findOrCreateTopic,
        private readonly SavePracticeQuestion $savePracticeQuestion,
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

            $this->savePracticeQuestion->handle($session, $data);

            return $session;
        });
    }
}
