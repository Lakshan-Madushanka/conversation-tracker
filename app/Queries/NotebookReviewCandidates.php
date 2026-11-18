<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Models\Notebook;
use App\Models\PracticeFeedbackEntry;
use App\Support\BlankExercise;
use App\Support\CustomReviewEligibility;
use App\Support\NotebookItemContent;
use App\Support\NotePreview;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The items one notebook can offer a custom review, by the scoped identities
 * the review will store.
 *
 * A word or phrase is one candidate however many saved rows carry it; a
 * grammar note is always its own candidate. The database groups, counts and
 * pages the identities, and only the rows of the page's identities are read
 * to resolve its words, so a page costs the same as the notebook grows.
 *
 * The one whole-notebook read left is a grammar note's own rows, because
 * whether a note can make a blank is decided from its markup by
 * `BlankExercise` and cannot be asked in SQL. Collections reads notes the same
 * way, and reading a note's words costs far less than reading every row of
 * the notebook.
 *
 * The content shown is resolved by `NotebookItemContent` in the board's saved
 * order, so the same notebook always offers the same words, and a meaning or
 * example is never borrowed from a matching session or another notebook.
 *
 * The learner's kinds choice narrows the list; it is never inferred from the
 * boxes that happen to have content.
 *
 * @phpstan-type NotebookReviewCandidate array{
 *     kind: string,
 *     identity: string,
 *     title: string,
 *     meaning: string,
 *     example: string,
 *     eligible: bool,
 *     reason: string|null
 * }
 */
