<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\SessionDeletionEffect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The delete warning, as the page needs it: the session being removed, exactly
 * what it holds, progress before and after, and the signed token to confirm.
 *
 * @mixin SessionDeletionEffect
 */
final class SessionDeletionPreviewResource extends JsonResource
{
    public function __construct(SessionDeletionEffect $effect)
    {
        parent::__construct($effect);
    }

    /**
     * @return array{
     *     session: array{
     *         id: int,
     *         topic: string,
     *         session_date: string|null,
     *         finished: bool,
     *         archived: bool
     *     },
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
        /** @var SessionDeletionEffect $effect */
        $effect = $this->resource;
        $session = $effect->session;

        return [
            'session' => [
                'id' => $session->id,
                'topic' => $session->topic->name,
                'session_date' => $session->created_at?->toIso8601String(),
                'finished' => $session->isFinished(),
                'archived' => $session->isArchived(),
            ],
            'counts' => $effect->counts,
            'progress' => [
                'before' => $effect->before->toArray(),
                'after' => $effect->after->toArray(),
            ],
            'token' => $effect->token(),
        ];
    }
}
