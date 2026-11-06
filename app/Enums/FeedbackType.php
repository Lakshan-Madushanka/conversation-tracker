<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * One feedback box a question can hold. The stored value is the box key the
 * editor sends, so the enum stays the single source for what a box is called.
 */
enum FeedbackType: string
{
    case Mistake = 'mistake';

    case Vocabulary = 'vocabulary';

    case Phrase = 'phrase';

    case GrammarNote = 'grammar_note';

    case NaturalVersion = 'natural_version';

    /**
     * A past question, reviewed by answering it out loud. It is not a feedback
     * box; it sits here so a review can name its items by one kind, the same
     * way it names words, phrases and mistakes.
     */
    case Question = 'question';

    /**
     * The boxes shown as a table: a fixed set of columns, one row per line.
     *
     * @return list<self>
     */
    public static function tableTypes(): array
    {
        return [self::Mistake, self::Vocabulary, self::Phrase];
    }

    /**
     * The boxes that hold many entries under one question. Only the natural
     * version stays a single piece of text.
     *
     * @return list<self>
     */
    public static function entryTypes(): array
    {
        return [...self::tableTypes(), self::GrammarNote];
    }

    /**
     * Every box, in the order the editor shows it. A question is not a box, so
     * the editor never writes one.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::Mistake, self::Vocabulary, self::Phrase, self::GrammarNote, self::NaturalVersion];
    }

    /**
     * The kinds a review can hold. A question is reviewed as say-it-again;
     * the other kinds are reviewed through the boxes above, with a grammar
     * note becoming a fill-in-the-blank.
     *
     * @return list<self>
     */
    public static function reviewableTypes(): array
    {
        return [self::Mistake, self::Vocabulary, self::Phrase, self::GrammarNote, self::Question];
    }

    /**
     * The boxes whose entries become collection items. The natural version is
     * not part of Collections, so it cannot carry marks.
     *
     * @return list<self>
     */
    public static function collectable(): array
    {
        return [self::Vocabulary, self::Phrase, self::GrammarNote, self::Mistake];
    }

    /**
     * The kinds a custom review can hold, in the order its page groups them. A
     * question joins the four collections even though it is not a feedback
     * box, because it is added by its own identity from the reading page.
     *
     * @return list<self>
     */
    public static function customReviewTypes(): array
    {
        return [self::Vocabulary, self::Phrase, self::GrammarNote, self::Mistake, self::Question];
    }

    /** Whether this kind of item can be gathered in a custom review. */
    public function isCustomReviewType(): bool
    {
        return in_array($this, self::customReviewTypes(), true);
    }

    /** The heading a custom review's page uses for this kind of item. */
    public function customReviewLabel(): string
    {
        return match ($this) {
            self::Vocabulary => 'Words',
            self::Phrase => 'Phrases',
            self::GrammarNote => 'Grammar notes',
            self::Mistake => 'Mistakes',
            self::Question => 'Questions',
            self::NaturalVersion => 'Natural versions',
        };
    }

    /**
     * The kinds whose items carry the learner's own star and status. A past
     * question and a grammar note never do, so nothing can favour them by a
     * star or suggest a status for them.
     *
     * @return list<self>
     */
    public static function markable(): array
    {
        return [self::Vocabulary, self::Phrase, self::Mistake];
    }

    /** Whether the learner can star, label and annotate this kind of item. */
    public function isCollectable(): bool
    {
        return in_array($this, self::collectable(), true);
    }

    /** Whether items of this kind carry the learner's star and status. */
    public function isMarkable(): bool
    {
        return in_array($this, self::markable(), true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::ordered());
    }

    /**
     * @return list<string>
     */
    public static function reviewableValues(): array
    {
        return array_map(fn (self $type): string => $type->value, self::reviewableTypes());
    }

    /**
     * @return list<string>
     */
    public static function tableValues(): array
    {
        return array_map(fn (self $type): string => $type->value, self::tableTypes());
    }

    /**
     * @return list<string>
     */
    public static function customReviewValues(): array
    {
        return array_map(fn (self $type): string => $type->value, self::customReviewTypes());
    }

    /**
     * @return list<string>
     */
    public static function richValues(): array
    {
        return array_values(array_map(
            fn (self $type): string => $type->value,
            array_filter(self::cases(), fn (self $type): bool => $type->isRich()),
        ));
    }

    /** Whether the box holds a table of rows, one column set per box. */
    public function isTable(): bool
    {
        return in_array($this, self::tableTypes(), true);
    }

    /** Whether the box is edited as formatted text. */
    public function isRich(): bool
    {
        return $this === self::GrammarNote || $this === self::NaturalVersion;
    }

    /** Where the box sits in the editor, used to keep search matches in order. */
    public function sortOrder(): int
    {
        $index = array_search($this, self::cases(), true);

        return $index === false ? 0 : $index;
    }
}
