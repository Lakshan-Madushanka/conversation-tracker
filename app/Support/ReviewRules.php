<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The review rules in the numbers an explanation needs.
 *
 * The words on Review Home are written around these, so a threshold changed in
 * `ReviewSettings` or a gap changed in `ReviewSchedule` shows up in the
 * explanation on the same request instead of quietly disagreeing with it.
 */
final readonly class ReviewRules
{
    /**
     * @return array{
     *     dailySize: int,
     *     newItems: int,
     *     hardDays: int,
     *     okayDays: int,
     *     easyGaps: list<int>,
     *     learnedRun: int,
     *     difficultRun: int,
     *     maxSuggestions: int
     * }
     */
    public static function values(): array
    {
        return [
            'dailySize' => ReviewSettings::SIZE,
            'newItems' => ReviewSettings::MAX_NEW_ITEMS,
            'hardDays' => ReviewSchedule::HARD_DUE_IN_DAYS,
            'okayDays' => ReviewSchedule::OKAY_DUE_IN_DAYS,
            'easyGaps' => ReviewSchedule::EASY_GAPS,
            'learnedRun' => ReviewSettings::LEARNED_EASY_RUN,
            'difficultRun' => ReviewSettings::STILL_DIFFICULT_HARD_RUN,
            'maxSuggestions' => ReviewSettings::MAX_STATUS_SUGGESTIONS,
        ];
    }
}
