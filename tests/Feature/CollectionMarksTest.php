<?php

declare(strict_types=1);

use App\Actions\Collections\SaveItemMark;
use App\Actions\Collections\SaveItemNotes;
use App\Enums\CollectionItemStatus;
use App\Enums\FeedbackType;
use App\Models\CollectionItemMark;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use App\Queries\TableCollection;
use App\Support\MistakeIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A finished session at a known moment.
 *
 * @param  array<string, mixed>  $attributes
 */
function marksSession(CarbonImmutable $at, array $attributes = []): PracticeSession
{
    return PracticeSession::factory()
        ->finished()
        ->for(Topic::factory()->create())
        ->create(array_merge([
            'created_at' => $at,
            'updated_at' => $at,
            'finished_at' => $at,
            'last_changed_at' => $at,
        ], $attributes));
}

/**
 * One row of a table box (a word, a phrase or a mistake).
 *
 * @param  array<string, mixed>  $attributes
 */
function marksRow(
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
        'reason' => '',
        'example' => '',
        'example_html' => [],
        'position' => 0,
        'is_split' => true,
    ], $attributes));
}

/**
 * One grammar note, always its own item.
 */
function marksNote(PracticeSession $session, string $text, string $html = ''): PracticeFeedbackEntry
{
    $question = PracticeQuestion::factory()->for($session)->create([
        'position' => $session->questions()->count() + 1,
    ]);

    return PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => FeedbackType::GrammarNote,
        'content_html' => $html,
        'content_text' => $text,
        'position' => 0,
    ]);
}

/**
 * The star and status asked for, the way the screen sends them.
 *
 * @param  array<string, mixed>  $body
 */
function marksPut(TestCase $case, array $body): TestResponse
{
    return $case->putJson(route('collections.marks.store'), $body);
}

/**
 * @param  array<string, mixed>  $body
 */
function marksNotesPut(TestCase $case, array $body): TestResponse
{
    return $case->putJson(route('collections.notes.store'), $body);
}

/**
 * The mark one item carries, for any kind from vocabulary to grammar notes.
 *
 * @param  array{starred?: bool, status?: CollectionItemStatus}  $values
 */
function marksForItem(FeedbackType $kind, string $itemKey, array $values): CollectionItemMark
{
    return app(SaveItemMark::class)->handle(
        $kind,
        $itemKey,
        $values['starred'] ?? null,
        $values['status'] ?? null,
    );
}

/** The saved note components of a mistake's identity. */
function marksMistakeKey(string $said, string $correction): string
{
    return MistakeIdentity::pairKey($said, $correction);
}

