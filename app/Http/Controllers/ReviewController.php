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
use App\Queries\ReviewSections;
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
 * A review is shown as one section per exercise kind, in a fixed friendly
 * order. Every section can be opened at any time (`&step=` names it and
 * `&position=` the item inside it), so a refresh, a bookmark or the Back
 * button return to the same sitting and to the next item still to rate. A
 * missing or unknown review shows a calm "couldn't find that review" instead
 * of an error.
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
                'mistakes' => $collection->attentionCount(FeedbackType::Mistake),
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
     * Every section of this review, with the section and item named in the
     * address (or the first one still to rate) shown as a card. Once every
     * item has a rating the finish screen takes over.
     */
    public function show(
        Request $request,
        ReviewSections $sectionsQuery,
        TodaysReview $todaysReview,
        LearnerDay $day,
    ): Response {
        $review = $this->reviewFrom($request);

        if (! $review instanceof Review) {
            return Inertia::render('review/Session', $this->emptyPayload(notFound: true));
        }

        $sections = $sectionsQuery->for($review);
        $total = array_sum(array_column($sections, 'total'));
        $rated = array_sum(array_column($sections, 'rated'));
        $finished = $total === 0 || $rated === $total;

        $ratings = $review->ratings;
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

        $current = null;
        $completedSection = null;

        if (! $finished) {
            $opened = $sectionsQuery->open(
                $sections,
                $request->query('step'),
                $request->query('position'),
            );

            if ($opened !== null && $opened['item'] === null) {
                $completedSection = $this->completedSection($opened['section'], $sections);
            } elseif ($opened !== null) {
                $index = array_search($opened['item'], $opened['section']['items'], true);
                $current = [
                    'step' => $opened['section']['key'],
                    'label' => $opened['section']['label'],
                    'position' => $opened['item']['position'],
                    'stepPosition' => $index === false ? 1 : $index + 1,
                    'total' => $opened['section']['total'],
                    'card' => $opened['item']['card'],
                    'rating' => $opened['item']['rating'],
                ];
            }
        }

        $canReviewMore = $finished
            && $todaysReview->suggest(excludingRatedToday: true, request: $request)->count() > 0;

        return Inertia::render('review/Session', [
            'notFound' => false,
            'finished' => $finished,
            'sections' => array_map($this->sectionSummary(...), $sections),
            'current' => $current,
            'completedSection' => $completedSection,
            'reviewId' => $review->id,
            'isDailyReview' => $review->is_daily_review,
            'position' => ['current' => $rated, 'total' => $total],
            'progress' => $total === 0 ? 0 : (int) round(($rated / $total) * 100),
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
     * Save one rating immediately and open the next item still to rate.
     * Repeating the same request changes nothing, so a double tap or a retry
     * counts once.
     */
    public function rate(
        RateReviewItemRequest $request,
        Review $review,
        RecordRating $recordRating,
        ReviewSections $sectionsQuery,
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

        $next = $sectionsQuery->nextAfterRating(
            $sectionsQuery->for($review),
            $request->kind(),
            $request->itemIdentity(),
        );

        return to_route('review.session', $next === null
            ? ['review' => $review->id]
            : ['review' => $review->id, 'step' => $next['step'], 'position' => $next['position']]);
    }

    /**
     * Take back the last rating and reopen the item it belonged to, ready to
     * rate again. Every other rating stays exactly as it was.
     */
    public function undo(
        UndoReviewItemRequest $request,
        Review $review,
        UndoRating $undoRating,
        ReviewSections $sectionsQuery,
    ): RedirectResponse {
        $undoRating->handle($review, $request->kind(), $request->itemIdentity());

        $review->load(['items', 'ratings']);
        $place = $sectionsQuery->locate(
            $sectionsQuery->for($review),
            $request->kind(),
            $request->itemIdentity(),
        );

        return to_route('review.session', $place === null
            ? ['review' => $review->id]
            : ['review' => $review->id, 'step' => $place['step'], 'position' => $place['position']]);
    }

    /**
     * The summary the stepper shows: name, icon, progress and its own address,
     * without the items themselves.
     *
     * @param  array{key: string, label: string, icon: string, number: int, total: int, rated: int, completed: bool, counts: array{hard: int, okay: int, easy: int}, url: string, items: list<array<string, mixed>>}  $section
     * @return array{key: string, label: string, icon: string, number: int, total: int, rated: int, completed: bool, counts: array{hard: int, okay: int, easy: int}, url: string}
     */
    private function sectionSummary(array $section): array
    {
        unset($section['items']);

        return $section;
    }

    /**
     * What a finished section shows instead of a card: its own tally and a way
     * on to the next section still holding unrated items.
     *
     * @param  array{key: string, label: string, icon: string, number: int, total: int, rated: int, completed: bool, counts: array{hard: int, okay: int, easy: int}, url: string, items: list<array<string, mixed>>}  $section
     * @param  list<array{key: string, label: string, icon: string, number: int, total: int, rated: int, completed: bool, counts: array{hard: int, okay: int, easy: int}, url: string, items: list<array<string, mixed>>}>  $sections
     * @return array{key: string, label: string, total: int, counts: array{hard: int, okay: int, easy: int}, next_url: string|null}
     */
    private function completedSection(array $section, array $sections): array
    {
        return [
            'key' => $section['key'],
            'label' => $section['label'],
            'total' => $section['total'],
            'counts' => $section['counts'],
            'next_url' => $this->nextUnfinishedUrl($section, $sections),
        ];
    }

    /**
     * @param  array{key: string, label: string, icon: string, number: int, total: int, rated: int, completed: bool, counts: array{hard: int, okay: int, easy: int}, url: string, items: list<array<string, mixed>>}  $section
     * @param  list<array{key: string, label: string, icon: string, number: int, total: int, rated: int, completed: bool, counts: array{hard: int, okay: int, easy: int}, url: string, items: list<array<string, mixed>>}>  $sections
     */
    private function nextUnfinishedUrl(array $section, array $sections): ?string
    {
        $count = count($sections);
        $start = 0;

        foreach ($sections as $index => $candidate) {
            if ($candidate['key'] === $section['key']) {
                $start = $index;

                break;
            }
        }

        for ($offset = 1; $offset <= $count; $offset += 1) {
            $candidate = $sections[($start + $offset) % $count];

            if (! $candidate['completed']) {
                return $candidate['url'];
            }
        }

        return null;
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
     *     sections: list<array<string, mixed>>,
     *     current: null,
     *     completedSection: null,
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
            'sections' => [],
            'current' => null,
            'completedSection' => null,
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
