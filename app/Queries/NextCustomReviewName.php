<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\CustomReview;

/**
 * The name a review the learner has not named yet would carry.
 *
 * The dialog shows it prefilled, and a review started without a name takes it,
 * so the two can never offer and store different names. The first number none
 * of the learner's reviews already uses is chosen, so a gap left by a deleted
 * review is filled and a name is never reused while another review carries it.
 *
 * Read-only: no review is created or changed here.
 */
final readonly class NextCustomReviewName
{
    /** The name a review the learner has not named yet carries. */
    public const string NAME_PREFIX = 'Custom Review ';

    public function handle(): string
    {
        /** @var array<int, true> $used */
        $used = [];

        foreach (CustomReview::query()->pluck('name') as $name) {
            if (preg_match('/^'.preg_quote(self::NAME_PREFIX, '/').'([1-9][0-9]*)$/', (string) $name, $matches) === 1) {
                $used[(int) $matches[1]] = true;
            }
        }

        for ($number = 1; ; $number++) {
            if (! isset($used[$number])) {
                return self::NAME_PREFIX.$number;
            }
        }
    }
}
