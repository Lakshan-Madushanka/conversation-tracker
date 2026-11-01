<?php

declare(strict_types=1);

use App\Actions\Collections\SaveItemMark;
use App\Actions\Collections\SaveItemNotes;
use App\Actions\PracticeQuestions\SavePracticeQuestions;
use App\Enums\CollectionItemStatus;
use App\Enums\FeedbackType;
use App\Models\CollectionItemMark;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use App\Support\MistakeIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A finished session at a known moment, free of archive.
 *
 * @param  array<string, mixed>  $attributes
 */
function editSession(array $attributes = [], ?CarbonImmutable $at = null): PracticeSession
{
    $at ??= CarbonImmutable::now();

    return PracticeSession::factory()
        ->finished()
        ->for(Topic::factory()->create())
        ->create(array_merge([
            'created_at' => $at,
            'finished_at' => $at,
            'last_changed_at' => $at,
        ], $attributes));
}

/**
 * One row of a table box, saved under a known client id, as the editor
 * would have left it the first time.
 *
 * @param  array<string, mixed>  $attributes
 */
function editRow(
    PracticeSession $session,
    string $subject,
    FeedbackType $type = FeedbackType::Vocabulary,
    array $attributes = [],
): PracticeFeedbackEntry {
    $question = PracticeQuestion::factory()->for($session)->create([
        'position' => $session->questions()->count() + 1,
    ]);

    return PracticeFeedbackEntry::factory()->for($question)->create(array_merge([
        'type' => $type,
        'content_text' => $subject,
        'correction_text' => '',
        'client_id' => (string) Str::uuid(),
        'reason' => '',
        'example' => '',
        'example_html' => [],
        'position' => 0,
        'is_split' => true,
    ], $attributes));
}

/**
 * Saves the questions of a session the way the editor does, inside the same
 * transaction the route handler would run.
 *
 * @param  list<array<string, mixed>>  $questions
 */
function editSave(PracticeSession $session, array $questions): void
{
    DB::transaction(fn (): mixed => app(SavePracticeQuestions::class)->handle($session, $questions));
}

/**
 * One saved question with feedback, as the editor sends it.
 *
 * @param  array<string, mixed>  $feedback
 * @return list<array<string, mixed>>
 */
function editQuestion(array $feedback): array
{
    return [
        [
            'question_html' => '<p>What did you notice?</p>',
            'answer_html' => '<p>A sentence.</p>',
            'feedback' => $feedback,
        ],
    ];
}

