<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * One page of entries of a single type for the Collections page.
 *
 * Only finished sessions feed a collection, so a draft the learner is still
 * working on never shows up half-written. An entry that holds nothing at all
 * is left out too, so a stray blank row can never render an empty card or
 * inflate a tab's total.
 */
final readonly class FeedbackCollection
{
    /**
     * The term is matched literally against the word or phrase, what it means
     * and its example: LIKE wildcards the learner typed (`%` and `_`) are
     * escaped, so searching for `100%` finds `100% sure` and nothing else. An
     * escape character of `!` keeps the query portable — sqlite has no default
     * escape character, and MySQL's backslash would have to be escaped
     * differently in each dialect.
     *
     * @return LengthAwarePaginator<int, PracticeFeedbackEntry>
     */
    public function page(FeedbackType $type, ?string $search): LengthAwarePaginator
    {
        return PracticeFeedbackEntry::query()
            ->with([
                'practiceQuestion:id,practice_session_id',
                'practiceQuestion.practiceSession:id,topic_id',
                'practiceQuestion.practiceSession.topic:id,name',
            ])
            ->when($search, function (Builder $query) use ($search): void {
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query
                        ->whereRaw("content_text LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("content_html LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("correction_text LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("correction_html LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("example LIKE ? ESCAPE '!'", [$like]);
                });
            })
            ->whereHas('practiceQuestion.practiceSession', fn (Builder $query): Builder => $query->study())
            ->where(fn (Builder $query): Builder => $query
                ->where('content_text', '!=', '')
                ->orWhere('content_html', '!=', ''))
            ->where('type', $type->value)
            ->latest()
            ->paginate(perPage: 4, pageName: $type->value);
    }
}
