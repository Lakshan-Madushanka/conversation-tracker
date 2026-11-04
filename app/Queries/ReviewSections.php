<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Http\Resources\QuestionCardResource;
use App\Http\Resources\ReviewCardResource;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\Rating;
use App\Models\Review;
use App\Models\ReviewItem;
use App\Support\ReviewExercises;
use Illuminate\Support\Collection;

/**
 * The items of a review grouped into one section per exercise kind, in the
 * fixed friendly order the stepper shows.
 *
 * A section remembers every item it holds and the rating each one already has,
 * so the screen can open any step at any time and always return to the next
 * item still to rate there. The cards are read from the current session data
 * in one batch, so an edit shows straight away and an item that was removed or
 * archived is skipped.
 *
 * @phpstan-type SectionItem array{
 *     position: int,
 *     kind: FeedbackType,
 *     identity: string,
 *     card: array<string, mixed>,
 *     rating: string|null
 * }
 * @phpstan-type Section array{
 *     key: string,
 *     label: string,
 *     icon: string,
 *     number: int,
 *     total: int,
 *     rated: int,
 *     completed: bool,
 *     counts: array{hard: int, okay: int, easy: int},
 *     url: string,
 *     items: list<SectionItem>
 * }
 */
final readonly class ReviewSections
{
    public function __construct(private ReviewCandidates $candidates) {}

    /**
     * @return list<Section>
     */
    public function for(Review $review): array
    {
        $items = $review->items;
        $cards = $this->cardsFor($items);
        $questionCards = $this->questionCardsFor($items);
        $ratings = $this->ratingsFor($review);

        /** @var array<string, list<SectionItem>> $grouped */
        $grouped = [];

        foreach ($items as $item) {
            $key = ReviewExercises::kindOf($item->kind);
            $itemKey = $this->candidates->key($item->kind, $item->item_identity);
            $payload = $this->cardPayload($item->kind, $itemKey, $cards, $questionCards);

            // A removed, archived or changed item is skipped; the review moves on.
            if ($payload === null) {
                continue;
            }

            $grouped[$key][] = [
                'position' => $item->position,
                'kind' => $item->kind,
                'identity' => $item->item_identity,
                'card' => $payload,
                'rating' => ($ratings[$itemKey] ?? null)?->rating->value,
            ];
        }

        $sections = [];

        foreach ($this->orderedKeys(array_keys($grouped)) as $key) {
            $sectionItems = $grouped[$key];
            $exercise = ReviewExercises::find($key);
            $counts = ['hard' => 0, 'okay' => 0, 'easy' => 0];
            $rated = 0;

            foreach ($sectionItems as $item) {
                if ($item['rating'] === null) {
                    continue;
                }

                $rated += 1;

                if (isset($counts[$item['rating']])) {
                    $counts[$item['rating']] += 1;
                }
            }

            $total = count($sectionItems);
            $next = $this->firstUnratedIn($sectionItems);

            $sections[] = [
                'key' => $key,
                'label' => $exercise->label,
                'icon' => $exercise->icon,
                'number' => count($sections) + 1,
                'total' => $total,
                'rated' => $rated,
                'completed' => $rated === $total,
                'counts' => $counts,
                'url' => route('review.session', [
                    'review' => $review->id,
                    'step' => $key,
                    'position' => $next['position'] ?? 1,
                ]),
                'items' => $sectionItems,
            ];
        }

        return $sections;
    }

    /**
     * The section and item the address names, or the first item still to rate
     * in the first unfinished section. A finished section opens its own panel
     * with no current item.
     *
     * @param  list<Section>  $sections
     * @return array{section: Section, item: SectionItem|null}|null
     */
    public function open(
        array $sections,
        mixed $requestedStep,
        mixed $requestedPosition,
    ): ?array {
        if ($sections === []) {
            return null;
        }

        $section = $this->sectionAt($sections, $requestedStep)
            ?? $this->firstUnfinished($sections)
            ?? $sections[0];

        if ($section['completed']) {
            return ['section' => $section, 'item' => null];
        }

        return [
            'section' => $section,
            'item' => $this->requestedItem($section, $requestedPosition)
                ?? $this->firstUnratedIn($section['items']),
        ];
    }

    /**
     * The next item still to rate after the one just rated: the rest of its
     * own section first, wrapping to its start, then the next unfinished
     * section. Null when every item has a rating.
     *
     * @param  list<Section>  $sections
     * @return array{step: string, position: int}|null
     */
    public function nextAfterRating(
        array $sections,
        FeedbackType $kind,
        string $itemIdentity,
    ): ?array {
        $sectionIndex = null;
        $itemIndex = null;

        foreach ($sections as $index => $section) {
            foreach ($section['items'] as $itemKey => $item) {
                if ($this->matches($item, $kind, $itemIdentity)) {
                    $sectionIndex = $index;
                    $itemIndex = $itemKey;

                    break 2;
                }
            }
        }

        if ($sectionIndex === null || $itemIndex === null) {
            return null;
        }

        $items = $sections[$sectionIndex]['items'];
        $count = count($items);

        for ($offset = 1; $offset < $count; $offset += 1) {
            $item = $items[($itemIndex + $offset) % $count];

            if ($item['rating'] === null) {
                return ['step' => $sections[$sectionIndex]['key'], 'position' => $item['position']];
            }
        }

        $sectionCount = count($sections);

        for ($offset = 1; $offset < $sectionCount; $offset += 1) {
            $section = $sections[($sectionIndex + $offset) % $sectionCount];
            $next = $this->firstUnratedIn($section['items']);

            if ($next !== null) {
                return ['step' => $section['key'], 'position' => $next['position']];
            }
        }

        return null;
    }

    /**
     * The section and position one item is shown at, for reopening it after
     * undo.
     *
     * @param  list<Section>  $sections
     * @return array{step: string, position: int}|null
     */
    public function locate(
        array $sections,
        FeedbackType $kind,
        string $itemIdentity,
    ): ?array {
        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                if ($this->matches($item, $kind, $itemIdentity)) {
                    return ['step' => $section['key'], 'position' => $item['position']];
                }
            }
        }

        return null;
    }

    /**
     * @param  list<Section>  $sections
     * @return Section|null
     */
    private function sectionAt(array $sections, mixed $requestedStep): ?array
    {
        if (! is_string($requestedStep) && ! is_numeric($requestedStep)) {
            return null;
        }

        foreach ($sections as $section) {
            if (is_numeric($requestedStep) && $section['number'] === (int) $requestedStep) {
                return $section;
            }

            if ($section['key'] === $requestedStep) {
                return $section;
            }
        }

        return null;
    }

    /**
     * @param  list<Section>  $sections
     * @return Section|null
     */
    private function firstUnfinished(array $sections): ?array
    {
        foreach ($sections as $section) {
            if (! $section['completed']) {
                return $section;
            }
        }

        return null;
    }

    /**
     * @param  Section  $section
     * @return SectionItem|null
     */
    private function requestedItem(array $section, mixed $requestedPosition): ?array
    {
        if (! is_numeric($requestedPosition)) {
            return null;
        }

        foreach ($section['items'] as $item) {
            if ($item['position'] === (int) $requestedPosition && $item['rating'] === null) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  list<SectionItem>  $items
     * @return SectionItem|null
     */
    private function firstUnratedIn(array $items): ?array
    {
        foreach ($items as $item) {
            if ($item['rating'] === null) {
                return $item;
            }
        }

        return null;
    }

    /**
     * The registry's fixed friendly order first, then any kind added without
     * a registry entry, so nothing is ever dropped silently.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function orderedKeys(array $keys): array
    {
        $ordered = [];

        foreach (ReviewExercises::all() as $exercise) {
            if (in_array($exercise->key, $keys, true)) {
                $ordered[] = $exercise->key;
            }
        }

        $known = $ordered;
        $extra = array_values(array_filter(
            $keys,
            fn (string $key): bool => ! in_array($key, $known, true),
        ));
        sort($extra);

        return [...$ordered, ...$extra];
    }

    /**
     * @param  Collection<int, ReviewItem>  $items
     * @return array<string, array{entry: PracticeFeedbackEntry, reasons: list<string>}>
     */
    private function cardsFor(Collection $items): array
    {
        return $this->candidates->cardsFor($this->itemPairs($items));
    }

    /**
     * @param  Collection<int, ReviewItem>  $items
     * @return array<string, array{question: PracticeQuestion, natural: PracticeFeedbackEntry}>
     */
    private function questionCardsFor(Collection $items): array
    {
        return $this->candidates->questionCardsFor($this->itemPairs($items));
    }

    /**
     * @param  Collection<int, ReviewItem>  $items
     * @return array<int, array{kind: FeedbackType, identity: string}>
     */
    private function itemPairs(Collection $items): array
    {
        return $items
            ->map(fn (ReviewItem $item): array => [
                'kind' => $item->kind,
                'identity' => $item->item_identity,
            ])
            ->values()
            ->all();
    }

    /**
     * The payload for one item, shaped by the box or question it came from.
     *
     * @param  array<string, array{entry: PracticeFeedbackEntry, reasons: list<string>}>  $cards
     * @param  array<string, array{question: PracticeQuestion, natural: PracticeFeedbackEntry}>  $questionCards
     * @return array<string, mixed>|null
     */
    private function cardPayload(
        FeedbackType $kind,
        string $key,
        array $cards,
        array $questionCards,
    ): ?array {
        if ($kind === FeedbackType::Question) {
            $card = $questionCards[$key] ?? null;

            return $card === null
                ? null
                : (new QuestionCardResource($card['question'], $card['natural']))->resolve();
        }

        $card = $cards[$key] ?? null;

        return $card === null
            ? null
            : (new ReviewCardResource($card['entry'], $card['reasons']))->resolve();
    }

    /**
     * @return array<string, Rating> keyed by kind and identity
     */
    private function ratingsFor(Review $review): array
    {
        $found = [];

        foreach ($review->ratings as $rating) {
            $found[$this->candidates->key($rating->kind, $rating->item_identity)] = $rating;
        }

        return $found;
    }

    /**
     * @param  SectionItem  $item
     */
    private function matches(array $item, FeedbackType $kind, string $itemIdentity): bool
    {
        return $item['kind'] === $kind && $item['identity'] === $itemIdentity;
    }
}
