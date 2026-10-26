<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Search\BackfillSearchText;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Gives rows saved before search existed the normalised copy search reads.
 * Safe to run again: rows already up to date are left alone.
 */
#[Description('Fill the searchable text for topics, questions, answers and feedback entries')]
#[Signature('search:backfill')]
class BackfillSearchTextCommand extends Command
{
    public function handle(BackfillSearchText $backfill): int
    {
        $counts = $backfill->handle();

        $this->info(sprintf(
            'Updated %d topic(s), %d question(s) and %d feedback entr%s.',
            $counts['topics'],
            $counts['questions'],
            $counts['entries'],
            $counts['entries'] === 1 ? 'y' : 'ies',
        ));

        return self::SUCCESS;
    }
}
