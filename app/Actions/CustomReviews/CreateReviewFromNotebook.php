<?php

declare(strict_types=1);

namespace App\Actions\CustomReviews;

use App\Actions\CustomReviewAttempts\StartCustomReviewAttempt;
use App\Enums\CustomReviewStatus;
use App\Enums\FeedbackType;
use App\Enums\NotebookReviewOutcome;
use App\Models\CustomReview;
use App\Models\CustomReviewAttempt;
use App\Models\CustomReviewItem;
use App\Models\Notebook;
use App\Queries\NextCustomReviewName;
use App\Queries\NotebookReviewCandidates;
use App\Support\CustomReviewKinds;
use App\Support\CustomReviewRules;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Create review and practise: one notebook's chosen items become a new saved
 * review that is explicitly marked Finished, and its first attempt starts.
 *
 * Every answer is decided from what the notebook holds right now, never from
 * what the client claims. A candidate list that has changed is refused rather
 * than partly accepted, so no review can be built from a stale selection or
 * from another notebook's matching spelling. The maximum and allowed kinds
 * are checked before the recommended-size warning; the size warning is checked
 * before anything is written. Cancelling therefore leaves nothing behind.
 *
 * The creation key makes the request repeatable. A retry after a successful
 * creation finds the same review and starts from it again; a retry after a
 * failed start keeps the saved review and tries the start again, never making
 * a replacement. Only this new review is marked Finished — ordinary creation
 * and ordinary adds keep their Empty/Draft rules.
 *
 * The review's membership is fixed here. A later notebook row does not join
 * it, though explicit adds still follow the ordinary custom-review rules.
 */
