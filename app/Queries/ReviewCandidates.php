<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Http\Resources\FillInTheBlankCardResource;
use App\Http\Resources\QuestionCardResource;
use App\Http\Resources\ReviewCardResource;
use App\Models\Notebook;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Support\BlankExercise;
use App\Support\CustomReviewIdentity;
use App\Support\EntrySearch;
use App\Support\NotebookIdentity;
use App\Support\NotebookItemContent;
use App\Support\ReviewFilters;
use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The items a review can be built from: the words and phrases that carry a
 * meaning (or use), the mistakes that carry a correction, the questions that
 * carry a more natural version, and the grammar notes that can make a blank,
 * from finished sessions that are not archived or from saved notebook entries.
 *
 * An item is the same identity Collections groups by, so two sessions that
 * mention the same word — or the same mistake pair — make one item, while a
 * notebook word keeps its own scoped identity. The text is deliberately not
 * copied: `cardsFor()` reads the current entries straight from their source,
 * so an edit is shown and a removed item is skipped.
 *
 * A mistake's exercise text comes from its most recent appearance (latest
 * session, ties by latest entry). Its reason comes from the most recent
 * appearance that has one, with every distinct reason kept in order for the
 * "More notes" panel.
 *
 * Choosing today's items lives in `App\Queries\TodaysReview`; this class
 * provides the shared candidate query and the bulk readers it and the
 * exercise screen need.
 */
final class ReviewCandidates
{
    /**
     * The saved kinds that can carry a fill-in-the-blank.
     *
     * @return list<FeedbackType>
     */
    public static function blankTypes(): array
    {
        return [
            FeedbackType::Vocabulary,
            FeedbackType::Phrase,
            FeedbackType::Mistake,
            FeedbackType::GrammarNote,
        ];
    }

    /** How many distinct items could be reviewed right now. */
    public function count(?ReviewFilters $filters = null): int
    {
        $filters ??= ReviewFilters::daily();

        $entries = DB::query()
            ->fromSub(
                $this->base($filters)
                    ->select(['entries.type', 'entries.collection_key'])
                    ->distinct(),
                'reviewable',
            )
            ->count();

        $questions = $filters->allows(FeedbackType::Question)
            ? DB::query()
                ->fromSub(
                    $this->questionsBase($filters)->select('questions.id as collection_key'),
                    'reviewable_questions',
                )
                ->count()
            : 0;

        return $entries + $questions;
    }

    /**
     * The card each chosen item is shown as, keyed by kind and identity.
     *
     * The exercise decides the shape: a blank is built from the entry's current
     * text, a question shows its natural version, and everything else keeps the
     * flashcard or mistake shape. An item that can no longer be shown is left
     * out, so the caller skips it exactly as it would a removed item.
     *
     * @param  list<array{kind: FeedbackType, identity: string, exercise: string}>  $items
     * @return array<string, array<string, mixed>>
     */
    public function payloadsFor(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $pairs = array_map(fn (array $item): array => [
            'kind' => $item['kind'],
            'identity' => $item['identity'],
        ], $items);

        $cards = $this->cardsFor($pairs);
        $questionCards = $this->questionCardsFor($pairs);

        $payloads = [];

        foreach ($items as $item) {
            $payload = $this->payload($item, $cards, $questionCards);

            if ($payload !== null) {
                $payloads[$this->key($item['kind'], $item['identity'])] = $payload;
            }
        }

        return $payloads;
    }

