<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Collections\SaveItemMark;
use App\Actions\Reviews\DeclineStatusSuggestion;
use App\Http\Requests\StatusSuggestionRequest;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;

/**
 * The learner's answers to the status suggestions the finish screen offers.
 *
 * Confirming uses the very status change Collections already offers, with only
 * the status named, so the star, the notes, the sessions and the streak all
 * stay exactly as they were. Declining keeps a refusal for the run of ratings
 * the suggestion came from and changes nothing else. Both answers return to the
 * finish screen, where the summary stays as it was.
 */
class ReviewSuggestionController extends Controller
{
    public function confirm(
        StatusSuggestionRequest $request,
        Review $review,
        SaveItemMark $saveItemMark,
    ): RedirectResponse {
        $this->ensureReviewed($review, $request);

        $saveItemMark->handle(
            $request->kind(),
            $request->itemIdentity(),
            // A suggestion only ever offers a status — the star is left alone.
            null,
            $request->status(),
        );

        return to_route('review.session', ['review' => $review->id]);
    }

    public function decline(
        StatusSuggestionRequest $request,
        Review $review,
        DeclineStatusSuggestion $declineStatusSuggestion,
    ): RedirectResponse {
        $this->ensureReviewed($review, $request);

        $declineStatusSuggestion->handle(
            $request->kind(),
            $request->itemIdentity(),
            $request->status(),
            $request->runStartedId(),
        );

        return to_route('review.session', ['review' => $review->id]);
    }

    /**
     * The suggestion must name an item this sitting actually holds; anything
     * else is a stale or hand-made address.
     */
    private function ensureReviewed(Review $review, StatusSuggestionRequest $request): void
    {
        abort_unless(
            $review->items()
                ->where('kind', $request->kind()->value)
                ->where('item_identity', $request->itemIdentity())
                ->exists(),
            404,
        );
    }
}
