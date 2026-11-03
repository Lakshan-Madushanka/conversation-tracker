<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reviews\RecordRating;
use App\Actions\Reviews\StartReview;
use App\Actions\Reviews\UndoRating;
use App\Http\Requests\RateReviewItemRequest;
use App\Http\Requests\UndoReviewItemRequest;
use App\Http\Resources\ReviewCardResource;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeSession;
use App\Models\Rating;
use App\Models\Review;
use App\Models\ReviewItem;
use App\Queries\ReviewCandidates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Review and re-practice: the home that starts a review, the distraction-free
 * exercise screen, and the ratings that walk through it.
 *
 * The review lives at its own address (`?review=` on the exercise page), so a
 * refresh, a bookmark or the Back button return to the same sitting and to the
 * next item not yet rated. A missing or unknown review shows a calm "couldn't
 * find that review" instead of an error.
 */
class ReviewController extends Controller
{
    public function index(ReviewCandidates $candidates): Response
    {
        return Inertia::render('review/Index', [
            'reviewableCount' => $candidates->count(),
            'hasFinishedSessions' => PracticeSession::query()->study()->exists(),
        ]);
    }

    /**
     * Start a review of the items most worth revisiting. Nothing reviewable
     * goes back to Review Home, which explains what fills it.
     */
    public function store(StartReview $startReview): RedirectResponse
    {
        $review = $startReview->handle();

        if (! $review instanceof Review) {
            return to_route('review.index');
        }

        return to_route('review.session', ['review' => $review->id]);
    }

    /**
     * The next item in this review, or the finish screen once every item has
     * been rated or can no longer be shown.
     */
    public function show(Request $request, ReviewCandidates $candidates): Response
    {
        $review = $this->reviewFrom($request);

        if (! $review instanceof Review) {
            return Inertia::render('review/Session', $this->emptyPayload(notFound: true));
        }

        $items = $review->items;
        $ratings = $review->ratings;

        $rated = $ratings->mapWithKeys(fn (Rating $rating): array => [
            $candidates->key($rating->kind, $rating->item_identity) => true,
        ]);

        $unrated = $items->reject(
            fn (ReviewItem $item): bool => $rated->has($candidates->key($item->kind, $item->item_identity)),
        );

        $entries = $candidates->entriesFor($unrated
            ->map(fn (ReviewItem $item): array => [
                'kind' => $item->kind,
                'identity' => $item->item_identity,
            ])
            ->values()
            ->all());

        $card = null;
        $skipped = 0;

        foreach ($unrated as $item) {
            $entry = $entries[$candidates->key($item->kind, $item->item_identity)] ?? null;

            // A removed or archived item is skipped; the review moves on.
            if (! $entry instanceof PracticeFeedbackEntry) {
                $skipped += 1;

                continue;
            }

            $card = (new ReviewCardResource($entry))->resolve();

            break;
        }

        $counts = $ratings->countBy(fn (Rating $rating): string => $rating->rating->value);
        $total = $items->count();
        $completed = $ratings->count() + $skipped;
        $latest = $ratings->sortByDesc('id')->first();

        return Inertia::render('review/Session', [
            'notFound' => false,
            'finished' => $card === null,
            'card' => $card,
            'reviewId' => $review->id,
            'position' => [
                'current' => $total === 0 ? 0 : min($total, $completed + 1),
                'total' => $total,
            ],
            'progress' => $total === 0
                ? 0
                : (int) round(($completed / $total) * 100),
            'counts' => [
                'hard' => $counts->get('hard', 0),
                'okay' => $counts->get('okay', 0),
                'easy' => $counts->get('easy', 0),
            ],
            'reviewedCount' => $ratings->count(),
            'undo' => $latest instanceof Rating
                ? [
                    'kind' => $latest->kind->value,
                    'item_identity' => $latest->item_identity,
                ]
                : null,
            'canReviewMore' => $candidates->count() > 0,
        ]);
    }

    /**
     * Save one rating immediately and move to the next item. Repeating the same
     * request changes nothing, so a double tap or a retry counts once.
     */
    public function rate(
        RateReviewItemRequest $request,
        Review $review,
        RecordRating $recordRating,
    ): RedirectResponse {
        abort_unless(
            $review->items()
                ->where('kind', $request->kind()->value)
                ->where('item_identity', $request->itemIdentity())
                ->exists(),
            404,
        );

        $recordRating->handle(
            $review,
            $request->kind(),
            $request->itemIdentity(),
            $request->rating(),
        );

        return to_route('review.session', ['review' => $review->id]);
    }

    /**
     * Take back the last rating. Every other rating stays exactly as it was.
     */
    public function undo(
        UndoReviewItemRequest $request,
        Review $review,
        UndoRating $undoRating,
    ): RedirectResponse {
        $undoRating->handle($review, $request->kind(), $request->itemIdentity());

        return to_route('review.session', ['review' => $review->id]);
    }

    /**
     * The review named in the address, or null when the address is stale,
     * hand-made or points at nothing.
     */
    private function reviewFrom(Request $request): ?Review
    {
        $value = $request->query('review');

        if (! is_numeric($value)) {
            return null;
        }

        return Review::query()
            ->with(['items', 'ratings'])
            ->find((int) $value);
    }

    /**
     * @return array{
     *     notFound: bool,
     *     finished: bool,
     *     card: null,
     *     position: array{current: int, total: int},
     *     progress: int,
     *     counts: array{hard: int, okay: int, easy: int},
     *     reviewedCount: int,
     *     undo: null,
     *     canReviewMore: bool,
     *     reviewId: null
     * }
     */
    private function emptyPayload(bool $notFound): array
    {
        return [
            'notFound' => $notFound,
            'finished' => false,
            'card' => null,
            'position' => ['current' => 0, 'total' => 0],
            'progress' => 0,
            'counts' => ['hard' => 0, 'okay' => 0, 'easy' => 0],
            'reviewedCount' => 0,
            'undo' => null,
            'canReviewMore' => false,
            'reviewId' => null,
        ];
    }
}