    /**
     * The current entry behind each identity, in one query for the whole
     * review, plus every distinct reason its mistake appearances carry.
     *
     * @param  array<int, array{kind: FeedbackType, identity: string}>  $items
     * @param  bool  $withSource  read the topic behind each entry too, for the
     *                            payloads that name it; an eligibility check
     *                            only needs the entry itself
     * @return array<string, array{entry: PracticeFeedbackEntry, reasons: list<string>}>
     *                                                                                   keyed by kind and identity
     */
    public function cardsFor(array $items, bool $withSource = true): array
    {
        if ($items === []) {
            return [];
        }

        /** @var array<string, FeedbackType> $notebookWords */
        $notebookWords = [];
        /** @var list<int> $noteIds */
        $noteIds = [];

        foreach ($items as $item) {
            if (
                in_array($item['kind'], [FeedbackType::Vocabulary, FeedbackType::Phrase], true)
                && NotebookIdentity::isWordKey($item['identity'])
            ) {
                $notebookWords[$item['identity']] = $item['kind'];

                continue;
            }

            if ($item['kind'] === FeedbackType::GrammarNote) {
                $noteId = CustomReviewIdentity::noteId($item['identity']);

                if ($noteId !== null) {
                    $noteIds[] = $noteId;
                }
            }
        }

        /** @var array<string, array{entry: PracticeFeedbackEntry, reasons: list<string>}> $cards */
        $cards = $this->notebookWordCards($notebookWords, $withSource);
        $notebookNoteIds = $this->notebookNoteCards($noteIds, $cards, $withSource);

        $sessionItems = array_values(array_filter(
            $items,
            fn (array $item): bool => (! in_array($item['kind'], [FeedbackType::Vocabulary, FeedbackType::Phrase], true) || ! NotebookIdentity::isWordKey($item['identity'])) && ($item['kind'] !== FeedbackType::GrammarNote || ! in_array(CustomReviewIdentity::noteId($item['identity']), $notebookNoteIds, true)),
        ));

        if ($sessionItems === []) {
            return $cards;
        }

        // The identities are already scoped by kind, so one `where in` read
        // finds every wanted row without growing the query tree per item.
        $identities = array_values(array_unique(array_map(
            fn (array $item): string => $item['identity'],
            $sessionItems,
        )));

        $entries = $this->entriesBase(self::blankTypes())
            ->when(
                $withSource,
                fn (EloquentBuilder $query): EloquentBuilder => $query
                    ->with('practiceQuestion.practiceSession.topic'),
            )
            ->whereIn('practice_feedback_entries.collection_key', $identities)
            ->get();

        foreach ($entries as $entry) {
            $key = $this->key($entry->type, (string) $entry->collection_key);

            $cards[$key] ??= ['entry' => $entry, 'reasons' => []];

            $reason = trim((string) $entry->reason);

            if ($reason !== '' && ! in_array($reason, $cards[$key]['reasons'], true)) {
                $cards[$key]['reasons'][] = $reason;
            }
        }

        return $cards;
    }

