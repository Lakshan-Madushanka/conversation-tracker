<?php

declare(strict_types=1);

namespace App\Actions\CustomReviews;

use App\Enums\CustomReviewAddOutcome;
use App\Enums\FeedbackType;
use App\Models\CustomReview;
use App\Queries\CustomReviewItems;
use App\Queries\UnfinishedCustomReviews;
use App\Support\CustomReviewKinds;
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
 * The maximum is checked here before anything is written — even before a
 * review is created — and a confirmed add never passes it. A review that has
 * narrowed its kinds refuses the item first, naming what it does allow; that
 * refusal, like the maximum, is never undone by an answer. The recommended
 * size is checked after both, and the learner's answer arrives on the same
 * request: a confirmed add below the maximum goes through whole.
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
     *     resulting_count: int,
     *     message: string|null
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
                    'message' => null,
                ];
            }
        }

        $plan = $this->plan($kind, $identities, $target);

        // A review that has narrowed its kinds refuses the item outright,
        // before its size is ever weighed: the item does not belong here at
        // all. A review with no kinds chosen allows every kind.
        if ($target instanceof CustomReview && ! $target->allowsKind($kind)) {
            return $this->refusedKind($target, $plan['held']);
        }

        // The maximum refuses the whole add, confirmed or not, before a
        // warning is ever asked or a review is ever created.
        if (CustomReviewRules::crossesMaximum($plan['resulting_count'])) {
            return $this->refusal($target, $plan['held']);
        }

        if (! $confirmed && CustomReviewRules::needsConfirmation($plan['resulting_count'], $plan['new_count'])) {
            return [
                'outcome' => CustomReviewAddOutcome::NeedsConfirmation,
                'review' => $target,
                'choices' => [],
                'added' => [],
                'already' => array_keys($plan['already']),
                'resulting_count' => $plan['resulting_count'],
                'message' => null,
            ];
        }

        // No review was being built, so the one these items need is made now.
        $review = $target ?? $this->createCustomReview->handle();
        $result = $this->addCustomReviewItems->handle($review, $kind, $identities);

        // Another add filled the review while this one was on its way, or the
        // learner narrowed its kinds in a second tab; the transaction refused
        // it, so nothing was written.
        if ($result['refused']) {
            return $result['refused_kind']
                ? $this->refusedKind($review, $result['total'])
                : $this->refusal($review, $result['total']);
        }

        return [
            'outcome' => CustomReviewAddOutcome::Added,
            'review' => $review,
            'choices' => [],
            'added' => $result['added'],
            'already' => $result['already'],
            'resulting_count' => $result['total'],
            'message' => null,
        ];
    }

    /**
     * What this add would change, counted from the database, never from what
     * the client claims: only the items the review does not hold yet can
     * change its size.
     *
     * @param  list<string>  $identities
     * @return array{already: array<string, true>, held: int, new_count: int, resulting_count: int}
     */
    private function plan(FeedbackType $kind, array $identities, ?CustomReview $target): array
    {
        $already = $target instanceof CustomReview
            ? $this->items->among($target, $kind, $identities)
            : [];

        $newCount = count(array_filter(
            $identities,
            fn (string $identity): bool => ! isset($already[$identity]),
        ));

        $held = $target instanceof CustomReview ? $this->items->count($target) : 0;

        return [
            'already' => $already,
            'held' => $held,
            'new_count' => $newCount,
            'resulting_count' => $held + $newCount,
        ];
    }

    /**
     * The refusal, with the review as it stands and the room it has left.
     *
     * @return array{
     *     outcome: CustomReviewAddOutcome,
     *     review: CustomReview|null,
     *     choices: list<array{review: CustomReview, item_count: int}>,
     *     added: list<string>,
     *     already: list<string>,
     *     resulting_count: int,
     *     message: string|null
     * }
     */
    private function refusal(?CustomReview $review, int $held): array
    {
        return $this->refused(
            CustomReviewAddOutcome::RefusedMaximum,
            $review,
            $held,
            CustomReviewRules::maximumMessage($held),
        );
    }

    /**
     * The refusal for an item of a kind the review has narrowed away, naming
     * the kinds it does allow.
     *
     * @return array{
     *     outcome: CustomReviewAddOutcome,
     *     review: CustomReview|null,
     *     choices: list<array{review: CustomReview, item_count: int}>,
     *     added: list<string>,
     *     already: list<string>,
     *     resulting_count: int,
     *     message: string|null
     * }
     */
    private function refusedKind(?CustomReview $review, int $held): array
    {
        return $this->refused(
            CustomReviewAddOutcome::RefusedKind,
            $review,
            $held,
            CustomReviewKinds::addRefusalMessage($review?->allowed_kinds),
        );
    }

    /**
     * A refusal changes nothing, and the review reads as it stands.
     *
     * @return array{
     *     outcome: CustomReviewAddOutcome,
     *     review: CustomReview|null,
     *     choices: list<array{review: CustomReview, item_count: int}>,
     *     added: list<string>,
     *     already: list<string>,
     *     resulting_count: int,
     *     message: string|null
     * }
     */
    private function refused(
        CustomReviewAddOutcome $outcome,
        ?CustomReview $review,
        int $held,
        string $message,
    ): array {
        return [
            'outcome' => $outcome,
            'review' => $review,
            'choices' => [],
            'added' => [],
            'already' => [],
            'resulting_count' => $held,
            'message' => $message,
        ];
    }
}
