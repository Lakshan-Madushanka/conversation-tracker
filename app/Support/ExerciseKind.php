<?php

declare(strict_types=1);

namespace App\Support;

/**
 * One kind of exercise a review item can be shown as. New kinds (fix the
 * mistake, say it again, fill in the blank) plug in by adding a case here
 * and their own candidate source — the estimate and the mix description
 * then grow automatically.
 */
final readonly class ExerciseKind
{
    public function __construct(
        /** The stable key, e.g. `flashcard`. */
        public string $key,
        /** The wording for one item, e.g. `flashcard`. */
        public string $singular,
        /** The wording for many, e.g. `flashcards`. */
        public string $plural,
        /** The name a review step uses, e.g. `Fix the mistake`. */
        public string $label,
        /** The icon a review step shows, by its shared name. */
        public string $icon,
        /** A rough duration for one item, for the estimate. */
        public int $seconds,
    ) {}
}