    /**
     * The current notebook row behind each scoped word or phrase, shaped as
     * the item the shared resolution rule reads: the first saved spelling,
     * the first meaning, the first example, and the row that supplied each.
     *
     * @param  array<string, FeedbackType>  $identities  keyed by identity
     * @return array<string, array{entry: PracticeFeedbackEntry, reasons: list<string>}>
     */
    private function notebookWordCards(array $identities, bool $withSource): array
    {
        if ($identities === []) {
            return [];
        }

        $rows = PracticeFeedbackEntry::query()
            ->whereNotNull('notebook_id')
            ->whereIn('type', [FeedbackType::Vocabulary->value, FeedbackType::Phrase->value])
            ->whereIn('collection_key', array_keys($identities))
            ->when($withSource, fn (EloquentBuilder $query): EloquentBuilder => $query->with('notebook.topic'))
            ->orderBy('notebook_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (PracticeFeedbackEntry $entry): string => (string) $entry->collection_key);

        $cards = [];

        foreach ($rows as $identity => $entryRows) {
            $kind = $identities[$identity] ?? null;
            $first = $entryRows->first();

            if ($kind === null || ! $first instanceof PracticeFeedbackEntry) {
                continue;
            }

            $content = NotebookItemContent::of($kind, $entryRows->values()->all());
            $exampleRow = $entryRows->first(
                fn (PracticeFeedbackEntry $entry): bool => trim(EntrySearch::exampleText($entry)) !== '',
            );
            $exampleHtml = $exampleRow instanceof PracticeFeedbackEntry
                ? $exampleRow->example_html
                : [];

            $entry = new PracticeFeedbackEntry;
            $entry->forceFill([
                'notebook_id' => $first->notebook_id,
                'type' => $kind,
                'position' => $first->position,
                'client_id' => $first->client_id,
                'content_text' => $content['title'],
                'correction_text' => $content['meaning'],
                'example' => $content['example'],
                'example_html' => $exampleHtml,
                'collection_key' => $identity,
            ]);
            if ($withSource) {
                $entry->setRelation('notebook', $first->notebook);
            }

            $cards[$this->key($kind, $identity)] = ['entry' => $entry, 'reasons' => []];
        }

        return $cards;
    }

    /**
     * The notebook-backed grammar notes among these identities, which keep
     * their own saved row as the card's content.
     *
     * @param  list<int>  $noteIds
     * @param  array<string, array{entry: PracticeFeedbackEntry, reasons: list<string>}>  $cards
     * @return list<int> the note ids that belong to a notebook
     */
    private function notebookNoteCards(array $noteIds, array &$cards, bool $withSource): array
    {
        if ($noteIds === []) {
            return [];
        }

        $rows = PracticeFeedbackEntry::query()
            ->whereIn('id', $noteIds)
            ->where('type', FeedbackType::GrammarNote)
            ->whereNotNull('notebook_id')
            ->when($withSource, fn (EloquentBuilder $query): EloquentBuilder => $query->with('notebook.topic'))
            ->get();

        $found = [];

        foreach ($rows as $row) {
            $found[] = (int) $row->id;
            $cards[$this->key(FeedbackType::GrammarNote, CustomReviewIdentity::noteKey((int) $row->id))] = [
                'entry' => $row,
                'reasons' => [],
            ];
        }

        return $found;
    }

    /**
     * One item's card, shaped by the exercise chosen for it.
     *
     * @param  array{kind: FeedbackType, identity: string, exercise: string}  $item
     * @param  array<string, array{entry: PracticeFeedbackEntry, reasons: list<string>}>  $cards
     * @param  array<string, array{question: PracticeQuestion, natural: PracticeFeedbackEntry}>  $questionCards
     * @return array<string, mixed>|null
     */
    private function payload(array $item, array $cards, array $questionCards): ?array
    {
        $key = $this->key($item['kind'], $item['identity']);

        if ($item['exercise'] === 'fill_in_the_blank') {
            $card = $cards[$key] ?? null;

            if ($card === null) {
                return null;
            }

            $entry = $card['entry'];
            $notebook = $entry->notebook;
            $topic = $notebook instanceof Notebook
                ? (string) $notebook->topic->name
                : (string) ($entry->practiceQuestion?->practiceSession?->topic->name ?? '');
            $blank = BlankExercise::build($entry, $topic, $card['reasons'][0] ?? null);

            // An item edited into something that cannot hold a blank is
            // skipped, exactly like the item it came from being removed.
            return $blank === null
                ? null
                : (new FillInTheBlankCardResource($entry, $blank))->resolve();
        }

        if ($item['kind'] === FeedbackType::Question) {
            $card = $questionCards[$key] ?? null;

            return $card === null
                ? null
                : (new QuestionCardResource($card['question'], $card['natural']))->resolve();
        }

        $card = $cards[$key] ?? null;

        return $card === null
            ? null
            : (new ReviewCardResource($card['entry'], $card['reasons']))->resolve();
    }

    /**
     * The current question and its natural version behind each say-it-again
     * identity, in one query for the whole review. A question whose session
     * has gone, was archived, or whose natural version was removed is skipped
     * rather than shown stale.
     *
     * @param  array<int, array{kind: FeedbackType, identity: string}>  $items
     * @return array<string, array{question: PracticeQuestion, natural: PracticeFeedbackEntry}>
     *                                                                                          keyed by kind and identity
     */
    public function questionCardsFor(array $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            if ($item['kind'] === FeedbackType::Question && is_numeric($item['identity'])) {
                $ids[] = (int) $item['identity'];
            }
        }

        if ($ids === []) {
            return [];
        }

        $naturals = PracticeFeedbackEntry::query()
            ->select('practice_feedback_entries.*')
            ->join('practice_questions as questions', 'questions.id', '=', 'practice_feedback_entries.practice_question_id')
            ->join('practice_sessions as sessions', 'sessions.id', '=', 'questions.practice_session_id')
            ->with('practiceQuestion.practiceSession')
            ->where('practice_feedback_entries.type', FeedbackType::NaturalVersion->value)
            ->whereIn('practice_feedback_entries.practice_question_id', $ids)
            ->whereRaw("TRIM(practice_feedback_entries.content_text) <> ''")
            ->whereRaw("TRIM(questions.question_text) <> ''")
            ->where('sessions.status', PracticeSession::STATUS_FINISHED)
            ->whereNull('sessions.archived_at')
            ->orderByDesc('practice_feedback_entries.id')
            ->get();

        /** @var array<string, array{question: PracticeQuestion, natural: PracticeFeedbackEntry}> $cards */
        $cards = [];

        foreach ($naturals as $natural) {
            $question = $natural->practiceQuestion;

            if (! $question instanceof PracticeQuestion) {
                continue;
            }

            $cards[$this->key(FeedbackType::Question, (string) $question->id)] ??= [
                'question' => $question,
                'natural' => $natural,
            ];
        }

        return $cards;
    }

