<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PracticeSession;
use App\Support\SessionDeletionBatchEffect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The bulk delete warning, as the page needs it: how many sessions are going,
 * the first few names, exactly what they hold together, progress before and
 * after, and the signed token to confirm that exact state.
 *
 * @mixin SessionDeletionBatchEffect
 */
final class SessionDeletionBatchPreviewResource extends JsonResource
{
    /** How many session names the warning shows before "and N more". */
    private const int NAMED_SESSIONS = 3;

    public function __construct(SessionDeletionBatchEffect $effect)
    {
        parent::__construct($effect);
    }

    /**
     * @return array{
     *     requested_count: int,
     *     session_count: int,
     *     missing_count: int,
     *     finished_count: int,
     *     unfinished_count: int,
     *     archivable_count: int,
     *     all_finished_archived: bool,
     *     names: list<array{topic: string, session_date: string|null}>,
     *     more_count: int,
     *     counts: array{questions: int, words: int, phrases: int, mistakes: int, grammar_notes: int},
     *     progress: array{
     *         before: array{words: int, phrases: int, mistakes: int, grammar_notes: int, practice_days: int, current_streak: int},
     *         after: array{words: int, phrases: int, mistakes: int, grammar_notes: int, practice_days: int, current_streak: int}
     *     },
     *     token: string
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var SessionDeletionBatchEffect $effect */
        $effect = $this->resource;

        $named = [];

        foreach (
            $effect->sessions
                ->sortByDesc(fn (PracticeSession $session): string => (string) $session->created_at?->getTimestamp())
                ->take(self::NAMED_SESSIONS) as $session
        ) {
            $named[] = [
                'topic' => $session->topic->name,
                'session_date' => $session->created_at?->toIso8601String(),
            ];
        }

        return [
            'requested_count' => count($effect->requestedIds),
            'session_count' => $effect->sessions->count(),
            'missing_count' => $effect->missingCount(),
            'finished_count' => $effect->finishedCount(),
            'unfinished_count' => $effect->unfinishedCount(),
            'archivable_count' => $effect->archivableCount(),
            'all_finished_archived' => $effect->finishedCount() > 0
                && $effect->archivableCount() === 0,
            'names' => $named,
            'more_count' => max(0, $effect->sessions->count() - count($named)),
            'counts' => $effect->counts,
            'progress' => [
                'before' => $effect->before->toArray(),
                'after' => $effect->after->toArray(),
            ],
            'token' => $effect->token(),
        ];
    }
}
