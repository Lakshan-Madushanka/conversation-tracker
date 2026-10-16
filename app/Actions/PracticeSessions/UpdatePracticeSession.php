<?php

declare(strict_types=1);

namespace App\Actions\PracticeSessions;

use App\Actions\PracticeQuestions\SavePracticeQuestion;
use App\Actions\Topics\FindOrCreateTopic;
use App\Models\PracticeSession;
use Illuminate\Support\Facades\DB;

/**
 * Saves edits to the topic and first question of an existing session.
 */
final class UpdatePracticeSession
{
    public function __construct(
        private readonly FindOrCreateTopic $findOrCreateTopic,
        private readonly SavePracticeQuestion $savePracticeQuestion,
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

            $this->savePracticeQuestion->handle($session, $data);
        });
    }
}