describe('star and status', function (): void {
    it('stars an item, shows it everywhere the item appears, and unstars it', function (): void {
        $session = marksSession(CarbonImmutable::now());
        marksRow($session, 'reluctant', FeedbackType::Vocabulary, ['correction_text' => 'slow to act']);

        // The item is not marked yet.
        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.starred', false)
                ->where('items.0.status', null)
                ->where('items.0.has_notes', false));

        // Star it, exactly as the list row's control would ask.
        marksPut($this, [
            'kind' => 'vocabulary',
            'item' => 'reluctant',
            'starred' => true,
            'status' => 'none',
        ])->assertOk();

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.starred', true)
                ->where('items.0.status', null)
                ->where('items.0.has_notes', false));

        // The opened item shows the same marks, because they belong to the
        // item and not to the list.
        $this->get(route('collections.index', ['item' => 'reluctant']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('open_item.starred', true));

        // The same word saved again in another finished session still shows
        // the one mark the learner set.
        $second = marksSession(CarbonImmutable::now()->subDay());
        marksRow($second, 'reluctant', FeedbackType::Vocabulary, ['correction_text' => 'hesitant']);

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.seen_count', 2)
                ->where('items.0.starred', true));

        // Unstar it the same way, and the page shows the drop at once.
        marksPut($this, [
            'kind' => 'vocabulary',
            'item' => 'reluctant',
            'starred' => false,
            'status' => 'none',
        ])->assertOk()->assertJsonPath('mark.starred', false);

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.starred', false));
    });

    it('stars and labels items in every collection, mistakes and notes included', function (): void {
        $session = marksSession(CarbonImmutable::now());
        marksRow($session, 'catch up on', FeedbackType::Phrase);
        marksRow($session, 'I went', FeedbackType::Mistake, ['correction_text' => 'I have gone']);
        $note = marksNote($session, 'Use commas after an intro phrase.');

        foreach ([
            ['kind' => 'phrase', 'item' => 'catch up on'],
            ['kind' => 'mistake', 'item' => marksMistakeKey('I went', 'I have gone')],
            ['kind' => 'grammar_note', 'item' => 'note-'.$note->id],
        ] as $mark) {
            marksPut($this, array_merge($mark, [
                'starred' => true,
                'status' => 'learned',
            ]))->assertOk()
                ->assertJsonPath('mark.status', 'learned')
                ->assertJsonPath('mark.starred', true);
        }

        foreach (['phrases', 'mistakes', 'grammar'] as $tab) {
            $this->get(route('collections.index', ['tab' => $tab]))
                ->assertOk()
                ->assertInertia(fn (Assert $page): Assert => $page
                    ->where('total', 1)
                    ->where('items.0.starred', true)
                    ->where('items.0.status', 'learned'));
        }
    });

    it('works on items whose meaning is missing, wherever they ask for attention', function (): void {
        $session = marksSession(CarbonImmutable::now());
        marksRow($session, 'relish');

        marksPut($this, [
            'kind' => 'vocabulary', 'item' => 'relish', 'starred' => true, 'status' => 'still_difficult',
        ])->assertOk();

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.needs_attention', true)
                ->where('items.0.starred', true)
                ->where('items.0.status', 'still_difficult'));

        marksNotesPut($this, [
            'kind' => 'vocabulary', 'item' => 'relish', 'html' => '<p>Find the difference.</p>', 'version' => 0,
        ])->assertOk();

        $this->get(route('collections.index', ['item' => 'relish']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('open_item.has_notes', true));
    });

    it('sets a status, keeps at most one, and clears it', function (): void {
        $session = marksSession(CarbonImmutable::now());
        marksRow($session, 'relish');

        marksPut($this, [
            'kind' => 'vocabulary',
            'item' => 'relish',
            'starred' => true,
            'status' => 'learned',
        ])->assertOk()->assertJsonPath('mark.status', 'learned');

        // A second choice replaces the first: at most one status at a time.
        marksPut($this, [
            'kind' => 'vocabulary',
            'item' => 'relish',
            'starred' => false,
            'status' => 'still_difficult',
        ])->assertOk()
            ->assertJsonPath('mark.status', 'still_difficult')
            ->assertJsonPath('mark.starred', false);

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.status', 'still_difficult'));

        // Choosing the active option again, or Clear status, sends "none".
        marksPut($this, [
            'kind' => 'vocabulary',
            'item' => 'relish',
            'starred' => false,
            'status' => 'none',
        ])->assertOk()->assertJsonPath('mark.status', null);

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.status', null));
    });

    it('is safe to repeat: the desired state wins, never a toggle', function (): void {
        $session = marksSession(CarbonImmutable::now());
        marksRow($session, 'relish');

        // A hurried double press and repeated requests all ask for the same
        // state, so a busy connection cannot flip the star.
        foreach (range(1, 3) as $attempt) {
            marksPut($this, [
                'kind' => 'vocabulary',
                'item' => 'relish',
                'starred' => true,
                'status' => 'none',
            ])->assertOk();
        }

        // An unstar from one tab and a star from another: the last choice
        // sent wins, and the same row holds it.
        marksPut($this, [
            'kind' => 'vocabulary', 'item' => 'relish', 'starred' => true, 'status' => 'none',
        ])->assertOk();
        marksPut($this, [
            'kind' => 'vocabulary', 'item' => 'relish', 'starred' => false, 'status' => 'none',
        ])->assertOk();

        $this->assertSame(1, CollectionItemMark::query()->count());

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.starred', false));
    });

    it('never changes a session, an entry or any collection count', function (): void {
        $session = marksSession(CarbonImmutable::now());
        marksRow($session, 'reluctant', FeedbackType::Vocabulary, ['correction_text' => 'slow to act']);
        $note = marksNote($session, 'Use the past perfect.');
        $entries = PracticeFeedbackEntry::query()->count();

        $before = app(TableCollection::class)->counts();

        marksPut($this, [
            'kind' => 'vocabulary', 'item' => 'reluctant', 'starred' => true, 'status' => 'still_difficult',
        ])->assertOk();
        marksPut($this, [
            'kind' => 'grammar_note', 'item' => 'note-'.$note->id, 'starred' => true, 'status' => 'none',
        ])->assertOk();

        $this->assertSame($entries, PracticeFeedbackEntry::query()->count());
        $this->assertSame($before, app(TableCollection::class)->counts());

        // The session itself carries no trace of any mark.
        $session->refresh();
        $this->assertTrue($session->isFinished());
        $this->assertNull($session->archived_at);
    });

    it('refuses a kind beyond Collections and an unknown status', function (): void {
        $session = marksSession(CarbonImmutable::now());
        marksRow($session, 'relish');

        marksPut($this, ['kind' => 'natural_version', 'item' => 'hello', 'starred' => true, 'status' => 'none'])
            ->assertStatus(422);
        marksPut($this, ['kind' => 'vocabulary', 'item' => 'relish', 'starred' => false, 'status' => 'automatically'])
            ->assertStatus(422);
        marksPut($this, ['kind' => 'vocabulary', 'item' => 'relish', 'starred' => false, 'status' => ''])
            ->assertStatus(422);

        // Nothing was stored for the item.
        $this->assertSame(0, CollectionItemMark::query()->count());
    });
});

