<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CustomReviewAttempts\FinishAttemptEarly;
use App\Actions\CustomReviewAttempts\RecordAttemptRating;
use App\Actions\CustomReviewAttempts\StartCustomReviewAttempt;
use App\Actions\CustomReviewAttempts\UndoAttemptRating;
use App\Http\Requests\RateReviewItemRequest;
use App\Http\Requests\UndoReviewItemRequest;
use App\Models\CustomReview;
use App\Models\CustomReviewAttempt;
use App\Models\CustomReviewAttemptItem;
use App\Queries\AttemptSections;
use App\Queries\CustomReviewContents;
use App\Support\CustomReviewAttemptLabel;
use App\Support\CustomReviewPractice;
use App\Support\ReviewSectionBoard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Practising a saved custom review: starting an attempt, walking through the
 * same exercise screen Review uses, and finishing or leaving it.
 *
 * The screen is Review's, with a different source. Every card is the snapshot
 * the attempt was started with, every rating is kept on the attempt alone, and
 * nothing here reads or writes an item, a schedule or a Review rating. The
 * review's own status can change while an attempt runs; that only stops a new
 * attempt from starting, never the one the learner is in.
 *
 * @phpstan-import-type Section from ReviewSectionBoard
 */
final class CustomReviewAttemptController extends Controller
{
    /**
     * Start an attempt, or resume the one already running. A start that the
     * review cannot support says why in the review's own words and changes
     * nothing.
     *
     * A start that left a whole kind out carries the reason to the attempt's
     * screen, where it is said once and then stays said.
     */
    public function store(
        CustomReview $customReview,
        StartCustomReviewAttempt $startAttempt,
    ): RedirectResponse {
        $result = $startAttempt->handle($customReview);
        $attempt = $result['attempt'];

        if (! $attempt instanceof CustomReviewAttempt) {
            return to_route('customReviews.show', ['customReview' => $customReview->id])
                ->with('attempt_error', $result['note']);
        }

        return to_route('customReviews.attempts.show', [
            'customReview' => $customReview->id,
            'attempt' => $attempt->id,
        ])->with('attempt_notices', $result['notices']);
    }

