<?php

declare(strict_types=1);

namespace App\Actions\Search;

use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\Topic;
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
        return [
            'topics' => $this->topics(),
            'questions' => $this->questions(),
            'entries' => $this->entries(),
        ];
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
                    $search = $entry->searchableText();

                    if ($entry->search_text === $search) {
                        continue;
                    }

                    DB::table('practice_feedback_entries')
                        ->where('id', $entry->id)
                        ->update(['search_text' => $search]);

                    $changed += 1;
                }
            });

        return $changed;
    }
}
