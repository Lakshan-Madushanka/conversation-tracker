<?php

declare(strict_types=1);

namespace App\Actions\PracticeSessions;

use App\Actions\PracticeQuestions\SavePracticeQuestions;
use App\Actions\Topics\FindOrCreateTopic;
use App\Models\PracticeSession;
use Illuminate\Support\Facades\DB;

/**
 * Saves edits to the topic and questions of an existing session.
 */
final class UpdatePracticeSession
{
    public function __construct(
        private readonly FindOrCreateTopic $findOrCreateTopic,
        private readonly SavePracticeQuestions $savePracticeQuestions,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(PracticeSession $session, array $data): void
    {
        DB::transaction(function () use ($session, $data): void {
            $session->update([
                'topic_id' => $this->findOrCreateTopic->handle((string) $data['topic'])->id,
            ]);

            /** @var array<int, array<string, mixed>> $questions */
            $questions = is_array($data['questions'] ?? null) ? array_values($data['questions']) : [];

            $this->savePracticeQuestions->handle($session, $questions);
        });
    }
}