    /**
     * The candidate keys that can build a blank right now, keyed by kind and
     * identity. Each item is tested through the very card the exercise will
     * show — the current notebook resolution or the newest session
     * appearance — in one bulk read for the whole suggestion.
     *
     * @param  array<int, array{kind: FeedbackType, identity: string}>  $items
     * @return array<string, true>
     */
    public function blankableKeys(array $items): array
    {
        $wanted = [];
        $pairs = [];

        foreach ($items as $item) {
            if (! in_array($item['kind'], self::blankTypes(), true)) {
                continue;
            }

            $key = $this->key($item['kind'], $item['identity']);

            if (isset($wanted[$key])) {
                continue;
            }

            $wanted[$key] = true;
            $pairs[] = ['kind' => $item['kind'], 'identity' => $item['identity']];
        }

        if ($pairs === []) {
            return [];
        }

        $blankable = [];

        foreach ($this->cardsFor($pairs, false) as $key => $card) {
            if (isset($wanted[$key]) && BlankExercise::build($card['entry']) !== null) {
                $blankable[$key] = true;
            }
        }

        return $blankable;
    }

    /**
     * How many visible grammar notes cannot make a blank yet, for the friendly
     * tip on Review Home. Two notes with identical text count separately, and
     * a note with neither a bold part nor a quoted example counts. Notes from
     * finished sessions and from notebooks are counted by the same rule.
     */
    public function unusableGrammarNotesCount(): int
    {
        $notes = PracticeFeedbackEntry::query()
            ->select([
                'practice_feedback_entries.id',
                'practice_feedback_entries.type',
                'practice_feedback_entries.collection_key',
                'practice_feedback_entries.content_html',
                'practice_feedback_entries.content_text',
            ])
            ->where('practice_feedback_entries.type', FeedbackType::GrammarNote->value)
            ->whereRaw("TRIM(practice_feedback_entries.content_text) <> ''")
            ->where(function (EloquentBuilder $scope): void {
                $scope->whereNotNull('practice_feedback_entries.notebook_id')
                    ->orWhereHas(
                        'practiceQuestion.practiceSession',
                        fn (EloquentBuilder $query): EloquentBuilder => $query->study(),
                    );
            })
            ->get();

        $unusable = 0;

        foreach ($notes as $note) {
            if (BlankExercise::build($note) === null) {
                $unusable += 1;
            }
        }

        return $unusable;
    }

    /** The key a review item and its current entry share. */
    public function key(FeedbackType $kind, string $identity): string
    {
        return $kind->value."\u{0}".$identity;
    }

    /**
     * The saved appearances of these kinds, newest first. Callers add their
     * own filters; keeping the order here keeps the "newest appearance wins"
     * rule in one place.
     *
     * @param  list<FeedbackType>  $types
     * @return EloquentBuilder<PracticeFeedbackEntry>
     */
    private function entriesBase(array $types): EloquentBuilder
    {
        return PracticeFeedbackEntry::query()
            ->select('practice_feedback_entries.*')
            ->join('practice_questions as questions', 'questions.id', '=', 'practice_feedback_entries.practice_question_id')
            ->join('practice_sessions as sessions', 'sessions.id', '=', 'questions.practice_session_id')
            ->whereIn('practice_feedback_entries.type', array_map(
                fn (FeedbackType $type): string => $type->value,
                $types,
            ))
            ->where('sessions.status', PracticeSession::STATUS_FINISHED)
            ->whereNull('sessions.archived_at')
            // The newest appearance wins, so the learner sees their latest
            // wording. Ties go to the row saved last.
            ->orderByDesc('sessions.created_at')
            ->orderByDesc('practice_feedback_entries.created_at')
            ->orderByDesc('practice_feedback_entries.id');
    }

