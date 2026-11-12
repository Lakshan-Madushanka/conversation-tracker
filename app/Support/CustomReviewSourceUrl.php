<?php

declare(strict_types=1);

namespace App\Support;

use App\Queries\CustomReviewContents;

/**
 * The reading page opened at one saved item, named exactly as Custom Reviews
 * names its sources.
 *
 * Review's screen, a custom review's page and an attempt's history all point
 * at the same place, with the same query the reading page already understands,
 * so the item is highlighted, scrolled to and focused.
 *
 * @phpstan-import-type CustomReviewSource from CustomReviewContents
 */
final class CustomReviewSourceUrl
{
    /**
     * @param  CustomReviewSource  $source
     */
    public static function of(array $source): string
    {
        if ($source['source_kind'] === 'notebook') {
            return self::notebook($source);
        }

        $query = [
            'from' => 'review',
            'question' => (string) $source['question_position'],
            'field' => $source['field'],
        ];

        if ($source['field'] === 'entry') {
            $query['type'] = (string) $source['type'];
            $query['position'] = (string) $source['entry_position'];

            if ($source['entry_id'] !== null) {
                $query['entry'] = $source['entry_id'];
            }
        }

        return route('sessions.show', [
            'session' => $source['session_id'],
            ...$query,
        ]);
    }

    /**
     * The notebook board opened at the saved row, using the same
     * `field`/`type`/`entry`/`position` query the board already understands.
     *
     * @param  CustomReviewSource  $source
     */
    private static function notebook(array $source): string
    {
        $query = [
            'from' => 'review',
            'field' => 'entry',
            'type' => (string) $source['type'],
            'position' => (string) $source['entry_position'],
        ];

        if ($source['entry_id'] !== null) {
            $query['entry'] = $source['entry_id'];
        }

        return route('notebooks.show', [
            'notebook' => $source['notebook_id'],
            ...$query,
        ]);
    }
}
