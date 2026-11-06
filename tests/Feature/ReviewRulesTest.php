<?php

declare(strict_types=1);

use App\Support\ReviewSchedule;
use App\Support\ReviewSettings;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The Review Home explanation reads its numbers from the rules themselves, so
 * the words on the screen cannot fall behind the code.
 */
it('gives Review Home the numbers its explanation reads from', function (): void {
    $this->get(route('review.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('review/Index')
            ->where('reviewRules.dailySize', ReviewSettings::SIZE)
            ->where('reviewRules.newItems', ReviewSettings::MAX_NEW_ITEMS)
            ->where('reviewRules.hardDays', ReviewSchedule::HARD_DUE_IN_DAYS)
            ->where('reviewRules.okayDays', ReviewSchedule::OKAY_DUE_IN_DAYS)
            ->where('reviewRules.easyGaps', ReviewSchedule::EASY_GAPS)
            ->where('reviewRules.learnedRun', ReviewSettings::LEARNED_EASY_RUN)
            ->where('reviewRules.difficultRun', ReviewSettings::STILL_DIFFICULT_HARD_RUN)
            ->where('reviewRules.maxSuggestions', ReviewSettings::MAX_STATUS_SUGGESTIONS));
});