describe('personal notes', function (): void {
    it('saves cleaned formatted notes and a matching plain-text copy', function (): void {
        $session = marksSession(CarbonImmutable::now());
        marksRow($session, 'reluctant', FeedbackType::Vocabulary, ['correction_text' => 'slow to act']);

        marksNotesPut($this, [
            'kind' => 'vocabulary',
            'item' => 'reluctant',
            'html' => '<p>Word family:</p><ul><li><strong>reluctant</strong></li>'
                .'<li><script>alert(1)</script><em>reluctance</em></li></ul>',
            'version' => 0,
        ])->assertOk()->assertJsonPath('mark.has_notes', true);

        $mark = CollectionItemMark::query()->firstOrFail();

        // Unsafe content is never saved, supported formatting is kept, and
        // the plain copy is what a later search reads.
        $this->assertStringNotContainsString('<script', $mark->notes_html);
        $this->assertStringNotContainsString('alert(1)', $mark->notes_html);
        $this->assertStringContainsString('<strong>reluctant</strong>', $mark->notes_html);
        $this->assertStringContainsString('<em>reluctance</em>', $mark->notes_html);
        $this->assertStringContainsString('Word family', $mark->notes_text);
        $this->assertStringContainsString('reluctant', $mark->notes_text);
        $this->assertStringContainsString('reluctance', $mark->notes_text);
    });

    it('treats a note of only blank space or empty formatting as no note', function (): void {
        $session = marksSession(CarbonImmutable::now());
        marksRow($session, 'relish');

        marksNotesPut($this, [
            'kind' => 'vocabulary', 'item' => 'relish', 'html' => '<p>   </p><strong></strong>', 'version' => 0,
        ])->assertOk()
            ->assertJsonPath('mark.has_notes', false)
            ->assertJsonPath('mark.notes_html', '');

        // Nothing was written for a blank note: not stored, not shown.
        $this->assertSame(0, CollectionItemMark::query()->count());

        marksNotesPut($this, [
            'kind' => 'vocabulary', 'item' => 'relish', 'html' => '', 'version' => 0,
        ])->assertOk()->assertJsonPath('mark.has_notes', false);
    });

    it('accepts generous notes up to the limit and refuses longer ones without losing anything', function (): void {
        $session = marksSession(CarbonImmutable::now());
        marksRow($session, 'relish');

        marksNotesPut($this, [
            'kind' => 'vocabulary',
            'item' => 'relish',
            'html' => '<p>'.str_repeat('word ', 2000).'</p>',
            'version' => 0,
        ])->assertOk()
            ->assertJsonPath('mark.has_notes', true);

        $saved = CollectionItemMark::query()->firstOrFail();

        // One character beyond the limit: told clearly, and the saved note
        // keeps everything it had — nothing was trimmed away.
        marksNotesPut($this, [
            'kind' => 'vocabulary',
            'item' => 'relish',
            'html' => '<p>'.str_repeat('word ', 2000).'and more</p>',
            'version' => 1,
        ])->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'A note can hold up to 10,000 characters. Remove a little to save it.',
            ]);

        $saved->refresh();
        $this->assertSame(1, $saved->notes_version);
    });

    it('stops when another tab changed the note, so nothing is overwritten', function (): void {
        $session = marksSession(CarbonImmutable::now());
        marksRow($session, 'relish');

        marksNotesPut($this, [
            'kind' => 'vocabulary', 'item' => 'relish', 'html' => '<p>First tab wrote this.</p>', 'version' => 0,
        ])->assertOk();

        // A second tab still holds the note as it read it, at version 0. The
        // save stops and returns what is already stored, so the learner
        // chooses, and nothing disappears.
        marksNotesPut($this, [
            'kind' => 'vocabulary', 'item' => 'relish', 'html' => '<p>Second tab wrote this.</p>', 'version' => 0,
        ])->assertStatus(409)
            ->assertJsonPath('conflict', true)
            ->assertJsonPath('mark.notes_version', 1)
            ->assertJsonPath('mark.notes_html', '<p>First tab wrote this.</p>');

        $mark = CollectionItemMark::query()->firstOrFail();
        $this->assertStringNotContainsString('Second tab', $mark->notes_text);
        $this->assertSame(1, $mark->notes_version);
    });

    it('repeats a notes save cleanly and never clobbers a newer note', function (): void {
        $session = marksSession(CarbonImmutable::now());
        marksRow($session, 'relish');

        marksNotesPut($this, [
            'kind' => 'vocabulary', 'item' => 'relish', 'html' => '<p>Saved once.</p>', 'version' => 0,
        ])->assertOk();

        // A retry carrying the version the first save answered with, and the
        // same content, changes nothing.
        marksNotesPut($this, [
            'kind' => 'vocabulary', 'item' => 'relish', 'html' => '<p>Saved once.</p>', 'version' => 1,
        ])->assertOk()->assertJsonPath('mark.notes_version', 1);

        // A retry carrying a version older than what is stored raises the
        // conflict instead of overwriting newer writing.
        marksNotesPut($this, [
            'kind' => 'vocabulary', 'item' => 'relish', 'html' => '<p>Saved once.</p>', 'version' => 99,
        ])->assertStatus(409);

        $this->assertSame(1, CollectionItemMark::query()->count());
    });

    it('keeps the star and status separate from the notes', function (): void {
        $session = marksSession(CarbonImmutable::now());
        marksRow($session, 'relish');

        marksPut($this, ['kind' => 'vocabulary', 'item' => 'relish', 'starred' => true, 'status' => 'learned'])
            ->assertOk();
        marksNotesPut($this, [
            'kind' => 'vocabulary', 'item' => 'relish', 'html' => '<p>A note worth keeping.</p>', 'version' => 0,
        ])->assertOk();

        $mark = CollectionItemMark::query()->firstOrFail();
        $this->assertTrue($mark->starred);
        $this->assertSame(CollectionItemStatus::Learned, $mark->status);
        $this->assertSame(1, $mark->notes_version);

        marksNotesPut($this, [
            'kind' => 'vocabulary', 'item' => 'relish', 'html' => '<p>Changed.</p>', 'version' => 1,
        ])->assertOk();
        $mark = CollectionItemMark::query()->firstOrFail();
        $this->assertTrue($mark->starred);
        $this->assertSame(CollectionItemStatus::Learned, $mark->status);

        marksPut($this, ['kind' => 'vocabulary', 'item' => 'relish', 'starred' => false, 'status' => 'none'])
            ->assertOk();
        $mark = CollectionItemMark::query()->firstOrFail();
        $mark = CollectionItemMark::query()->firstOrFail();
        $this->assertSame(2, $mark->notes_version);
        $this->assertStringContainsString('Changed.', $mark->notes_text);
    });

    it('shows a has-notes indicator for notes that hold words, and none for blank ones', function (): void {
        // Distinct session times, so the page order is known: the newest word
        // comes first.
        $session = marksSession(CarbonImmutable::now());
        marksRow($session, 'relish', FeedbackType::Vocabulary, ['correction_text' => 'to enjoy']);
        $older = marksSession(CarbonImmutable::now()->subHours(2));
        marksRow($older, 'reluctant', FeedbackType::Vocabulary, ['correction_text' => 'slow to act']);

        marksNotesPut($this, [
            'kind' => 'vocabulary', 'item' => 'relish', 'html' => '<p>One of my favourites.</p>', 'version' => 0,
        ])->assertOk();

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.key', 'relish')
                ->where('items.0.has_notes', true)
                ->where('items.1.key', 'reluctant')
                ->where('items.1.has_notes', false));
    });
});

