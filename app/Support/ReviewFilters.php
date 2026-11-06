<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\FeedbackType;
use App\Enums\ReviewCollection;

/**
 * What the learner asked a custom review to cover: topics, collections,
 * starred items only, and whether Learned items take part.
 *
 * The daily suggestion carries no choices, so it uses `daily()` and keeps the
 * Step 2 rules exactly as they were. A custom sitting names its own scope, and
 * because it is the learner asking for these items, everything that matches
 * takes part — the items still resting between reviews join after the due work
 * rather than being left out. The one selection piece serves both, so the
 * schedule, the mix, the estimate and the allocation can never disagree.
 */
final readonly class ReviewFilters
{
    /**
     * @param  list<int>  $topicIds  the topics chosen, empty for every topic
     * @param  list<ReviewCollection>  $collections  the collections chosen, empty for all of them
     */
    public function __construct(
        public bool $custom,
        public array $topicIds,
        public array $collections,
        public bool $starredOnly,
        public bool $includeLearned,
    ) {}

    /** The app's own choice of today's items: nothing restricted, nothing added. */
    public static function daily(): self
    {
        return new self(
            custom: false,
            topicIds: [],
            collections: [],
            starredOnly: false,
            includeLearned: false,
        );
    }

    /**
     * @param  list<int>  $topicIds
     * @param  list<ReviewCollection>  $collections
     */
    public static function custom(
        array $topicIds = [],
        array $collections = [],
        bool $starredOnly = false,
        bool $includeLearned = false,
    ): self {
        // Keyed by their own values, so the same topic or collection named
        // twice in an address is only asked for once.
        $chosen = [];

        foreach ($collections as $collection) {
            $chosen[$collection->value] = $collection;
        }

        return new self(
            custom: true,
            topicIds: array_values(array_unique(array_map(intval(...), $topicIds))),
            collections: array_values($chosen),
            starredOnly: $starredOnly,
            includeLearned: $includeLearned,
        );
    }

    /**
     * The saved kinds the choices allow, or null when every kind may take part.
     *
     * Starred-only narrows the kinds further: a past question and a grammar
     * note carry no star, so they cannot join a starred-only sitting.
     *
     * @return list<FeedbackType>|null
     */
    public function types(): ?array
    {
        $types = ReviewCollection::types($this->collections);

        if (! $this->starredOnly) {
            return $types;
        }

        $markable = FeedbackType::markable();

        if ($types === null) {
            return $markable;
        }

        return array_values(array_filter(
            $types,
            fn (FeedbackType $type): bool => in_array($type, $markable, true),
        ));
    }

    /** Whether items of this kind may take part at all. */
    public function allows(FeedbackType $kind): bool
    {
        $types = $this->types();

        return $types === null || in_array($kind, $types, true);
    }

    /**
     * The topics the choices name, empty for every topic.
     *
     * @return list<int>|null
     */
    public function topicFilter(): ?array
    {
        return $this->topicIds === [] ? null : $this->topicIds;
    }

    /**
     * The choices as the page address carries them, so a refresh keeps the
     * sitting the learner was about to start. They are never stored. Lists are
     * joined with commas, the way the Collections page carries its own.
     *
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        $query = [];

        if ($this->topicIds !== []) {
            $query['topics'] = implode(',', $this->topicIds);
        }

        if ($this->collections !== []) {
            $query['collections'] = implode(',', array_map(
                fn (ReviewCollection $collection): string => $collection->value,
                $this->collections,
            ));
        }

        if ($this->starredOnly) {
            $query['starred'] = '1';
        }

        if ($this->includeLearned) {
            $query['learned'] = '1';
        }

        return $query;
    }

    /** How many choices are set, for the phone button that opens the panel. */
    public function setCount(): int
    {
        return count($this->topicIds)
            + count($this->collections)
            + ($this->starredOnly ? 1 : 0)
            + ($this->includeLearned ? 1 : 0);
    }
}
