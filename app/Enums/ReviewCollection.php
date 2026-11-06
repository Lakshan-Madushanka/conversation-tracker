<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The five collections the learner can restrict a custom review to. Each one
 * names the saved kind its items come from, so the choice and the candidate
 * query never drift apart.
 */
enum ReviewCollection: string
{
    case Words = 'words';

    case Phrases = 'phrases';

    case GrammarNotes = 'grammar_notes';

    case Mistakes = 'mistakes';

    case Questions = 'questions';

    /** The words the learner sees on the page. */
    public function label(): string
    {
        return match ($this) {
            self::Words => 'Words',
            self::Phrases => 'Phrases',
            self::GrammarNotes => 'Grammar notes',
            self::Mistakes => 'Mistakes',
            self::Questions => 'Past questions',
        };
    }

    /** The saved kind the items of this collection come from. */
    public function type(): FeedbackType
    {
        return match ($this) {
            self::Words => FeedbackType::Vocabulary,
            self::Phrases => FeedbackType::Phrase,
            self::GrammarNotes => FeedbackType::GrammarNote,
            self::Mistakes => FeedbackType::Mistake,
            self::Questions => FeedbackType::Question,
        };
    }

    /**
     * Every collection, in the order the page lists it.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::Words, self::Phrases, self::GrammarNotes, self::Mistakes, self::Questions];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $collection): string => $collection->value, self::ordered());
    }

    /**
     * The saved kinds these collections cover, or null for every kind.
     *
     * @param  list<self>  $collections
     * @return list<FeedbackType>|null
     */
    public static function types(array $collections): ?array
    {
        if ($collections === []) {
            return null;
        }

        // Keyed by the kind's own value, so two collections that would name
        // the same kind cannot list it twice.
        $types = [];

        foreach ($collections as $collection) {
            $types[$collection->type()->value] = $collection->type();
        }

        return array_values($types);
    }
}