describe('the typo rule', function (): void {
    it('moves the marks with a word fixed nowhere else', function (): void {
        $session = editSession();
        $row = editRow($session, 'teh', FeedbackType::Vocabulary, ['correction_text' => 'slow to act']);

        app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, 'teh', true, CollectionItemStatus::StillDifficult);
        app(SaveItemNotes::class)->handle(FeedbackType::Vocabulary, 'teh', '<p>A note about the misspelling.</p>', 0);

        editSave($session, editQuestion([
            'vocabulary' => [[
                'id' => $row->client_id,
                'text' => 'the',
                'meaning' => 'slow to act',
            ]],
        ]));

        // The mark now belongs to the word as it is spelled now — star,
        // status and note together.
        $mark = CollectionItemMark::query()->sole();

        expect(CollectionItemMark::keyFor(FeedbackType::Vocabulary, 'the'))->toBe($mark->item_key)
            ->and($mark->starred)->toBeTrue()
            ->and($mark->status)->toBe(CollectionItemStatus::StillDifficult)
            ->and($mark->notes_html)->toContain('A note about the misspelling.');
    });

    it('moves the marks with a mistake that was corrected', function (): void {
        $session = editSession();
        $row = editRow(
            $session,
            'I went',
            FeedbackType::Mistake,
            ['correction_text' => 'I has went'],
        );

        $fromKey = MistakeIdentity::pairKey('I went', 'I has went');
        app(SaveItemMark::class)->handle(FeedbackType::Mistake, $fromKey, true, null);

        editSave($session, editQuestion([
            'mistake' => [[
                'id' => $row->client_id,
                'text' => 'I went',
                'correction_html' => 'I have gone',
            ]],
        ]));

        $mark = CollectionItemMark::query()->sole();

        expect(CollectionItemMark::keyFor(
            FeedbackType::Mistake,
            MistakeIdentity::pairKey('I went', 'I have gone'),
        ))->toBe($mark->item_key);
    });

    it('keeps the marks on a word that still appears elsewhere', function (): void {
        // The word 'teh' was recorded in two finished sessions; only one is
        // being fixed now.
        $fixed = editSession();
        $row = editRow($fixed, 'teh', FeedbackType::Vocabulary, ['correction_text' => 'slow to act']);
        $other = editSession([], CarbonImmutable::yesterday());
        editRow($other, 'teh', FeedbackType::Vocabulary, ['correction_text' => 'hesitant']);

        app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, 'teh', true, null);

        editSave($fixed, editQuestion([
            'vocabulary' => [[
                'id' => $row->client_id,
                'text' => 'the',
                'meaning' => 'slow to act',
            ]],
        ]));

        // The old item keeps the learner's marks; the fixed spelling starts
        // with none.
        $mark = CollectionItemMark::query()->sole();

        expect(CollectionItemMark::keyFor(FeedbackType::Vocabulary, 'teh'))->toBe($mark->item_key)
            ->and($mark->starred)->toBeTrue();
    });

    it('keeps the marks of a word that already has its own', function (): void {
        $session = editSession();
        $row = editRow($session, 'teh', FeedbackType::Vocabulary, ['correction_text' => 'slow to act']);

        app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, 'teh', true, CollectionItemStatus::StillDifficult);
        app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, 'the', false, CollectionItemStatus::Learned);

        editSave($session, editQuestion([
            'vocabulary' => [[
                'id' => $row->client_id,
                'text' => 'the',
                'meaning' => 'slow to act',
            ]],
        ]));

        // Nothing was overwritten: both marks remain, each under its own
        // identity, and the one for 'the' stayed untouched.
        $marks = CollectionItemMark::query()->orderBy('id')->get();

        expect($marks)->toHaveCount(2)
            ->and(CollectionItemMark::keyFor(FeedbackType::Vocabulary, 'teh'))->toBe($marks->first()->item_key)
            ->and($marks->first()->status)->toBe(CollectionItemStatus::StillDifficult)
            ->and(CollectionItemMark::keyFor(FeedbackType::Vocabulary, 'the'))->toBe($marks->last()->item_key)
            ->and($marks->last()->starred)->toBeFalse()
            ->and($marks->last()->status)->toBe(CollectionItemStatus::Learned);
    });

    it('moves nothing when capitals, accents or spacing alone change', function (): void {
        $session = editSession();
        $row = editRow($session, 'teh', FeedbackType::Vocabulary, ['correction_text' => 'slow to act']);

        app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, 'teh', true, null);

        // Capitalisation, an accent mark and spacing are still the same item
        // by the shared normalisation, so the star stays put.
        editSave($session, editQuestion([

            'vocabulary' => [[
                'id' => $row->client_id,
                'text' => 'TEH',
            ]],
        ]));

        $mark = CollectionItemMark::query()->sole();

        expect(CollectionItemMark::keyFor(FeedbackType::Vocabulary, 'teh'))->toBe($mark->item_key)
            ->and($mark->starred)->toBeTrue();
    });

    it('moves nothing in an unfinished session', function (): void {
        $session = editSession(['status' => PracticeSession::STATUS_UNFINISHED]);
        $row = editRow($session, 'teh', FeedbackType::Vocabulary, ['correction_text' => 'slow to act']);

        app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, 'teh', true, null);

        editSave($session, editQuestion([
            'vocabulary' => [[
                'id' => $row->client_id,
                'text' => 'the',
                'meaning' => 'slow to act',
            ]],
        ]));

        $mark = CollectionItemMark::query()->sole();

        expect(CollectionItemMark::keyFor(FeedbackType::Vocabulary, 'teh'))->toBe($mark->item_key)
            ->and($mark->starred)->toBeTrue();
    });

    it('moves nothing in an archived session', function (): void {
        $session = editSession(['archived_at' => CarbonImmutable::now()]);
        $row = editRow($session, 'teh', FeedbackType::Vocabulary, ['correction_text' => 'slow to act']);

        app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, 'teh', true, null);

        editSave($session, editQuestion([
            'vocabulary' => [[
                'id' => $row->client_id,
                'text' => 'the',
                'meaning' => 'slow to act',
            ]],
        ]));

        $mark = CollectionItemMark::query()->sole();

        expect(CollectionItemMark::keyFor(FeedbackType::Vocabulary, 'teh'))->toBe($mark->item_key)
            ->and($mark->starred)->toBeTrue();
    });

    it('moves nothing for a grammar note, whose marks belong to the note', function (): void {
        $session = editSession();
        $row = editRow(
            $session,
            'Simple past for finished actions.',
            FeedbackType::GrammarNote,
            ['content_html' => '<p>Simple past for finished actions.</p>'],
        );

        app(SaveItemMark::class)->handle(FeedbackType::GrammarNote, 'note-'.$row->id, true, null);
        app(SaveItemNotes::class)->handle(FeedbackType::GrammarNote, 'note-'.$row->id, '<p>My own reminder.</p>', 0);

        // The note's wording is rewritten entirely; its identity is still the
        // note itself.
        editSave($session, editQuestion([
            'grammar_note' => [[
                'id' => $row->client_id,
                'html' => '<p>Use the simple past for actions that finished.</p>',
            ]],
        ]));

        $mark = CollectionItemMark::query()->sole();

        expect($mark->item_key)->toBe(CollectionItemMark::keyFor(FeedbackType::GrammarNote, 'note-'.$row->id))
            ->and($mark->starred)->toBeTrue()
            ->and($mark->notes_html)->toContain('My own reminder.');
    });

    it('stays the same when the same edit is saved again', function (): void {
        $session = editSession();
        $row = editRow($session, 'teh', FeedbackType::Vocabulary, ['correction_text' => 'slow to act']);

        app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, 'teh', true, null);

        $save = fn (): mixed => editSave($session, editQuestion([
            'vocabulary' => [[
                'id' => $row->client_id,
                'text' => 'the',
                'meaning' => 'slow to act',
            ]],
        ]));

        $save();
        $save();
        editSave($session, editQuestion([
            'vocabulary' => [[
                'text' => 'the',
                'meaning' => 'slow to act',
            ]],
        ]));

        $marks = CollectionItemMark::query()->get();
        expect($marks)->toHaveCount(1)
            ->and(CollectionItemMark::keyFor(FeedbackType::Vocabulary, 'the'))->toBe($marks->first()->item_key)
            ->and($marks->first()->starred)->toBeTrue();
    });

    it('rolls the move back with the rest of a failed save', function (): void {
        $session = editSession();
        $row = editRow($session, 'teh', FeedbackType::Vocabulary, ['correction_text' => 'slow to act']);

        app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, 'teh', true, null);

        try {
            DB::transaction(function () use ($session, $row): void {
                editSave($session, editQuestion([
                    'vocabulary' => [[
                        'id' => $row->client_id,
                        'text' => 'the',
                        'meaning' => 'slow to act',
                    ]],
                ]));

                throw new RuntimeException('Something later in the save failed.');
            });
        } catch (RuntimeException) {
        }

        // The entry and the mark are both exactly as the save left them
        // before it began.
        $entry = $row->fresh();
        $mark = CollectionItemMark::query()->sole();

        expect($entry->content_text)->toBe('teh')
            ->and(CollectionItemMark::keyFor(FeedbackType::Vocabulary, 'teh'))->toBe($mark->item_key)
            ->and($mark->starred)->toBeTrue();
    });

    it('shows the moved marks on the page under the fixed word', function (): void {
        $session = editSession();
        $row = editRow($session, 'teh', FeedbackType::Vocabulary, ['correction_text' => 'slow to act']);

        app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, 'teh', true, null);

        editSave($session, editQuestion([
            'vocabulary' => [[
                'id' => $row->client_id,
                'text' => 'the',
                'meaning' => 'slow to act',
            ]],
        ]));

        $this->get(route('collections.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.key', 'the')
                ->where('items.0.starred', true));
    });
});
