<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reviews\RecordRating;
use App\Actions\Reviews\StartReview;
use App\Actions\Reviews\UndoRating;
use App\Enums\FeedbackType;
use App\Http\Requests\RateReviewItemRequest;
use App\Http\Requests\StartReviewRequest;
use App\Http\Requests\UndoReviewItemRequest;
use App\Http\Resources\ReviewCardResource;
use App\Models\PracticeFeedbackEntry;
use App\Models\Rating;
use App\Models\Review;
use App\Models\ReviewItem;
use App\Queries\ReviewCandidates;
use App\Queries\TableCollection;
use App\Queries\TodaysReview;
use App\Support\LearnerDay;
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
    public function index(
        Request $request,
        TodaysReview $todaysReview,
        TableCollection $collection,
    ): Response {
        return Inertia::render('review/Index', [
            'todayReview' => $todaysReview->card($request),
            // The same shared "needs attention" rule the collections use, so
            // the note and the collections always agree.
            'waitingForFix' => [
                'vocabulary' => $collection->attentionCount(FeedbackType::Vocabulary),
                'phrases' => $collection->attentionCount(FeedbackType::Phrase),
            ],
        ]);
    }

    /**
     * Start a review built from the items most worth revisiting at this
     * moment. `daily` marks the sitting as the day's suggestion; `more`
     * starts the "Review a bit more" sitting, which leaves out the items
     * already rated today. Nothing reviewable goes back to Review Home,
     * which explains what fills it.
     */
    public function store(
        StartReviewRequest $request,
        StartReview $startReview,
    ): RedirectResponse {
        $review = $startReview->handle(
            daily: $request->daily(),
            excludingRatedToday: $request->more(),
            request: $request,
        );

        if (! $review instanceof Review) {
            return to_route('review.index');
        }

        return to_route('review.session', ['review' => $review->id]);
    }

    /**
     * The next item in this review, or the finish screen once every item has
     * been rated or can no longer be shown.
     */
    public function show(
        Request $request,
        ReviewCandidates $candidates,
        TodaysReview $todaysReview,
        LearnerDay $day,
    ): Response {
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

        $finished = $card === null;
        $counts = $ratings->countBy(fn (Rating $rating): string => $rating->rating->value);
        $total = $items->count();
        $completed = $ratings->count() + $skipped;
        $latest = $ratings->sortByDesc('id')->first();

        // Reaching the finish screen completes a daily review. The learner's
        // local day is recorded so the card can say "You've reviewed today"
        // without keeping anything else. A review left part-way never
        // completes, and the card never nags.
        if ($finished && $review->is_daily_review && $review->completed_on === null) {
            $review->completed_on = $day->today($request);
            $review->save();
        }

        $canReviewMore = $finished
            && $todaysReview->suggest(excludingRatedToday: true, request: $request)->count() > 0;

        return Inertia::render('review/Session', [
            'notFound' => false,
            'finished' => $finished,
            'card' => $card,
            'reviewId' => $review->id,
            'isDailyReview' => $review->is_daily_review,
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
            'canReviewMore' => $canReviewMore,
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
     *     isDailyReview: bool,
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
            'isDailyReview' => false,
            'reviewId' => null,
        ];
    }
}
