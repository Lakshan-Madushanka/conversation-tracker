<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Enums\CollectionItemStatus;
use App\Enums\FeedbackType;
use App\Models\ReviewSuggestionDecline;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the learner's "Not now" to one status suggestion, for the run of
 * ratings it was said during.
 *
 * The run is named by the rating that began it, so the suggestion stays quiet
 * while that run grows and is free to come back when a new one reaches the
 * threshold. One row per item and status is kept: a later refusal replaces the
 * earlier one instead of piling up. Nothing else changes — no star, no status,
 * no session.
 */
final class DeclineStatusSuggestion
{
    public function handle(
        FeedbackType $kind,
        string $itemIdentity,
        CollectionItemStatus $status,
        int $runStartedId,
    ): ReviewSuggestionDecline {
        return DB::transaction(function () use ($kind, $itemIdentity, $status, $runStartedId): ReviewSuggestionDecline {
            /** @var ReviewSuggestionDecline|null $decline */
            $decline = ReviewSuggestionDecline::query()
                ->where('kind', $kind->value)
                ->where('item_identity', $itemIdentity)
                ->where('status', $status->value)
                ->lockForUpdate()
                ->first();

            if ($decline !== null) {
                $decline->run_started_id = $runStartedId;
                $decline->save();

                return $decline;
            }

            try {
                return ReviewSuggestionDecline::query()->create([
                    'kind' => $kind,
                    'item_identity' => $itemIdentity,
                    'status' => $status,
                    'run_started_id' => $runStartedId,
                ]);
            } catch (UniqueConstraintViolationException) {
                // The same refusal arrived twice at once; the row that won is
                // the answer, so nothing more is written.
                return ReviewSuggestionDecline::query()
                    ->where('kind', $kind->value)
                    ->where('item_identity', $itemIdentity)
                    ->where('status', $status->value)
                    ->firstOrFail();
            }
        });
    }
}
