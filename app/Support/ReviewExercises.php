<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The registry of exercise kinds, and the two small pieces of wording built
 * from it: how long a review of them takes, and how the mix reads.
 *
 * Only flashcards exist so far, so a review today is described as
 * "8 flashcards". When the next steps add kinds, they join `all()` and both
 * the estimate and the description change by themselves.
 */
final class ReviewExercises
{
    /**
     * The kinds a review can be built from, in the order the mix lists them.
     *
     * @return list<ExerciseKind>
     */
    public static function all(): array
    {
        return [
            new ExerciseKind('flashcard', 'flashcard', 'flashcards', ReviewSettings::FLASHCARD_SECONDS),
        ];
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
