<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\FeedbackType;

/**
 * The exercise screen's steps, built from whichever list is being practised.
 *
 * Review and an attempt share this piece: one groups live review items, the
 * other reads an attempt's snapshots, and both get the same sections, progress,
 * counts and navigation. Nothing here reads or writes storage, so the same
 * screen can be given a different source without a second copy of it.
 *
 * @phpstan-type SectionItem array{
 *     position: int,
 *     kind: FeedbackType,
 *     identity: string,
 *     card: array<string, mixed>,
 *     rating: string|null,
 *     skipped: bool
 * }
 * @phpstan-type Section array{
 *     key: string,
 *     label: string,
 *     icon: string,
 *     number: int,
 *     total: int,
 *     rated: int,
 *     settled: int,
 *     completed: bool,
 *     counts: array{hard: int, okay: int, easy: int},
 *     url: string,
 *     items: list<SectionItem>
 * }
 */
final class ReviewSectionBoard
{
    /**
     * @param  list<array{
     *     position: int,
     *     kind: FeedbackType,
     *     identity: string,
     *     exercise: string,
     *     card: array<string, mixed>,
     *     rating: string|null,
     *     skipped?: bool
     * }>  $items
     * @param  callable(string, int): string  $url  the address of a step
     * @return list<Section>
     */
    public function build(array $items, callable $url): array
    {
        /** @var array<string, list<SectionItem>> $grouped */
        $grouped = [];

        foreach ($items as $item) {
            $grouped[$item['exercise']][] = [
                'position' => $item['position'],
                'kind' => $item['kind'],
                'identity' => $item['identity'],
                'card' => $item['card'],
                'rating' => $item['rating'],
                'skipped' => $item['skipped'] ?? false,
            ];
        }

        $sections = [];

        foreach ($this->orderedKeys(array_keys($grouped)) as $key) {
            $sectionItems = $grouped[$key];
            $exercise = ReviewExercises::find($key);
            $counts = ['hard' => 0, 'okay' => 0, 'easy' => 0];
            $rated = 0;
            $settled = 0;

            foreach ($sectionItems as $item) {
                if ($item['rating'] !== null) {
                    $rated += 1;

                    if (isset($counts[$item['rating']])) {
                        $counts[$item['rating']] += 1;
                    }
                }

                if ($item['rating'] !== null || $item['skipped']) {
                    $settled += 1;
                }
            }

            $total = count($sectionItems);
            $next = $this->firstUnsettledIn($sectionItems);

            $sections[] = [
                'key' => $key,
                'label' => $exercise->label,
                'icon' => $exercise->icon,
                'number' => count($sections) + 1,
                'total' => $total,
                'rated' => $rated,
                'settled' => $settled,
                'completed' => $settled === $total,
                'counts' => $counts,
                'url' => $url($key, $next['position'] ?? 1),
                'items' => $sectionItems,
            ];
        }

        return $sections;
    }

    /**
     * One section without its items, as the stepper's step list shows it.
     *
     * @param  Section  $section
     * @return array{
     *     key: string,
     *     label: string,
     *     icon: string,
     *     number: int,
     *     total: int,
     *     rated: int,
     *     settled: int,
     *     completed: bool,
     *     counts: array{hard: int, okay: int, easy: int},
     *     url: string
     * }
     */
    public function summary(array $section): array
    {
        unset($section['items']);

        return $section;
    }

    /**
     * What a finished section shows instead of a card: its own tally and a way
     * on to the next section still holding unrated items.
     *
     * @param  Section  $section
     * @param  list<Section>  $sections
     * @return array{
     *     key: string,
     *     label: string,
     *     total: int,
     *     counts: array{hard: int, okay: int, easy: int},
     *     next_url: string|null
     * }
     */
    public function completed(array $section, array $sections): array
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
                ?? $this->firstUnsettledIn($section['items']),
        ];
    }

    /**
     * The next item still to rate after the one just rated: the rest of its
     * own section first, wrapping to its start, then the next unfinished
     * section. Null when every item is settled.
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

            if (! $this->isSettled($item)) {
                return ['step' => $sections[$sectionIndex]['key'], 'position' => $item['position']];
            }
        }

        $sectionCount = count($sections);

        for ($offset = 1; $offset < $sectionCount; $offset += 1) {
            $section = $sections[($sectionIndex + $offset) % $sectionCount];
            $next = $this->firstUnsettledIn($section['items']);

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
     * The next unfinished section after this one, wrapping past the end, or
     * null when every section is complete.
     *
     * @param  Section  $section
     * @param  list<Section>  $sections
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
            if ($item['position'] === (int) $requestedPosition && ! $this->isSettled($item)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  list<SectionItem>  $items
     * @return SectionItem|null
     */
    private function firstUnsettledIn(array $items): ?array
    {
        foreach ($items as $item) {
            if (! $this->isSettled($item)) {
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
     * @param  SectionItem  $item
     */
    private function matches(array $item, FeedbackType $kind, string $itemIdentity): bool
    {
        return $item['kind'] === $kind && $item['identity'] === $itemIdentity;
    }

    /**
     * @param  SectionItem  $item
     */
    private function isSettled(array $item): bool
    {
        return $item['rating'] !== null || $item['skipped'];
    }
}
