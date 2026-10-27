<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * One of the four personal collections a learner can browse.
 *
 * The tab key travels in the page address, so an unknown value from an old
 * bookmark falls back to vocabulary instead of breaking the page.
 */
enum CollectionTab: string
{
    case Vocabulary = 'vocabulary';

    case Phrases = 'phrases';

    case Grammar = 'grammar';

    case Mistakes = 'mistakes';

    /**
     * Read a tab from the address, keeping a stale value harmless: the page
     * opens on vocabulary rather than failing.
     */
    public static function fromQuery(?string $value): self
    {
        if ($value === null) {
            return self::Vocabulary;
        }

        return self::tryFrom($value) ?? self::Vocabulary;
    }

    /**
     * Every tab, in the order the page shows them.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return self::cases();
    }

    /** The feedback box this collection gathers. */
    public function feedbackType(): FeedbackType
    {
        return match ($this) {
            self::Vocabulary => FeedbackType::Vocabulary,
            self::Phrases => FeedbackType::Phrase,
            self::Grammar => FeedbackType::GrammarNote,
            self::Mistakes => FeedbackType::Mistake,
        };
    }

    /** The label the tabs and headings use. */
    public function label(): string
    {
        return match ($this) {
            self::Vocabulary => 'Vocabulary',
            self::Phrases => 'Phrases',
            self::Grammar => 'Grammar',
            self::Mistakes => 'My mistakes',
        };
    }

    /**
     * Whether this collection has been built yet. The other tabs stay visible
     * and explain that they are on their way, rather than looking broken.
     */
    public function isBuilt(): bool
    {
        return $this === self::Vocabulary;
    }
}
