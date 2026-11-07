<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Models\CustomReview;
use App\Models\CustomReviewItem;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeSession;
use App\Support\BlankExercise;
use App\Support\CustomReviewEligibility;
use App\Support\CustomReviewIdentity;
use App\Support\MistakeIdentity;
use App\Support\NotePreview;
use App\Support\RichText;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * A custom review as the learner sees it: its items grouped by collection,
 * each with the words to show, where it came from, and whether it is available.
 *
 * A review stores only identities. Everything shown here is read live from the
 * study sessions, so an edited item shows its current words, an archived item
 * says it is not available right now, and a deleted item is gone (its
 * reference is pruned before this query runs).
 *
 * Reads are done one query per kind whatever the review holds, and every place
 * an item appears is read once, so the query count does not grow with the
 * number of items.
 *
 * @phpstan-type CustomReviewSource array{
 *     session_id: int,
 *     session_date: CarbonImmutable|null,
 *     topic: string,
 *     question_position: int,
 *     entry_id: string|null,
 *     entry_position: int,
 *     field: 'question'|'entry',
 *     type: string|null
 * }
 * @phpstan-type CustomReviewItemData array{
 *     id: string,
 *     kind: string,
 *     identity: string,
 *     title: string,
 *     correction: string,
 *     correction_html: string,
 *     available: bool,
 *     eligible: bool,
 *     reason: string|null,
 *     source: CustomReviewSource
 * }
 */
final class CustomReviewContents
{
    /**
     * Every group with at least one item, in the order the review's page shows:
     * Words, Phrases, Grammar notes, Mistakes, Questions.
     *
     * @return list<array{key: string, label: string, count: int, items: list<CustomReviewItemData>}>
     */
    public function groups(CustomReview $review): array
    {
        /** @var Collection<int, CustomReviewItem> $rows */
        $rows = CustomReviewItem::query()
            ->where('custom_review_id', $review->id)
            ->orderByDesc('added_at')
            ->orderByDesc('id')
            ->get();

        /** @var Collection<string, Collection<int, CustomReviewItem>> $byKind */
        $byKind = $rows->groupBy(
            fn (CustomReviewItem $item): string => $item->kind->value,
        );

        $groups = [];

        foreach (FeedbackType::customReviewTypes() as $kind) {
            /** @var Collection<int, CustomReviewItem> $kindRows */
            $kindRows = $byKind->get($kind->value, collect());

            /** @var list<string> $identities */
            $identities = $kindRows
                ->pluck('item_identity')
                ->unique()
                ->values()
                ->all();

            if ($identities === []) {
                continue;
            }

            $found = $this->items($kind, $identities);
            $items = [];

            // The review's own order is kept: the most recently added first.
            foreach ($identities as $identity) {
                if (isset($found[$identity])) {
                    $items[] = $found[$identity];
                }
            }

            if ($items === []) {
                continue;
            }

            $groups[] = [
                'key' => $kind->value,
                'label' => $kind->customReviewLabel(),
                'count' => count($items),
                'items' => $items,
            ];
        }

        return $groups;
    }

    /**
     * @param  list<string>  $identities
     * @return array<string, CustomReviewItemData>
     */
    private function items(FeedbackType $kind, array $identities): array
    {
        return match ($kind) {
            FeedbackType::Vocabulary, FeedbackType::Phrase => $this->feedbackItems($kind, $identities),
            FeedbackType::GrammarNote => $this->noteItems($identities),
            FeedbackType::Mistake => $this->mistakeItems($identities),
            FeedbackType::Question => $this->questionItems($identities),
            FeedbackType::NaturalVersion => [],
        };
    }

