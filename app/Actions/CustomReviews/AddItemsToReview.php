<?php

declare(strict_types=1);

namespace App\Actions\CustomReviews;

use App\Enums\CustomReviewAddOutcome;
use App\Enums\FeedbackType;
use App\Models\CustomReview;
use App\Queries\CustomReviewItems;
use App\Queries\UnfinishedCustomReviews;
use App\Support\CustomReviewRules;

/**
 * Gathers the chosen items into a review the learner is building.
 *
 * This is the one place that decides which review an add belongs to, so the
 * client asks and the server never guesses when the learner has a choice:
 *
 * - a review the learner named, or is standing in, takes the items;
 * - one review being built takes them without a word;
 * - several ask which one, most recently created first;
 * - none creates one and names it.
 *
 * The recommended size is checked here too, before anything is written, and
 * the learner's answer arrives on the same request. Nothing is ever refused:
 * a confirmed add goes through whole, whatever its size.
 */
final readonly class AddItemsToReview
{
    public function __construct(
        private UnfinishedCustomReviews $unfinishedReviews,
        private CustomReviewItems $items,
        private CreateCustomReview $createCustomReview,
        private AddCustomReviewItems $addCustomReviewItems,
    ) {}

    /**
     * @param  list<string>  $identities
     * @param  int|null  $reviewId  the review the learner chose or is standing in
     * @return array{
     *     outcome: CustomReviewAddOutcome,
     *     review: CustomReview|null,
     *     choices: list<array{review: CustomReview, item_count: int}>,
     *     added: list<string>,
     *     already: list<string>,
     *     resulting_count: int
     * }
     */
    public function handle(
        FeedbackType $kind,
        array $identities,
        ?int $reviewId = null,
        bool $confirmed = false,
    ): array {
        $target = $this->unfinishedReviews->target($reviewId);

        if (! $target instanceof CustomReview) {
            $choices = $this->unfinishedReviews->list();

            if ($choices !== []) {
                return [
                    'outcome' => CustomReviewAddOutcome::NeedsReviewChoice,
                    'review' => null,
                    'choices' => $choices,
                    'added' => [],
                    'already' => [],
                    'resulting_count' => 0,
                ];
            }
        }

        if (! $confirmed) {
            $answer = $this->explainSize($kind, $identities, $target);

            if ($answer !== null) {
                return $answer;
            }
        }

        // No review was being built, so the one these items need is made now.
        $review = $target ?? $this->createCustomReview->handle();
        $result = $this->addCustomReviewItems->handle($review, $kind, $identities);

        return [
            'outcome' => CustomReviewAddOutcome::Added,
            'review' => $review,
            'choices' => [],
            'added' => $result['added'],
            'already' => $result['already'],
            'resulting_count' => $result['total'],
        ];
    }

    /**
     * The warning when this add would take the review past its recommended
     * size, or null when it can go ahead.
     *
     * The count comes from the database, never from what the client claims:
     * only the items the review does not hold yet can change its size.
     *
     * @param  list<string>  $identities
     * @return array{
     *     outcome: CustomReviewAddOutcome,
     *     review: CustomReview|null,
     *     choices: list<array{review: CustomReview, item_count: int}>,
     *     added: list<string>,
     *     already: list<string>,
     *     resulting_count: int
     * }|null
     */
    private function explainSize(FeedbackType $kind, array $identities, ?CustomReview $target): ?array
    {
        $already = $target instanceof CustomReview
            ? $this->items->among($target, $kind, $identities)
            : [];

        $newCount = count(array_filter(
            $identities,
            fn (string $identity): bool => ! isset($already[$identity]),
        ));

        $resultingCount = ($target instanceof CustomReview ? $this->items->count($target) : 0) + $newCount;

        if (! CustomReviewRules::needsConfirmation($resultingCount, $newCount)) {
            return null;
        }

        return [
            'outcome' => CustomReviewAddOutcome::NeedsConfirmation,
            'review' => $target,
            'choices' => [],
            'added' => [],
            'already' => array_keys($already),
            'resulting_count' => $resultingCount,
        ];
    }
}
