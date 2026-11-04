<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\FeedbackType;

/**
 * The registry of exercise kinds, and the small pieces of wording built from
 * it: how long a review of them takes, how the mix reads, and which step a
 * saved item belongs to.
 *
 * Flashcards and fix-the-mistake are built today. Say-it-again and
 * fill-in-the-blank already have a share in `ReviewSettings::TARGET_SHARES`,
 * so they will slot in without touching the composer when their candidate
 * source arrives.
 */
final class ReviewExercises
{
    /**
     * The kinds a review can be built from, in the fixed friendly order the
     * mix and the stepper use.
     *
     * @return list<ExerciseKind>
     */
    public static function all(): array
    {
        return [
            new ExerciseKind(
                'flashcard',
                'flashcard',
                'flashcards',
                'Flashcards',
                'book-open',
                ReviewSettings::FLASHCARD_SECONDS,
            ),
            new ExerciseKind(
                'mistake',
                'mistake to fix',
                'mistakes to fix',
                'Fix the mistake',
                'square-pen',
                ReviewSettings::MISTAKE_SECONDS,
            ),
        ];
    }

    /** The registry entry for a key, or null when nothing is built for it. */
    public static function find(string $key): ?ExerciseKind
    {
        foreach (self::all() as $kind) {
            if ($kind->key === $key) {
                return $kind;
            }
        }

        return null;
    }

    /**
     * The exercise one saved feedback kind is shown as. Vocabulary and
     * phrases share the flashcard step; a mistake has its own.
     */
    public static function kindOf(FeedbackType $type): string
    {
        return match ($type) {
            FeedbackType::Mistake => 'mistake',
            FeedbackType::Vocabulary, FeedbackType::Phrase => 'flashcard',
            default => $type->value,
        };
    }

    /**
     * How many minutes a review of these items takes, rounded up, at least
     * one minute. The estimate is always the honest number of items times
     * their per-kind times.
     *
     * @param  array<string, int>  $kindCounts  count per exercise kind key
     * @param  list<ExerciseKind>|null  $kinds  the registry to read, for tests
     */
    public static function minutes(array $kindCounts, ?array $kinds = null): int
    {
        $seconds = 0;

        foreach ($kinds ?? self::all() as $kind) {
            $count = $kindCounts[$kind->key] ?? 0;
            $seconds += $count * $kind->seconds;
        }

        return max(1, (int) ceil($seconds / 60));
    }

    /**
     * The mix description, with correct singular and plural wording — for
     * example "8 flashcards" or "1 flashcard". Kinds not in the registry are
     * described by their key rather than dropped, so a new kind can never
     * vanish silently from the sentence.
     *
     * @param  array<string, int>  $kindCounts  count per exercise kind key
     * @param  list<ExerciseKind>|null  $kinds  the registry to read, for tests
     */
    public static function describe(array $kindCounts, ?array $kinds = null): string
    {
        $parts = [];

        foreach ($kinds ?? self::all() as $kind) {
            $count = $kindCounts[$kind->key] ?? 0;

            if ($count === 0) {
                continue;
            }

            $parts[] = $count.' '.($count === 1 ? $kind->singular : $kind->plural);
        }

        foreach ($kindCounts as $key => $count) {
            $known = false;

            foreach ($kinds ?? self::all() as $kind) {
                if ($kind->key === $key) {
                    $known = true;

                    break;
                }
            }

            if (! $known && $count > 0) {
                $parts[] = $count.' '.$key;
            }
        }

        return implode(', ', $parts);
    }
}