    /**
     * The words and phrases, newest appearance first. The first row of an
     * identity gives its current spelling and source; any row outside the
     * archive makes it available.
     *
     * @param  list<string>  $identities
     * @return array<string, CustomReviewItemData>
     */
    private function feedbackItems(FeedbackType $kind, array $identities): array
    {
        $wanted = array_fill_keys($identities, true);

        // The identities are applied outside the positioned rows so the window
        // still numbers every entry in the box, as the reading page does, even
        // when only some of those entries are in the review.
        $rows = DB::query()
            ->fromSub(
                $this->entryQuery($kind)
                    ->where('s.status', PracticeSession::STATUS_FINISHED)
                    ->select([
                        'e.id',
                        'e.created_at',
                        'e.collection_key',
                        'e.content_text',
                        'e.correction_text',
                        'e.split_broke',
                        'e.client_id',
                        'q.id as question_id',
                        'q.position as question_position',
                        's.id as session_id',
                        's.created_at as session_date',
                        's.archived_at',
                        't.name as topic',
                    ])
                    ->selectRaw($this->entryPositionExpression()),
                'rows',
            )
            ->whereIn('rows.collection_key', $identities)
            ->orderByDesc('rows.session_date')
            ->orderByDesc('rows.created_at')
            ->orderByDesc('rows.id')
            ->get();

        /** @var array<string, CustomReviewItemData> $items */
        $items = [];

        /** @var array<string, array{meaning: bool, split_broke: bool}> $signals */
        $signals = [];

        foreach ($rows as $row) {
            $identity = (string) $row->collection_key;

            if (! isset($wanted[$identity])) {
                continue;
            }

            $available = $row->archived_at === null;
            $meaning = trim((string) $row->correction_text) !== '';

            $signals[$identity] ??= ['meaning' => false, 'split_broke' => false];
            $signals[$identity]['meaning'] = $signals[$identity]['meaning'] || $meaning;
            $signals[$identity]['split_broke'] = $signals[$identity]['split_broke']
                || (bool) $row->split_broke;

            if (! isset($items[$identity])) {
                $items[$identity] = $this->entryItem($kind, $identity, $row, $available);
            } elseif ($available) {
                $items[$identity]['available'] = true;
            }
        }

        return $this->withEligibility($kind, $items, $signals);
    }

    /**
     * @param  list<string>  $identities
     * @return array<string, CustomReviewItemData>
     */
    private function noteItems(array $identities): array
    {
        $noteIds = array_values(array_filter(array_map(
            CustomReviewIdentity::noteId(...),
            $identities,
        )));

        if ($noteIds === []) {
            return [];
        }

        $rows = DB::query()
            ->fromSub(
                $this->entryQuery(FeedbackType::GrammarNote)
                    ->select([
                        'e.id',
                        'e.content_text',
                        'e.content_html',
                        'e.client_id',
                        'q.id as question_id',
                        'q.position as question_position',
                        's.id as session_id',
                        's.created_at as session_date',
                        's.status',
                        's.archived_at',
                        't.name as topic',
                    ])
                    ->selectRaw($this->entryPositionExpression()),
                'rows',
            )
            ->whereIn('rows.id', $noteIds)
            ->get();

        /** @var array<string, CustomReviewItemData> $items */
        $items = [];

        foreach ($rows as $row) {
            $identity = CustomReviewIdentity::noteKey((int) $row->id);
            $available = $row->status === PracticeSession::STATUS_FINISHED
                && $row->archived_at === null;
            $usable = $this->noteCanMakeABlank($row);
            $reason = CustomReviewEligibility::reason(
                FeedbackType::GrammarNote,
                $available,
                $usable,
            );

            $items[$identity] = [
                'id' => FeedbackType::GrammarNote->value.'|'.$identity,
                'kind' => FeedbackType::GrammarNote->value,
                'identity' => $identity,
                'title' => NotePreview::of((string) $row->content_text),
                'correction' => '',
                'correction_html' => '',
                'available' => $available,
                'eligible' => $reason === null,
                'reason' => $reason,
                'source' => $this->source($row, 'entry', FeedbackType::GrammarNote),
            ];
        }

        return $items;
    }

