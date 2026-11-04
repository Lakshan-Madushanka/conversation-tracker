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
use App\Models\Rating;
use App\Models\Review;
use App\Queries\ReviewSteps;
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
 * next item not yet rated. Every item is also a step (`&step=`), so the learner
 * can open any of them at any time instead of going in order. A missing or
 * unknown review shows a calm "couldn't find that review" instead of an error.
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
     * Every item in this review as a step, with the step named in the address
     * (or the first one still to rate) shown as a card. Once every step has
     * a rating the finish screen takes over.
     */
    public function show(
        Request $request,
        ReviewSteps $steps,
        TodaysReview $todaysReview,
        LearnerDay $day,
    ): Response {
        $review = $this->reviewFrom($request);

        if (! $review instanceof Review) {
            return Inertia::render('review/Session', $this->emptyPayload(notFound: true));
        }

        $allSteps = $steps->for($review);
        $current = $steps->current($allSteps, $request->query('step'));
        $currentNumber = $current['number'] ?? null;
        $ratings = $review->ratings;
        $total = count($allSteps);
        $rated = count(array_filter(
            $allSteps,
            fn (array $step): bool => $step['rating'] !== null,
        ));

        $finished = $total === 0 || $rated === $total;
        $counts = $ratings->countBy(fn (Rating $rating): string => $rating->rating->value);
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
            'steps' => $allSteps,
            'currentStep' => $finished ? null : $currentNumber,
            'card' => $finished ? null : $current['card'],
            'cardRating' => $finished ? null : $current['rating'],
            'reviewId' => $review->id,
            'isDailyReview' => $review->is_daily_review,
            'position' => [
                'current' => $finished ? $total : ($currentNumber ?? 0),
                'total' => $total,
            ],
            'progress' => $total === 0
                ? 0
                : (int) round(($rated / $total) * 100),
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
     * Save one rating immediately and open the next step still to rate.
     * Repeating the same request changes nothing, so a double tap or a retry
     * counts once.
     */
    public function rate(
        RateReviewItemRequest $request,
        Review $review,
        RecordRating $recordRating,
        ReviewSteps $steps,
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

        $review->load(['items', 'ratings']);

        $next = $steps->nextUnrated(
            $steps->for($review),
            $request->kind(),
            $request->itemIdentity(),
        );

        return to_route('review.session', $next === null
            ? ['review' => $review->id]
            : ['review' => $review->id, 'step' => $next]);
    }

    /**
     * Take back the last rating and reopen the item it belonged to, ready to
     * rate again. Every other rating stays exactly as it was.
     */
    public function undo(
        UndoReviewItemRequest $request,
        Review $review,
        UndoRating $undoRating,
        ReviewSteps $steps,
    ): RedirectResponse {
        $undoRating->handle($review, $request->kind(), $request->itemIdentity());

        $review->load(['items', 'ratings']);
        $number = $steps->numberOf(
            $steps->for($review),
            $request->kind(),
            $request->itemIdentity(),
        );

        return to_route('review.session', $number === null
            ? ['review' => $review->id]
            : ['review' => $review->id, 'step' => $number]);
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
     *     steps: list<array<string, mixed>>,
     *     currentStep: null,
     *     card: null,
     *     cardRating: null,
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
            'steps' => [],
            'currentStep' => null,
            'card' => null,
            'cardRating' => null,
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
