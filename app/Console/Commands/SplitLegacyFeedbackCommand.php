<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\PracticeSessions\SplitLegacyFeedbackEntries;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Splits feedback saved before entries existed into one entry per line, so old
 * sessions look the same as new ones. Safe to run again: already converted
 * sessions are left alone.
 */
#[Description('Split pasted lists saved as one piece of text into separate entries')]
#[Signature('practice:split-legacy-feedback')]
class SplitLegacyFeedbackCommand extends Command
{
    public function handle(SplitLegacyFeedbackEntries $split): int
    {
        $changed = $split->handleAll();

        $this->info("Converted {$changed} question".($changed === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