    /**
     * The mistakes, by the same normalised pair the collection groups with.
     * The newest appearance gives the words; any appearance outside the
     * archive makes the mistake available.
     *
     * @param  list<string>  $identities
     * @return array<string, CustomReviewItemData>
     */
    private function mistakeItems(array $identities): array
    {
        $wanted = array_fill_keys($identities, true);

        $rows = $this->entryQuery(FeedbackType::Mistake)
            ->where('s.status', PracticeSession::STATUS_FINISHED)
            ->select([
                'e.id',
                'e.content_text',
                'e.correction_text',
                'e.correction_html',
                'e.split_broke',
                'e.client_id',
                'q.id as question_id',
                'q.position as question_position',
                's.id as session_id',
                's.created_at as session_date',
                's.archived_at',
                't.name as topic',
            ])
            ->selectRaw($this->entryPositionExpression())
            ->orderByDesc('s.created_at')
            ->orderByDesc('e.created_at')
            ->orderByDesc('e.id')
            ->get();

        /** @var array<string, CustomReviewItemData> $items */
        $items = [];

        /** @var array<string, array{meaning: bool, split_broke: bool}> $signals */
        $signals = [];

        foreach ($rows as $row) {
            $said = trim((string) $row->content_text);
            $correction = trim((string) $row->correction_text);

            if (MistakeIdentity::isBlank($said, $correction)) {
                continue;
            }

            $identity = MistakeIdentity::pairKey($said, $correction);

            if (! isset($wanted[$identity])) {
                continue;
            }

            $available = $row->archived_at === null;
            $usable = $said !== '' && $correction !== '';

            $signals[$identity] ??= ['meaning' => false, 'split_broke' => false];
            $signals[$identity]['meaning'] = $signals[$identity]['meaning'] || $usable;
            $signals[$identity]['split_broke'] = $signals[$identity]['split_broke']
                || (bool) $row->split_broke;

            if (! isset($items[$identity])) {
                $items[$identity] = [
                    'id' => FeedbackType::Mistake->value.'|'.$identity,
                    'kind' => FeedbackType::Mistake->value,
                    'identity' => $identity,
                    'title' => $said,
                    'correction' => $correction,
                    'correction_html' => $correction === ''
                        ? ''
                        : RichText::cleanInlineWithText((string) $row->correction_html)['html'],
                    'available' => $available,
                    'eligible' => false,
                    'reason' => null,
                    'source' => $this->source($row, 'entry', FeedbackType::Mistake),
                ];

                continue;
            }

            if ($available) {
                $items[$identity]['available'] = true;
            }

            // A newer appearance may have gained a correction that an older
            // one had left empty; the most recent non-empty one is shown.
            if ($items[$identity]['correction'] === '' && $correction !== '') {
                $items[$identity]['correction'] = $correction;
                $items[$identity]['correction_html'] = RichText::cleanInlineWithText(
                    (string) $row->correction_html,
                )['html'];
            }
        }

        return $this->withEligibility(FeedbackType::Mistake, $items, $signals);
    }

    /**
     * @param  list<string>  $identities
     * @return array<string, CustomReviewItemData>
     */
    private function questionItems(array $identities): array
    {
        $rows = DB::table('practice_questions as q')
            ->join('practice_sessions as s', 's.id', '=', 'q.practice_session_id')
            ->join('topics as t', 't.id', '=', 's.topic_id')
            ->whereIn('q.id', array_map(intval(...), $identities))
            ->select([
                'q.id',
                'q.position as question_position',
                'q.question_html',
                'q.question_text',
                's.id as session_id',
                's.created_at as session_date',
                's.status',
                's.archived_at',
                't.name as topic',
            ])
            ->selectSub(
                DB::table('practice_feedback_entries as natural')
                    ->selectRaw('1')
                    ->whereColumn('natural.practice_question_id', 'q.id')
                    ->where('natural.type', FeedbackType::NaturalVersion->value)
                    ->whereRaw("TRIM(natural.content_text) <> ''")
                    ->limit(1),
                'natural_version',
            )
            ->get();

        /** @var array<string, CustomReviewItemData> $items */
        $items = [];

        foreach ($rows as $row) {
            $identity = (string) $row->id;
            $title = (string) $row->question_text;

            if ($title === '') {
                $title = RichText::toPlainText((string) $row->question_html);
            }

            $available = $row->status === PracticeSession::STATUS_FINISHED
                && $row->archived_at === null;
            $usable = $title !== '' && $row->natural_version !== null;
            $reason = CustomReviewEligibility::reason(
                FeedbackType::Question,
                $available,
                $usable,
            );

            $items[$identity] = [
                'id' => FeedbackType::Question->value.'|'.$identity,
                'kind' => FeedbackType::Question->value,
                'identity' => $identity,
                'title' => $title === '' ? 'Question '.(int) $row->question_position : $title,
                'correction' => '',
                'correction_html' => '',
                'available' => $available,
                'eligible' => $reason === null,
                'reason' => $reason,
                'source' => $this->source($row, 'question', FeedbackType::Question),
            ];
        }

        return $items;
    }

