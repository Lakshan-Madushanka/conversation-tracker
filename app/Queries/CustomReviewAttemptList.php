<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Models\CustomReview;
use App\Support\CustomReviewSourceUrl;
use App\Support\ReviewAllocation;
use App\Support\ReviewExercises;
use App\Support\ReviewSettings;
use App\Support\RichText;
use Carbon\CarbonImmutable;

/**
 * The fixed list an attempt is started with: every eligible item in the review,
 * once, shaped as the card the attempt will show for ever.
 *
 * The membership is decided by the review's own eligibility rules, read through
 * `CustomReviewContents`. The mixing follows the daily composer instead: the
 * kinds share the list by the same target shares, renormalised over the kinds
 * that have something to show, and a kind that cannot fill its share hands the
 * free slots to the others. Because the list is the whole eligible pool, that
 * rule only decides the order the items and sections are met in — never whether
 * an item is included.
 *
 * Each item is turned into its card once, here, and the card is stored with the
 * attempt. Later edits to the item, its session or the review cannot reach it.
 *
 * @phpstan-import-type CustomReviewItemData from CustomReviewContents
 *
 * @phpstan-type AttemptListItem array{
 *     position: int,
 *     kind: FeedbackType,
 *     identity: string,
 *     exercise: string,
 *     card: array<string, mixed>,
 *     snapshot_text: string,
 *     topic: string,
 *     session_date: CarbonImmutable|null,
 *     source_session_id: int,
 *     source_url: string
 * }
 * @phpstan-type PendingAttemptItem array{
 *     kind: FeedbackType,
 *     identity: string,
 *     exercise: string,
 *     card: array<string, mixed>,
 *     snapshot_text: string,
 *     topic: string,
 *     session_date: CarbonImmutable|null,
 *     source_session_id: int,
 *     source_url: string
 * }
 */
final readonly class CustomReviewAttemptList
{
    public function __construct(
        private CustomReviewContents $contents,
        private ReviewCandidates $candidates,
    ) {}

    /**
     * @return list<AttemptListItem>
     */
    public function for(CustomReview $review): array
    {
        /** @var list<array{kind: FeedbackType, identity: string, exercise: string, item: CustomReviewItemData}> $wanted */
        $wanted = [];

        foreach ($this->contents->groups($review) as $group) {
            $kind = FeedbackType::from($group['key']);
            $exercise = ReviewExercises::kindOf($kind);

            foreach ($group['items'] as $item) {
                if (! $item['eligible']) {
                    continue;
                }

                $wanted[] = [
                    'kind' => $kind,
                    'identity' => $item['identity'],
                    'exercise' => $exercise,
                    'item' => $item,
                ];
            }
        }

        if ($wanted === []) {
            return [];
        }

        $payloads = $this->candidates->payloadsFor(array_map(
            fn (array $entry): array => [
                'kind' => $entry['kind'],
                'identity' => $entry['identity'],
                'exercise' => $entry['exercise'],
            ],
            $wanted,
        ));

        /** @var array<string, list<PendingAttemptItem>> $pools */
        $pools = [];

        foreach ($wanted as $entry) {
            $key = $this->candidates->key($entry['kind'], $entry['identity']);
            $card = $payloads[$key] ?? null;

            // The item changed between the eligibility read and this one; it
            // cannot be shown any more, so it is left out exactly as a removed
            // item would be.
            if ($card === null) {
                continue;
            }

            $source = $entry['item']['source'];

            $pools[$entry['exercise']][] = [
                'kind' => $entry['kind'],
                'identity' => $entry['identity'],
                'exercise' => $entry['exercise'],
                'card' => $card,
                'snapshot_text' => $this->snapshotText($entry['exercise'], $card),
                'topic' => $source['topic'],
                'session_date' => $source['session_date'],
                'source_session_id' => $source['session_id'],
                'source_url' => CustomReviewSourceUrl::of($source),
            ];
        }

        return $this->ordered($pools);
    }

    /**
     * The pools in the fixed friendly order, each kind taking up to its share
     * and anything left over following, so the list holds every item once.
     *
     * @param  array<string, list<PendingAttemptItem>>  $pools
     * @return list<AttemptListItem>
     */
    private function ordered(array $pools): array
    {
        $active = [];

        foreach (ReviewExercises::all() as $exercise) {
            if (isset($pools[$exercise->key])) {
                $active[] = $exercise->key;
            }
        }

        foreach (array_keys($pools) as $key) {
            if (! in_array($key, $active, true)) {
                $active[] = $key;
            }
        }

        if ($active === []) {
            return [];
        }

        $total = 0;

        foreach ($active as $key) {
            $total += count($pools[$key]);
        }

        $quotas = ReviewAllocation::quotas($active, $total, ReviewSettings::TARGET_SHARES);
        $items = [];

        foreach ($active as $key) {
            $take = min($quotas[$key] ?? 0, count($pools[$key]));

            for ($index = 0; $index < $take; $index += 1) {
                $items[] = $pools[$key][$index];
            }
        }

        // A kind holding more than its share keeps the rest at the end, so the
        // quota only shapes the mix and never drops an item.
        foreach ($active as $key) {
            $taken = min($quotas[$key] ?? 0, count($pools[$key]));
            $counter = count($pools[$key]);

            for ($index = $taken; $index < $counter; $index += 1) {
                $items[] = $pools[$key][$index];
            }
        }

        $ordered = [];

        foreach ($items as $position => $item) {
            $ordered[] = ['position' => $position + 1, ...$item];
        }

        return $ordered;
    }

    /**
     * The item's words as the attempt will show them in history, read from the
     * card that was just snapshotted.
     *
     * @param  array<string, mixed>  $card
     */
    private function snapshotText(string $exercise, array $card): string
    {
        return match ($exercise) {
            'mistake' => (string) ($card['said'] ?? ''),
            'say_it_again' => (string) ($card['question_text'] ?? ''),
            'fill_in_the_blank' => trim(RichText::toPlainText((string) ($card['prompt_html'] ?? ''))),
            default => (string) ($card['front'] ?? ''),
        };
    }
}
