<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\FeedbackType;

/**
 * How a review's allowed kinds are named, in one place.
 *
 * A review allows every kind by default; a narrowed one names its subset in
 * the collections' own words, so the card, the page and the refusals can never
 * word the same choice differently. The order is always the order the review's
 * page groups its items, whatever order the stored values arrive in.
 */
final readonly class CustomReviewKinds
{
    /**
     * The kinds a review holds, resolved from the stored value in the review's
     * own order. An empty choice means every kind.
     *
     * @param  list<string>|null  $allowedKinds
     * @return list<FeedbackType>
     */
    public static function resolve(?array $allowedKinds): array
    {
        if ($allowedKinds === null || $allowedKinds === []) {
            return FeedbackType::customReviewTypes();
        }

        $chosen = array_map(
            FeedbackType::from(...),
            $allowedKinds,
        );

        return array_values(array_filter(
            FeedbackType::customReviewTypes(),
            fn (FeedbackType $kind): bool => in_array($kind, $chosen, true),
        ));
    }

    /**
     * The chosen kinds by name, for a sentence: "all kinds", "Words", or
     * "Words + Phrases". Lower-case "all" only because it always follows a
     * verb in the refusals that use it.
     *
     * @param  list<string>|null  $allowedKinds
     */
    public static function names(?array $allowedKinds): string
    {
        $kinds = self::resolve($allowedKinds);

        if (count($kinds) === count(FeedbackType::customReviewTypes())) {
            return 'all kinds';
        }

        return implode(' + ', array_map(
            fn (FeedbackType $kind): string => $kind->customReviewLabel(),
            $kinds,
        ));
    }

    /**
     * The label a card and the review's page show, such as "All kinds",
     * "Words only", or "Words + Phrases".
     *
     * @param  list<string>|null  $allowedKinds
     */
    public static function label(?array $allowedKinds): string
    {
        $kinds = self::resolve($allowedKinds);

        if (count($kinds) === count(FeedbackType::customReviewTypes())) {
            return 'All kinds';
        }

        if (count($kinds) === 1) {
            return $kinds[0]->customReviewLabel().' only';
        }

        return self::names($allowedKinds);
    }

    /**
     * The value to store for a whole choice: every kind chosen means none is
     * listed, so the column stays empty and the review allows all.
     *
     * @param  list<FeedbackType>  $kinds
     * @return list<string>|null
     */
    public static function storedChoice(array $kinds): ?array
    {
        $values = [];

        foreach (FeedbackType::customReviewTypes() as $kind) {
            if (in_array($kind, $kinds, true)) {
                $values[] = $kind->value;
            }
        }

        return count($values) === count(FeedbackType::customReviewTypes())
            ? null
            : $values;
    }

    /**
     * What an add of a disallowed kind says, naming the kinds the review does
     * allow.
     *
     * @param  list<string>|null  $allowedKinds
     */
    public static function addRefusalMessage(?array $allowedKinds): string
    {
        return 'This review allows '.self::names($allowedKinds).' only.';
    }

    /**
     * What turning off a kind still held says, naming the items to remove
     * first.
     */
    public static function narrowingMessage(FeedbackType $kind, int $count): string
    {
        $word = match ($kind) {
            FeedbackType::Vocabulary => 'word',
            FeedbackType::Phrase => 'phrase',
            FeedbackType::GrammarNote => 'grammar note',
            FeedbackType::Mistake => 'mistake',
            FeedbackType::Question => 'question',
            FeedbackType::NaturalVersion => 'natural version',
        };

        return 'Remove the '.$count.' '.$word.' '.($count === 1 ? 'item' : 'items').' first.';
    }
}