    /**
     * @return CustomReviewItemData
     */
    private function entryItem(
        FeedbackType $kind,
        string $identity,
        stdClass $row,
        bool $available,
    ): array {
        return [
            'id' => $kind->value.'|'.$identity,
            'kind' => $kind->value,
            'identity' => $identity,
            'title' => (string) $row->content_text,
            'correction' => '',
            'correction_html' => '',
            'available' => $available,
            'eligible' => false,
            'reason' => null,
            'source' => $this->source($row, 'entry', $kind),
        ];
    }

    /**
     * Fill in each item's eligibility from the signals its rows carried. Words
     * and phrases need a meaning anywhere, and either kind reports a line that
     * could not be split with the more specific reason.
     *
     * @param  array<string, CustomReviewItemData>  $items
     * @param  array<string, array{meaning: bool, split_broke: bool}>  $signals
     * @return array<string, CustomReviewItemData>
     */
    private function withEligibility(FeedbackType $kind, array $items, array $signals): array
    {
        foreach ($items as $identity => $item) {
            $signal = $signals[$identity] ?? ['meaning' => false, 'split_broke' => false];
            $reason = CustomReviewEligibility::reason(
                $kind,
                $item['available'],
                $signal['meaning'],
                $signal['split_broke'],
            );

            $items[$identity]['eligible'] = $reason === null;
            $items[$identity]['reason'] = $reason;
        }

        return $items;
    }

    /** Whether a grammar note can still make the fill-in-the-blank exercise. */
    private function noteCanMakeABlank(stdClass $row): bool
    {
        $note = new PracticeFeedbackEntry;
        $note->forceFill([
            'type' => FeedbackType::GrammarNote,
            'content_html' => (string) ($row->content_html ?? ''),
            'content_text' => (string) $row->content_text,
        ]);

        return BlankExercise::build($note) !== null;
    }

    /**
     * Where an item came from, as the reading page names it.
     *
     * @param  'question'|'entry'  $field
     * @return CustomReviewSource
     */
    private function source(stdClass $row, string $field, FeedbackType $kind): array
    {
        $entryId = is_string($row->client_id ?? null) && $row->client_id !== ''
            ? $row->client_id
            : null;

        return [
            'session_id' => (int) $row->session_id,
            'session_date' => $row->session_date === null
                ? null
                : CarbonImmutable::parse((string) $row->session_date),
            'topic' => (string) $row->topic,
            'question_position' => (int) $row->question_position,
            'entry_id' => $field === 'entry' ? $entryId : null,
            'entry_position' => $field === 'entry' ? (int) ($row->entry_position ?? 0) : 0,
            'field' => $field,
            'type' => $field === 'entry' ? $kind->value : null,
        ];
    }

    /**
     * The rows of one kind, with the question, session and topic they belong
     * to. Archived sessions stay here, because their items still exist and
     * only become unavailable.
     */
    private function entryQuery(FeedbackType $kind): QueryBuilder
    {
        return DB::table('practice_feedback_entries as e')
            ->join('practice_questions as q', 'q.id', '=', 'e.practice_question_id')
            ->join('practice_sessions as s', 's.id', '=', 'q.practice_session_id')
            ->join('topics as t', 't.id', '=', 's.topic_id')
            ->where('e.type', $kind->value);
    }

    /**
     * The place of a row inside its box, counted in one pass and zero-based,
     * in the order the reading page numbers entries. A correlated count per
     * row would multiply across a large review, so the window does it once.
     *
     * @return literal-string
     */
    private function entryPositionExpression(): string
    {
        return 'ROW_NUMBER() OVER (PARTITION BY e.practice_question_id, e.type ORDER BY e.position, e.id) - 1 AS entry_position';
    }
}
