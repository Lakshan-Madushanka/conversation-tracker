<?php

declare(strict_types=1);

namespace App\Actions\PracticeSessions;

use App\Exceptions\SessionDeletionStaleException;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Queries\SessionDeletionPreview;
use App\Support\DeletionToken;
use Illuminate\Support\Facades\DB;

/**
 * Removes a session and everything inside it, permanently.
 *
 * The confirmation carries the signed state the warning showed. The session is
 * locked while the state is checked, so a session changed in another tab is
 * refused instead of deleting content the learner has not seen. When the check
 * passes, questions, feedback entries and the session are removed together in
 * one transaction, so nothing is left half-deleted.
 *
 * A session that is already gone is treated as success, which makes a double
 * click and a retried request safe.
 */
final readonly class DeletePracticeSession
{
    public function __construct(private SessionDeletionPreview $preview) {}

    public function handle(
        int $sessionId,
        string $token,
        string $timezone,
    ): DeletePracticeSessionResult {
        return DB::transaction(function () use ($sessionId, $token, $timezone): DeletePracticeSessionResult {
            /** @var PracticeSession|null $session */
            $session = PracticeSession::query()
                ->whereKey($sessionId)
                ->lockForUpdate()
                ->first();

            if (! $session instanceof PracticeSession) {
                return new DeletePracticeSessionResult(
                    sessionId: $sessionId,
                    topic: '',
                    counts: [
                        'questions' => 0,
                        'words' => 0,
                        'phrases' => 0,
                        'mistakes' => 0,
                        'grammar_notes' => 0,
                    ],
                    alreadyDeleted: true,
                );
            }

            $effect = $this->preview->for($session, $timezone);

            if (! DeletionToken::matches($token, $effect->fingerprint())) {
                throw new SessionDeletionStaleException($effect);
            }

            $questionIds = PracticeQuestion::query()
                ->where('practice_session_id', $session->id)
                ->pluck('id')
                ->all();

            if ($questionIds !== []) {
                PracticeFeedbackEntry::query()
                    ->whereIn('practice_question_id', $questionIds)
                    ->delete();

                PracticeQuestion::query()
                    ->whereIn('id', $questionIds)
                    ->delete();
            }

            $session->delete();

            return new DeletePracticeSessionResult(
                sessionId: $sessionId,
                topic: $effect->session->topic->name,
                counts: $effect->counts,
                alreadyDeleted: false,
            );
        });
    }
}
