<?php

declare(strict_types=1);

namespace App\Actions\Search;

use App\Enums\FeedbackType;
use App\Models\CollectionItemMark;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\Topic;
use App\Support\EntrySearch;
use App\Support\SearchText;
use Illuminate\Support\Facades\DB;

/**
 * Fills the searchable copy for rows saved before search columns existed.
 *
 * It only writes a row whose stored copy differs from what the current rules
 * produce, so running it again changes nothing. It updates the search column
 * alone, leaving the learner's timestamps untouched.
 */
final readonly class BackfillSearchText
{
    /**
     * @return array{topics: int, questions: int, entries: int}
     */
    public function handle(): array
    {
        $result = [
            'topics' => $this->topics(),
            'questions' => $this->questions(),
            'entries' => $this->entries(),
        ];

        // Marks are repaired in place but are not part of the command's
        // long-standing count, which names only the rows search reads.
        $this->marks();

        return $result;
    }

    private function topics(): int
    {
        $changed = 0;

        Topic::query()->orderBy('id')->chunkById(200, function ($topics) use (&$changed): void {
            foreach ($topics as $topic) {
                $search = SearchText::normalize($topic->name);

                if ($topic->search_text === $search) {
                    continue;
                }

                DB::table('topics')->where('id', $topic->id)->update(['search_text' => $search]);
                $changed += 1;
            }
        });

        return $changed;
    }

    private function questions(): int
    {
        $changed = 0;

        PracticeQuestion::query()
            ->orderBy('id')
            ->chunkById(200, function ($questions) use (&$changed): void {
                foreach ($questions as $question) {
                    $questionSearch = SearchText::normalize($question->question_text);
                    $answerSearch = SearchText::normalize($question->answer_text);
                    $values = [];

                    if ($question->question_search_text !== $questionSearch) {
                        $values['question_search_text'] = $questionSearch;
                    }

                    if ($question->answer_search_text !== $answerSearch) {
                        $values['answer_search_text'] = $answerSearch;
                    }

                    if ($values === []) {
                        continue;
                    }

                    DB::table('practice_questions')
                        ->where('id', $question->id)
                        ->update($values);

                    $changed += 1;
                }
            });

        return $changed;
    }

    private function entries(): int
    {
        $changed = 0;

        PracticeFeedbackEntry::query()
            ->orderBy('id')
            ->chunkById(200, function ($entries) use (&$changed): void {
                foreach ($entries as $entry) {
                    $search = EntrySearch::text($entry);
                    $collectionKey = EntrySearch::collectionKey($entry);
                    $sortText = EntrySearch::sortText($entry);
                    $attention = EntrySearch::attentionReason($entry);
                    $splitBroke = EntrySearch::splitBroke($entry);

                    $values = [];

                    if ($entry->search_text !== $search) {
                        $values['search_text'] = $search;
                    }

                    if ($entry->collection_key !== $collectionKey) {
                        $values['collection_key'] = $collectionKey;
                    }

                    if ($entry->sort_text !== $sortText) {
                        $values['sort_text'] = $sortText;
                    }

                    if ($entry->attention_reason !== $attention) {
                        $values['attention_reason'] = $attention;
                    }

                    if ((bool) $entry->split_broke !== $splitBroke) {
                        $values['split_broke'] = $splitBroke;
                    }

                    if ($values === []) {
                        continue;
                    }

                    DB::table('practice_feedback_entries')
                        ->where('id', $entry->id)
                        ->update($values);

                    $changed += 1;
                }
            });

        return $changed;
    }

    /**
     * Give older marks their public group key and the normalised copy of their
     * note. A mark whose item no longer appears cannot be named from its opaque
     * storage key, so it keeps a null group key until the item returns and the
     * mark is saved again.
     */
    private function marks(): void
    {
        /** @var array<string, string> $groupKeyByStorageKey */
        $groupKeyByStorageKey = [];

        DB::table('practice_feedback_entries')
            ->select(['type', 'collection_key'])
            ->whereNotNull('collection_key')
            ->orderBy('id')
            ->chunk(500, function ($entries) use (&$groupKeyByStorageKey): void {
                foreach ($entries as $entry) {
                    $type = FeedbackType::tryFrom((string) $entry->type);

                    if ($type === null) {
                        continue;
                    }

                    $groupKey = (string) $entry->collection_key;

                    $groupKeyByStorageKey[CollectionItemMark::keyFor($type, $groupKey)] = $groupKey;
                }
            });

        CollectionItemMark::query()
            ->orderBy('id')
            ->chunkById(200, function ($marks) use ($groupKeyByStorageKey): void {
                foreach ($marks as $mark) {
                    $notes = SearchText::normalize((string) $mark->notes_text);
                    $groupKey = $groupKeyByStorageKey[$mark->item_key] ?? null;

                    $values = [];

                    if ($mark->notes_search_text !== $notes) {
                        $values['notes_search_text'] = $notes;
                    }

                    if ($groupKey !== null && $mark->group_key !== $groupKey) {
                        $values['group_key'] = $groupKey;
                    }

                    if ($values === []) {
                        continue;
                    }

                    DB::table('collection_item_marks')
                        ->where('id', $mark->id)
                        ->update($values);
                }
            });
    }
}