    /**
     * The attempt's exercise screen, or its finish summary once every item is
     * rated or skipped.
     *
     * The step and item travel in the address exactly as they do in Review, so
     * a refresh, a bookmark or the Back button return to the same place. A
     * finished attempt opened again shows its summary rather than a card.
     */
    public function show(
        Request $request,
        CustomReview $customReview,
        CustomReviewAttempt $attempt,
        AttemptSections $sectionsQuery,
        ReviewSectionBoard $board,
        CustomReviewContents $contents,
    ): Response {
        $this->ensureBelongsTo($customReview, $attempt);

        $attempt->load('items');
        $walk = $sectionsQuery->walk($attempt);
        $sections = $walk['sections'];
        $total = array_sum(array_column($sections, 'total'));
        $rated = array_sum(array_column($sections, 'rated'));
        $settled = array_sum(array_column($sections, 'settled'));
        // Every item still met is rated or skipped — or, in a running attempt
        // whose sessions have all been archived since it started, there is
        // nothing left to meet at all. Either way the walk has reached its end;
        // a finished attempt is shown as finished whatever happened after.
        $nothingLeft = $attempt->isInProgress()
            && $walk['items']->isEmpty()
            && $attempt->items->isNotEmpty();
        $finished = $attempt->isFinished() || ($total > 0 && $settled === $total) || $nothingLeft;
        $counts = $sectionsQuery->counts($walk['items']);
        $latest = $walk['items']
            ->filter(fn (CustomReviewAttemptItem $item): bool => $item->isRated())
            ->sortByDesc('id')
            ->first();

        $current = null;
        $completedSection = null;

        if (! $finished && $sections !== []) {
            $opened = $board->open(
                $sections,
                $request->query('step'),
                $request->query('position'),
            );

            if ($opened !== null && $opened['item'] === null) {
                $completedSection = $board->completed($opened['section'], $sections);
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

        $eligible = $contents->eligibleCounts([$customReview->id])[(int) $customReview->id] ?? 0;
        $canStartAnother = CustomReviewPractice::canStart($customReview->status, $eligible > 0);
        $reviewUrl = route('customReviews.show', ['customReview' => $customReview->id]);
        $attemptUrl = [
            'customReview' => $customReview->id,
            'attempt' => $attempt->id,
        ];

        return Inertia::render('custom-reviews/Attempt', [
            'notFound' => $sections === [] && ! $finished,
            'finished' => $finished,
            // The kinds this attempt cannot show anything of: those whose
            // items were archived while it ran, and those it was started
            // without. Each is said once, in the attempt's own calm words.
            'notices' => $this->notices($request, $walk['notices']),
            'sections' => array_map($board->summary(...), $sections),
            'current' => $current,
            'completedSection' => $completedSection,
            'position' => ['current' => $rated, 'total' => $total],
            'progress' => $total === 0 ? 0 : (int) round(($rated / $total) * 100),
            'counts' => [
                'hard' => $counts['hard'],
                'okay' => $counts['okay'],
                'easy' => $counts['easy'],
            ],
            'reviewedCount' => $rated,
            'undo' => $latest instanceof CustomReviewAttemptItem
                ? [
                    'kind' => $latest->kind->value,
                    'item_identity' => $latest->item_identity,
                ]
                : null,
            // The quiet line above the progress: the review's name and which
            // attempt this is.
            'title' => CustomReviewAttemptLabel::heading($customReview->name, $attempt),
            'attempt' => [
                'id' => (int) $attempt->id,
                'number' => $attempt->number,
                'number_label' => $attempt->number === null
                    ? 'Not started'
                    : 'Attempt '.$attempt->number,
                'status' => $attempt->status->value,
                'status_label' => $attempt->status->label(),
                'total' => $total,
                'rated' => $rated,
                'skipped' => $counts['skipped'],
                'encouragement' => CustomReviewAttemptLabel::ENCOURAGEMENT,
            ],
            'urls' => [
                'rate' => route('customReviews.attempts.rate', $attemptUrl),
                'undo' => route('customReviews.attempts.undo', $attemptUrl),
                'finish_early' => route('customReviews.attempts.finishEarly', $attemptUrl),
                'exit' => $reviewUrl,
                'done' => $reviewUrl,
                'start_another' => $canStartAnother
                    ? route('customReviews.attempts.store', ['customReview' => $customReview->id])
                    : null,
            ],
        ]);
    }

    /**
     * Save one rating on the attempt and open the next item still to rate.
     * Repeating the same request changes nothing.
     */
    public function rate(
        RateReviewItemRequest $request,
        CustomReview $customReview,
        CustomReviewAttempt $attempt,
        RecordAttemptRating $recordAttemptRating,
        AttemptSections $sectionsQuery,
        ReviewSectionBoard $board,
    ): RedirectResponse {
        $this->ensureBelongsTo($customReview, $attempt);

        $attempt = $recordAttemptRating->handle(
            $attempt,
            $request->kind(),
            $request->itemIdentity(),
            $request->rating(),
        );
        $attempt->load('items');

        $next = $board->nextAfterRating(
            $sectionsQuery->walk($attempt)['sections'],
            $request->kind(),
            $request->itemIdentity(),
        );

        return $this->toAttempt($customReview, $attempt, $next);
    }

    /**
     * Take back the attempt's rating for one item and reopen it, ready to rate
     * again. Every other rating stays exactly as it was.
     */
    public function undo(
        UndoReviewItemRequest $request,
        CustomReview $customReview,
        CustomReviewAttempt $attempt,
        UndoAttemptRating $undoAttemptRating,
        AttemptSections $sectionsQuery,
        ReviewSectionBoard $board,
    ): RedirectResponse {
        $this->ensureBelongsTo($customReview, $attempt);

        $attempt = $undoAttemptRating->handle(
            $attempt,
            $request->kind(),
            $request->itemIdentity(),
        );
        $attempt->load('items');

        $place = $board->locate(
            $sectionsQuery->walk($attempt)['sections'],
            $request->kind(),
            $request->itemIdentity(),
        );

        return $this->toAttempt($customReview, $attempt, $place);
    }

    /**
     * End a running attempt before every item is rated. What was rated stays;
     * an attempt with no rating at all is left In progress.
     */
    public function finishEarly(
        CustomReview $customReview,
        CustomReviewAttempt $attempt,
        FinishAttemptEarly $finishAttemptEarly,
    ): RedirectResponse {
        $this->ensureBelongsTo($customReview, $attempt);

        $finishAttemptEarly->handle($attempt);

        return $this->toAttempt($customReview, $attempt, null);
    }

    /**
     * @param  array{step: string, position: int}|null  $place
     */
    private function toAttempt(
        CustomReview $customReview,
        CustomReviewAttempt $attempt,
        ?array $place,
    ): RedirectResponse {
        $parameters = [
            'customReview' => $customReview->id,
            'attempt' => $attempt->id,
        ];

        if ($place !== null) {
            $parameters['step'] = $place['step'];
            $parameters['position'] = $place['position'];
        }

        return to_route('customReviews.attempts.show', $parameters);
    }

    /** An attempt can only ever be opened under the review it belongs to. */
    private function ensureBelongsTo(CustomReview $customReview, CustomReviewAttempt $attempt): void
    {
        abort_unless((int) $attempt->custom_review_id === (int) $customReview->id, 404);
    }

    /**
     * The kinds left out, each said once: the ones read from the attempt's own
     * items, then the ones the start carried across.
     *
     * @param  list<string>  $walkNotices
     * @return list<string>
     */
    private function notices(Request $request, array $walkNotices): array
    {
        $started = $request->session()->get('attempt_notices');

        $lines = is_array($started)
            ? array_values(array_filter($started, is_string(...)))
            : [];

        /** @var list<string> $lines */
        return array_values(array_unique([...$walkNotices, ...$lines]));
    }
}