describe('marks on the page', function (): void {
    it('sends the marks of a page with the items, and the notes with the panel', function (): void {
        // Distinct session times, so the page order is known: starred word,
        // then difficult word, then plain word.
        $first = marksSession(CarbonImmutable::now());
        marksRow($first, 'starred word', FeedbackType::Vocabulary, ['correction_text' => 'its meaning']);
        $second = marksSession(CarbonImmutable::now()->subHours(2));
        marksRow($second, 'difficult word', FeedbackType::Vocabulary, ['correction_text' => 'its meaning']);
        $third = marksSession(CarbonImmutable::now()->subHours(4));
        marksRow($third, 'plain word', FeedbackType::Vocabulary, ['correction_text' => 'its meaning']);

        marksForItem(FeedbackType::Vocabulary, 'starred word', ['starred' => true]);
        marksForItem(FeedbackType::Vocabulary, 'difficult word', ['status' => CollectionItemStatus::StillDifficult]);
        app(SaveItemNotes::class)->handle(FeedbackType::Vocabulary, 'starred word', '<p>A note.</p>', 0);

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.key', 'starred word')
                ->where('items.0.starred', true)
                ->where('items.0.has_notes', true)
                ->where('items.0.status', null)
                ->where('items.1.key', 'difficult word')
                ->where('items.1.status', 'still_difficult')
                ->where('items.2.key', 'plain word')
                ->where('items.2.starred', false));

        $this->get(route('collections.index', ['item' => 'starred word']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('open_item.starred', true)
                ->where('open_item.has_notes', true)
                ->where('open_item.notes_version', 1)
                ->where('open_item.notes_html', '<p>A note.</p>'));
    });

    it('looks up the marks of a page in one request, whatever it holds', function (): void {
        $session = marksSession(CarbonImmutable::now());
        collect(range(1, 60))
            ->each(fn (int $index): PracticeFeedbackEntry => marksRow(
                $session,
                'word '.$index,
                FeedbackType::Vocabulary,
                ['correction_text' => 'its meaning'],
            ));

        collect([1, 11, 21, 31, 41, 51])
            ->each(fn (int $index): CollectionItemMark => marksForItem(
                FeedbackType::Vocabulary,
                'word '.$index,
                ['starred' => true],
            ));

        DB::enableQueryLog();
        $this->get(route('collections.index', ['per_page' => 5]));
        $small = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->get(route('collections.index', ['per_page' => 60]));
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        $this->assertSame($small, $large);
    });

    it('marks belong to the item, not to the session', function (): void {
        $session = marksSession(CarbonImmutable::now());
        marksRow($session, 'catch', FeedbackType::Vocabulary, ['correction_text' => 'to catch']);
        marksRow($session, 'catch', FeedbackType::Phrase);

        marksPut($this, ['kind' => 'vocabulary', 'item' => 'catch', 'starred' => true, 'status' => 'none'])
            ->assertOk();

        // The phrase of the same wording carries no mark of its own.
        $this->get(route('collections.index', ['tab' => 'phrases']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.starred', false));

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.starred', true));
    });

    it('keeps the marks of two identical grammar notes separate', function (): void {
        $session = marksSession(CarbonImmutable::now());
        $first = marksNote($session, 'Say it once, clearly.');
        $second = marksNote($session, 'Say it once, clearly.');

        marksForItem(FeedbackType::GrammarNote, 'note-'.$first->id, ['starred' => true]);
        marksNotesPut($this, [
            'kind' => 'grammar_note',
            'item' => 'note-'.$second->id,
            'html' => '<p>Only the second one carries my note.</p>',
            'version' => 0,
        ])->assertOk();

        $marks = CollectionItemMark::query()->orderBy('id')->get();

        $this->assertSame(2, $marks->count());
        $this->assertTrue($marks->first()->starred);
        $this->assertStringNotContainsString(
            'Only the second one',
            $marks->first()->notes_text,
        );
        $this->assertFalse($marks->last()->starred);
        $this->assertSame(
            CollectionItemMark::keyFor(FeedbackType::GrammarNote, 'note-'.$second->id),
            $marks->last()->item_key,
        );
        $this->assertStringContainsString('Only the second one', $marks->last()->notes_text);
    });
});