    /**
     * The shared starting point: finished, non-archived sessions and saved
     * notebook entries, of the kinds that can actually be practiced. A word or
     * phrase needs its meaning; a mistake needs both the sentence the learner
     * said and its correction.
     *
     * The learner's choices narrow the same query: their topics and, when they
     * picked collections, the kinds those collections hold. A notebook item
     * matches its notebook's shared topic.
     */
    public function base(ReviewFilters $filters): QueryBuilder
    {
        $types = $filters->types();

        return DB::table('practice_feedback_entries as entries')
            ->leftJoin('practice_questions as questions', 'questions.id', '=', 'entries.practice_question_id')
            ->leftJoin('practice_sessions as sessions', 'sessions.id', '=', 'questions.practice_session_id')
            ->leftJoin('notebooks as notebooks', 'notebooks.id', '=', 'entries.notebook_id')
            ->where($this->liveScope())
            ->when($types !== null, fn (QueryBuilder $query): QueryBuilder => $query->whereIn(
                'entries.type',
                array_map(fn (FeedbackType $type): string => $type->value, $types),
            ))
            ->when($filters->topicFilter() !== null, fn (QueryBuilder $query): QueryBuilder => $query
                ->whereIn(
                    DB::raw('COALESCE(sessions.topic_id, notebooks.topic_id)'),
                    $filters->topicFilter(),
                ))
            ->where(function (QueryBuilder $reviewable): void {
                $reviewable
                    ->where(function (QueryBuilder $words): void {
                        $words
                            ->whereIn('entries.type', [
                                FeedbackType::Vocabulary->value,
                                FeedbackType::Phrase->value,
                            ])
                            ->whereNotNull('entries.collection_key')
                            ->where('entries.collection_key', '!=', '')
                            ->whereRaw("TRIM(entries.correction_text) <> ''");
                    })
                    ->orWhere(function (QueryBuilder $mistakes): void {
                        $mistakes
                            ->where('entries.type', FeedbackType::Mistake->value)
                            ->whereNotNull('entries.collection_key')
                            ->where('entries.collection_key', '!=', '')
                            ->whereRaw("TRIM(entries.content_text) <> ''")
                            ->whereRaw("TRIM(entries.correction_text) <> ''");
                    });
            });
    }

    /**
     * The starting point for say-it-again: a tutor's question with words and a
     * non-empty natural version, from a finished session that is not archived.
     * A question's identity is its own row id, so the same words in two
     * sessions stay two items and an edit never moves a rating.
     */
    public function questionsBase(ReviewFilters $filters): QueryBuilder
    {
        return DB::table('practice_questions as questions')
            ->join('practice_sessions as sessions', 'sessions.id', '=', 'questions.practice_session_id')
            ->whereNull('sessions.archived_at')
            ->where('sessions.status', PracticeSession::STATUS_FINISHED)
            ->when($filters->topicFilter() !== null, fn (QueryBuilder $query): QueryBuilder => $query
                ->whereIn('sessions.topic_id', $filters->topicFilter()))
            ->whereRaw("TRIM(questions.question_text) <> ''")
            ->whereExists(function (QueryBuilder $natural): void {
                $natural
                    ->select(DB::raw('1'))
                    ->from('practice_feedback_entries as natural')
                    ->whereColumn('natural.practice_question_id', 'questions.id')
                    ->where('natural.type', FeedbackType::NaturalVersion->value)
                    ->whereRaw("TRIM(natural.content_text) <> ''");
            });
    }

    /**
     * The starting point for fill-in-the-blank's grammar notes: a note with
     * words, from a finished non-archived session or from a notebook. A note's
     * identity is its own row id, so two notes with identical text stay two
     * items and an edit never moves a rating.
     */
    public function grammarNotesBase(ReviewFilters $filters): QueryBuilder
    {
        return DB::table('practice_feedback_entries as entries')
            ->leftJoin('practice_questions as questions', 'questions.id', '=', 'entries.practice_question_id')
            ->leftJoin('practice_sessions as sessions', 'sessions.id', '=', 'questions.practice_session_id')
            ->leftJoin('notebooks as notebooks', 'notebooks.id', '=', 'entries.notebook_id')
            ->where($this->liveScope())
            ->when($filters->topicFilter() !== null, fn (QueryBuilder $query): QueryBuilder => $query
                ->whereIn(
                    DB::raw('COALESCE(sessions.topic_id, notebooks.topic_id)'),
                    $filters->topicFilter(),
                ))
            ->where('entries.type', FeedbackType::GrammarNote->value)
            ->whereNotNull('entries.collection_key')
            ->where('entries.collection_key', '!=', '')
            ->whereRaw("TRIM(entries.content_text) <> ''");
    }

    /**
     * A row counts when it lives in a notebook, or in a session the learner
     * finished and has not archived.
     */
    private function liveScope(): Closure
    {
        return function (QueryBuilder $scope): void {
            $scope->whereNotNull('entries.notebook_id')
                ->orWhere(function (QueryBuilder $live): void {
                    $live->where('sessions.status', PracticeSession::STATUS_FINISHED)
                        ->whereNull('sessions.archived_at');
                });
        };
    }
}
