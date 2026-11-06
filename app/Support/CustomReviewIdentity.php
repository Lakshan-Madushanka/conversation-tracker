<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\FeedbackType;

/**
 * The identity a custom review's item row stores, by the same rules the
 * Collections and Review already use to name an item.
 *
 * A word or phrase is its normalised text; a grammar note is `note-` and the
 * saved row's id; a mistake is the opaque pair key; a question is its own id.
 * The same item seen in two sessions therefore has one identity and one row,
 * here and in every collection.
 */
final class CustomReviewIdentity
{
    /** The longest identity the stored column and the item indexes allow. */
    public const int MAX_LENGTH = 191;

    /** The public key a grammar note is named by in Collections. */
    private const string NOTE_PREFIX = 'note-';

    /**
     * Reduce what the client sent to the identity the app stores. Only words
     * and phrases need normalising; the other kinds are already exact ids.
     */
    public static function normalize(FeedbackType $kind, string $identity): string
    {
        $identity = trim($identity);

        return match ($kind) {
            FeedbackType::Vocabulary, FeedbackType::Phrase => SearchText::normalize($identity),
            default => $identity,
        };
    }

    /** Whether a normalised identity is one this kind can actually hold. */
    public static function isValid(FeedbackType $kind, string $identity): bool
    {
        if ($identity === '' || mb_strlen($identity) > self::MAX_LENGTH) {
            return false;
        }

        return match ($kind) {
            FeedbackType::Vocabulary, FeedbackType::Phrase => true,
            FeedbackType::GrammarNote => preg_match('/^note-[1-9][0-9]*$/', $identity) === 1,
            FeedbackType::Mistake => preg_match('/^mistake-[0-9a-f]{24}$/', $identity) === 1,
            FeedbackType::Question => preg_match('/^[1-9][0-9]*$/', $identity) === 1,
            FeedbackType::NaturalVersion => false,
        };
    }

    /** The note id inside a `note-` identity, or null when it is not one. */
    public static function noteId(string $identity): ?int
    {
        if (preg_match('/^'.preg_quote(self::NOTE_PREFIX, '/').'([1-9][0-9]*)$/', $identity, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /** The prefix grammar notes use, so callers never invent it themselves. */
    public static function noteKey(int $noteId): string
    {
        return self::NOTE_PREFIX.$noteId;
    }
}
