<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Reviews\SyncReviewSchedule;
use App\Models\Rating;
use App\Models\ReviewSchedule;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Recomputes every stored schedule from the ratings. Safe to run at any
 * time: it only writes the rows the history disagrees with, removes rows
 * for items with no ratings left, and changes nothing when everything
 * already matches.
 */
#[Description('Rebuild every review schedule from the rating history')]
#[Signature('review:rebuild-schedules')]
class RebuildReviewSchedulesCommand extends Command
{
    public function handle(SyncReviewSchedule $sync): int
    {
        $items = Rating::query()
            ->select('kind', 'item_identity')
            ->distinct()
            ->get();

        $seen = [];

        foreach ($items as $item) {
            $kind = $item->kind;
            $key = $kind->value."\u{0}".$item->item_identity;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $sync->handle($kind, (string) $item->item_identity);
        }

        // A stored schedule without any rating behind it can only linger
        // from a hand-made database; the history wins.
        $removed = 0;

        $schedules = ReviewSchedule::query()->get(['id', 'kind', 'item_identity']);

        foreach ($schedules as $schedule) {
            $kind = $schedule->kind;
            $key = $kind->value."\u{0}".$schedule->item_identity;

            if (isset($seen[$key])) {
                continue;
            }

            $schedule->delete();
            $removed += 1;
        }

        $this->info('Rebuilt '.count($seen).' schedules from the ratings.'
            .($removed > 0 ? " Removed {$removed} stale rows." : ''));

        return self::SUCCESS;
    }
}
