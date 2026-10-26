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
     * Every box, in the order the editor shows it.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return self::cases();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
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