final readonly class CreateReviewFromNotebook
{
    public const string STALE_SELECTION = 'Some items changed while you were choosing. Review the list and try again.';

    public const string NO_CANDIDATES = 'There is nothing in this notebook to review yet.';

    public const string NO_ELIGIBLE = 'None of the selected items can be practised yet. Add the missing meanings or bold the key part of a grammar note.';

    public const string TOO_MANY = 'Choose at most '.CustomReviewRules::MAX_ITEMS.' items.';

    public const string START_FAILED = 'The review was saved, but practice could not start. Open the review and try again.';

    /** How many rows one insert carries; well under every driver's limit. */
    private const int CHUNK = 300;

    public function __construct(
        private NotebookReviewCandidates $candidates,
        private NextCustomReviewName $nextName,
        private StartCustomReviewAttempt $startAttempt,
    ) {}

    /**
     * @param  list<string>|null  $identities  null means every candidate
     * @param  list<FeedbackType>|null  $kinds  null means every kind
     * @return array{
     *     outcome: NotebookReviewOutcome,
     *     review: CustomReview|null,
     *     attempt: CustomReviewAttempt|null,
     *     count: int,
     *     maximum: int,
     *     message: string|null
     * }
     */
    public function handle(
        Notebook $notebook,
        string $creationKey,
        ?array $identities,
        ?array $kinds,
        bool $confirmed,
    ): array {
        // A repeated request belongs to the review the first one made, even if
        // the notebook has changed since. It never builds a second review.
        $existing = CustomReview::query()
            ->where('creation_key', $creationKey)
            ->first();

        if ($existing instanceof CustomReview) {
            return $this->start($existing);
        }

        $map = $this->candidates->map($notebook, $kinds);

        if ($map === []) {
            return $this->answer(NotebookReviewOutcome::NoCandidates, null, null, 0, self::NO_CANDIDATES);
        }

        if ($identities === null) {
            if (count($map) > CustomReviewRules::MAX_ITEMS) {
                return $this->answer(NotebookReviewOutcome::NeedsSelection, null, null, count($map), null);
            }

            $selected = array_keys($map);
        } else {
            $requested = array_values(array_unique($identities));
            $selected = array_values(array_filter(
                $requested,
                fn (string $identity): bool => isset($map[$identity]),
            ));

            // A selection naming an item this notebook no longer holds is
            // refused whole; nothing is silently dropped.
            if (count($selected) !== count($requested)) {
                return $this->answer(
                    NotebookReviewOutcome::StaleSelection,
                    null,
                    null,
                    count($selected),
                    self::STALE_SELECTION,
                );
            }

            if (count($selected) > CustomReviewRules::MAX_ITEMS) {
                return $this->answer(
                    NotebookReviewOutcome::TooManySelected,
                    null,
                    null,
                    count($selected),
                    self::TOO_MANY,
                );
            }
        }

        $eligibleCount = count(array_filter(
            $selected,
            fn (string $identity): bool => $map[$identity]['eligible'],
        ));

        if ($selected === [] || $eligibleCount === 0) {
            return $this->answer(
                NotebookReviewOutcome::NoEligible,
                null,
                null,
                count($selected),
                self::NO_ELIGIBLE,
            );
        }

        // The hard maximum and the kinds have already been applied; only now
        // does the recommended size ask, and a confirmation can never cross
        // the maximum.
        if (! $confirmed && count($selected) > CustomReviewRules::RECOMMENDED_ITEMS) {
            return $this->answer(
                NotebookReviewOutcome::NeedsConfirmation,
                null,
                null,
                count($selected),
                null,
            );
        }

        try {
            $review = $this->create($creationKey, $selected, $map, $kinds);
        } catch (UniqueConstraintViolationException) {
            // A double press reached the database first; its review is this
            // request's review too.
            $review = CustomReview::query()
                ->where('creation_key', $creationKey)
                ->firstOrFail();
        }

        return $this->start($review);
    }

    /**
     * Save the new review and its fixed membership. The review is marked
     * Finished only after its items are written, because an Empty review is
     * never started.
     *
     * @param  list<string>  $selected
     * @param  array<string, array{kind: string, identity: string, title: string, meaning: string, example: string, eligible: bool, reason: string|null}>  $map
     * @param  list<FeedbackType>|null  $kinds
     */
    private function create(string $creationKey, array $selected, array $map, ?array $kinds): CustomReview
    {
        return DB::transaction(function () use ($creationKey, $selected, $map, $kinds): CustomReview {
            $review = new CustomReview;
            $review->name = $this->nextName->handle();
            $review->status = CustomReviewStatus::Empty;
            $review->allowed_kinds = $kinds === null ? null : CustomReviewKinds::storedChoice($kinds);
            $review->creation_key = $creationKey;
            $review->save();

            $addedAt = now();
            $rows = [];

            foreach ($selected as $identity) {
                $rows[] = [
                    'custom_review_id' => $review->id,
                    'kind' => $map[$identity]['kind'],
                    'item_identity' => $identity,
                    'added_at' => $addedAt,
                ];
            }

            foreach (array_chunk($rows, self::CHUNK) as $chunk) {
                CustomReviewItem::query()->insertOrIgnore($chunk);
            }

            // The learner's explicit readiness choice, for this new review
            // only. Ordinary creation never comes through here.
            $review->status = CustomReviewStatus::Finished;
            $review->save();

            return $review;
        });
    }

    /**
     * Start the attempt the learner asked for, or say honestly that the saved
     * review could not start. Either way the review stays, so recovery never
     * asks the learner to make it again.
     *
     * @return array{
     *     outcome: NotebookReviewOutcome,
     *     review: CustomReview|null,
     *     attempt: CustomReviewAttempt|null,
     *     count: int,
     *     maximum: int,
     *     message: string|null
     * }
     */
    private function start(CustomReview $review): array
    {
        $count = (int) CustomReviewItem::query()
            ->where('custom_review_id', $review->id)
            ->count();

        try {
            $result = $this->startAttempt->handle($review);
        } catch (Throwable $exception) {
            report($exception);

            return $this->answer(
                NotebookReviewOutcome::CreatedStartFailed,
                $review,
                null,
                $count,
                self::START_FAILED,
            );
        }

        $attempt = $result['attempt'];

        if (! $attempt instanceof CustomReviewAttempt) {
            return $this->answer(
                NotebookReviewOutcome::CreatedStartFailed,
                $review,
                null,
                $count,
                self::START_FAILED,
            );
        }

        return $this->answer(NotebookReviewOutcome::Created, $review, $attempt, $count, null);
    }

    /**
     * @return array{
     *     outcome: NotebookReviewOutcome,
     *     review: CustomReview|null,
     *     attempt: CustomReviewAttempt|null,
     *     count: int,
     *     maximum: int,
     *     message: string|null
     * }
     */
    private function answer(
        NotebookReviewOutcome $outcome,
        ?CustomReview $review,
        ?CustomReviewAttempt $attempt,
        int $count,
        ?string $message,
    ): array {
        return [
            'outcome' => $outcome,
            'review' => $review,
            'attempt' => $attempt,
            'count' => $count,
            'maximum' => CustomReviewRules::MAX_ITEMS,
            'message' => $message,
        ];
    }
}