final readonly class NotebookReviewCandidates
{
    /** How many candidates one selection screen asks for at a time. */
    public const int PAGE_SIZE = 50;

    /** The listing order of the kinds, as an expression the database sorts by. */
    private const string KIND_ORDER = 'CASE e.type WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END';

    /**
     * The kinds a notebook can hold, in the order reviews list them.
     *
     * @return list<FeedbackType>
     */
    public static function kinds(): array
    {
        return [
            FeedbackType::Vocabulary,
            FeedbackType::Phrase,
            FeedbackType::GrammarNote,
        ];
    }

    /**
     * How many candidates the notebook offers, without reading its rows.
     *
     * @param  list<FeedbackType>|null  $kinds
     */
    public function count(Notebook $notebook, ?array $kinds = null): int
    {
        $wanted = $this->wanted($kinds);

        return $wanted === [] ? 0 : $this->countGroups($notebook, $wanted);
    }

    /**
     * One page of candidates, with the counts that describe the whole
     * notebook. The identities are grouped and paged by the database, and the
     * response carries only the rows that resolve the page. The selection
     * screen asks for the next page as the learner scrolls.
     *
     * @param  list<FeedbackType>|null  $kinds
     * @return array{candidates: list<NotebookReviewCandidate>, count: int, eligible_count: int, next_offset: int|null, has_more: bool}
     */
    public function page(Notebook $notebook, ?array $kinds, int $offset): array
    {
        $wanted = $this->wanted($kinds);

        if ($wanted === []) {
            return [
                'candidates' => [],
                'count' => 0,
                'eligible_count' => 0,
                'next_offset' => null,
                'has_more' => false,
            ];
        }

        $offset = max(0, $offset);
        $count = $this->countGroups($notebook, $wanted);

        $groups = $this->ordered($this->groups($notebook, $wanted))
            ->offset($offset)
            ->limit(self::PAGE_SIZE)
            ->get();

        $candidates = $this->candidatesFromGroups($notebook, $groups);
        $next = $offset + count($candidates);

        return [
            'candidates' => $candidates,
            'count' => $count,
            'eligible_count' => $this->eligibleCount($notebook, $wanted),
            'next_offset' => $next < $count ? $next : null,
            'has_more' => $next < $count,
        ];
    }

    /**
     * The candidates keyed by the identity a selection names, so a request can
     * be checked against what the notebook holds right now. Naming identities
     * reads only their rows; leaving them out reads the whole list, which the
     * caller only asks for when it fits one page.
     *
     * @param  list<FeedbackType>|null  $kinds
     * @param  list<string>|null  $identities  null means every candidate
     * @return array<string, NotebookReviewCandidate>
     */
    public function map(Notebook $notebook, ?array $kinds = null, ?array $identities = null): array
    {
        $wanted = $this->wanted($kinds);

        if ($wanted === []) {
            return [];
        }

        $groups = $this->groups($notebook, $wanted);

        if ($identities !== null) {
            $identities = array_values(array_unique($identities));

            if ($identities === []) {
                return [];
            }

            $groups->whereIn('e.collection_key', $identities);
        }

        $map = [];

        foreach ($this->candidatesFromGroups($notebook, $this->ordered($groups)->get()) as $candidate) {
            $map[$candidate['identity']] = $candidate;
        }

        return $map;
    }

    /**
     * @param  list<FeedbackType>|null  $kinds
     * @return list<FeedbackType>
     */
    private function wanted(?array $kinds): array
    {
        return $kinds === null
            ? self::kinds()
            : array_values(array_filter(
                self::kinds(),
                fn (FeedbackType $kind): bool => in_array($kind, $kinds, true),
            ));
    }

    /**
     * One row per candidate identity, with the first row's saved place for
     * ordering. A blank word or phrase has no key and a blank grammar note
     * cannot show a preview, so both are left out here just as they were when
     * the rows were read one by one.
     *
     * @param  list<FeedbackType>  $wanted
     */
    private function groups(Notebook $notebook, array $wanted): QueryBuilder
    {
        return DB::table('practice_feedback_entries as e')
            ->where('e.notebook_id', $notebook->id)
            ->whereIn('e.type', array_map(
                fn (FeedbackType $kind): string => $kind->value,
                $wanted,
            ))
            ->whereNotNull('e.collection_key')
            ->where('e.collection_key', '<>', '')
            ->where(fn (QueryBuilder $query): QueryBuilder => $query
                ->where('e.type', '<>', FeedbackType::GrammarNote->value)
                ->orWhereRaw("TRIM(e.content_text) <> ''"))
            ->groupBy('e.type', 'e.collection_key')
            ->select(['e.type', 'e.collection_key'])
            ->selectRaw('MIN(e.position) AS first_position')
            ->selectRaw('MIN(e.id) AS first_id');
    }

    private function ordered(QueryBuilder $groups): QueryBuilder
    {
        return $groups
            ->orderByRaw(self::KIND_ORDER, [
                FeedbackType::Vocabulary->value,
                FeedbackType::Phrase->value,
            ])
            ->orderBy('first_position')
            ->orderBy('first_id');
    }

    /**
     * @param  list<FeedbackType>  $wanted
     */
    private function countGroups(Notebook $notebook, array $wanted): int
    {
        return (int) DB::query()
            ->fromSub($this->groups($notebook, $wanted), 'g')
            ->count();
    }

    /**
     * @param  list<FeedbackType>  $wanted
     */
    private function eligibleCount(Notebook $notebook, array $wanted): int
    {
        return $this->eligibleTableCount($notebook, $wanted)
            + $this->eligibleNoteCount($notebook, $wanted);
    }

    /**
     * Words and phrases are eligible when any row of the item carries a
     * meaning, which the stored columns can answer.
     *
     * @param  list<FeedbackType>  $wanted
     */
    private function eligibleTableCount(Notebook $notebook, array $wanted): int
    {
        $tableKinds = array_values(array_filter(
            $wanted,
            fn (FeedbackType $kind): bool => $kind !== FeedbackType::GrammarNote,
        ));

        if ($tableKinds === []) {
            return 0;
        }

        return (int) DB::query()
            ->fromSub(
                $this->groups($notebook, $tableKinds)
                    ->havingRaw("MAX(CASE WHEN TRIM(e.correction_text) <> '' THEN 1 ELSE 0 END) = 1"),
                'g',
            )
            ->count();
    }

    /**
     * A grammar note's blank rule reads its markup, so its own rows answer it
     * — the same way Collections asks the same question.
     *
     * @param  list<FeedbackType>  $wanted
     */
    private function eligibleNoteCount(Notebook $notebook, array $wanted): int
    {
        if (! in_array(FeedbackType::GrammarNote, $wanted, true)) {
            return 0;
        }

        $eligible = 0;

        $notes = DB::table('practice_feedback_entries')
            ->where('notebook_id', $notebook->id)
            ->where('type', FeedbackType::GrammarNote->value)
            ->whereNotNull('collection_key')
            ->where('collection_key', '<>', '')
            ->whereRaw("TRIM(content_text) <> ''")
            ->select(['id', 'content_text', 'content_html'])
            ->get();

        foreach ($notes as $note) {
            $entry = new PracticeFeedbackEntry;
            $entry->forceFill([
                'type' => FeedbackType::GrammarNote,
                'content_html' => (string) $note->content_html,
                'content_text' => (string) $note->content_text,
            ]);

            if (BlankExercise::build($entry) !== null) {
                $eligible += 1;
            }
        }

        return $eligible;
    }

    /**
     * The candidates of the given identity rows, in their order, with the
     * content read from just those identities' saved rows.
     *
     * @param  Collection<int, stdClass>  $groups
     * @return list<NotebookReviewCandidate>
     */
    private function candidatesFromGroups(Notebook $notebook, Collection $groups): array
    {
        if ($groups->isEmpty()) {
            return [];
        }

        $keys = $groups->pluck('collection_key')->unique()->values()->all();
        $types = $groups->pluck('type')->unique()->values()->all();

        $rows = PracticeFeedbackEntry::query()
            ->where('notebook_id', $notebook->id)
            ->whereIn('type', $types)
            ->whereIn('collection_key', $keys)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (PracticeFeedbackEntry $entry): string => $entry->type->value.'|'.$entry->collection_key);

        $candidates = [];

        foreach ($groups as $group) {
            $kind = FeedbackType::from((string) $group->type);
            $identity = (string) $group->collection_key;
            $entryRows = $rows->get($kind->value.'|'.$identity);

            if (! $entryRows instanceof Collection || $entryRows->isEmpty()) {
                continue;
            }

            if ($kind === FeedbackType::GrammarNote) {
                $candidate = $this->noteCandidate($entryRows->first());
            } else {
                /** @var list<PracticeFeedbackEntry> $orderedRows */
                $orderedRows = $entryRows->values()->all();
                $candidate = $this->itemCandidate($kind, $identity, $orderedRows);
            }

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }

    /**
     * @param  list<PracticeFeedbackEntry>  $entryRows  in saved order
     * @return NotebookReviewCandidate
     */
    private function itemCandidate(FeedbackType $kind, string $identity, array $entryRows): array
    {
        $content = NotebookItemContent::of($kind, $entryRows);
        $reason = CustomReviewEligibility::reason(
            $kind,
            true,
            $content['usable'],
            $content['split_broke'],
        );

        return [
            'kind' => $kind->value,
            'identity' => $identity,
            'title' => $content['title'],
            'meaning' => $content['meaning'],
            'example' => $content['example'],
            'eligible' => $reason === null,
            'reason' => $reason,
        ];
    }

    /**
     * @return NotebookReviewCandidate|null
     */
    private function noteCandidate(PracticeFeedbackEntry $row): ?array
    {
        $identity = (string) ($row->collection_key ?? '');
        $text = trim((string) $row->content_text);

        if ($identity === '' || $text === '') {
            return null;
        }

        $content = NotebookItemContent::of(FeedbackType::GrammarNote, [$row]);
        $reason = CustomReviewEligibility::reason(
            FeedbackType::GrammarNote,
            true,
            $content['usable'],
        );

        return [
            'kind' => FeedbackType::GrammarNote->value,
            'identity' => $identity,
            'title' => NotePreview::of($text),
            'meaning' => '',
            'example' => '',
            'eligible' => $reason === null,
            'reason' => $reason,
        ];
    }
}
