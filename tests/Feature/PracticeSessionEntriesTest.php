<?php

declare(strict_types=1);

use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use App\Models\User;

/**
 * Build one question payload whose boxes hold entries the way the editor
 * sends them: every entry keeps the id it was given.
 *
 * @param  array<string, list<array{id?: string, text?: string, html?: string}>>  $feedback
 * @return array{question_html: string, answer_html: string, feedback: array<string, mixed>}
 */
function entryQuestionPayload(string $question, array $feedback = []): array
{
    return [
        'question_html' => $question,
        'answer_html' => '',
        'feedback' => $feedback,
    ];
}

/**
 * @param  list<string>  $texts
 * @return list<array{id: string, text: string}>
 */
function textEntries(array $texts, string $prefix = 'e'): array
{
    $entries = [];
    $index = 0;

    foreach (array_values($texts) as $text) {
        $entries[] = ['id' => "{$prefix}-".$index, 'text' => $text];
        $index++;
    }

    return $entries;
}

/**
 * Save a session holding one question with the given feedback boxes.
 *
 * @param  array<string, mixed>  $feedback
 */
function saveEntrySession(array $feedback): PracticeSession
{
    $user = User::factory()->create();

    test()->actingAs($user)->post(route('sessions.store'), [
        'topic' => 'Travel',
        'questions' => [entryQuestionPayload('<p>Where did you go?</p>', $feedback)],
    ])->assertRedirect();

    return PracticeSession::firstOrFail();
}

/** The stored entries of one box, in the order they were saved. */
function boxEntries(PracticeSession $session, string $type): array
{
    $question = PracticeQuestion::firstOrFail();

    return $session->questions
        ->firstOrFail(fn (PracticeQuestion $item): bool => $item->id === $question->id)
        ->feedbackEntries
        ->filter(fn (PracticeFeedbackEntry $entry): bool => $entry->type === $type)
        ->sortBy('position')
        ->values()
        ->all();
}

describe('a pasted list becomes one entry per line', function (): void {
    it('stores every line of a pasted list as its own entry, in order', function (): void {
        $session = saveEntrySession([
            'phrase' => textEntries(['a long way', 'off the beaten track', 'a shortcut']),
        ]);

        $entries = boxEntries($session, PracticeFeedbackEntry::TYPE_PHRASE);

        expect($entries)->toHaveCount(3)
            ->and(array_column($entries, 'content_text'))->toBe(['a long way', 'off the beaten track', 'a shortcut'])
            ->and(array_column($entries, 'position'))->toBe([0, 1, 2]);
    });

    it('splits the lines inside one submitted entry and keeps the first in place', function (): void {
        $session = saveEntrySession([
            'phrase' => textEntries(["a long way\noff the beaten track\na shortcut"], 'paste'),
        ]);

        $entries = boxEntries($session, PracticeFeedbackEntry::TYPE_PHRASE);

        expect($entries)->toHaveCount(3)
            ->and(array_column($entries, 'content_text'))->toBe(['a long way', 'off the beaten track', 'a shortcut'])
            ->and($entries[0]->client_id)->toBe('paste-0')
            ->and($entries[1]->client_id)->not->toBe('')
            ->and($entries[1]->client_id)->not->toBe($entries[0]->client_id);
    });

    it('applies the cleaning rules when it stores pasted lines', function (): void {
        $session = saveEntrySession([
            'phrase' => textEntries(["•\ta long way\n1. off the beaten track\n- a shortcut\n\n   "], 'paste'),
        ]);

        $entries = boxEntries($session, PracticeFeedbackEntry::TYPE_PHRASE);

        expect(array_column($entries, 'content_text'))->toBe(['a long way', 'off the beaten track', 'a shortcut']);
    });

    it('keeps a very long line as one entry instead of cutting it', function (): void {
        $long = str_repeat('a', 900);

        $session = saveEntrySession(['phrase' => textEntries([$long], 'paste')]);

        expect(array_column(boxEntries($session, PracticeFeedbackEntry::TYPE_PHRASE), 'content_text'))->toBe([$long]);
    });
});

describe('entries keep their identity between saves', function (): void {
    it('updates the same vocabulary row when it is edited and does not add another', function (): void {
        $session = saveEntrySession(['vocabulary' => [
            ['id' => 'v-0', 'text' => 'reluctant', 'meaning' => 'unwilling', 'example_html' => ''],
            ['id' => 'v-1', 'text' => 'affordable', 'meaning' => 'cheap', 'example_html' => ''],
        ]]);

        $this->actingAs(User::factory()->create())->put(route('sessions.update', $session), [
            'topic' => 'Travel',
            'questions' => [
                entryQuestionPayload('<p>Where did you go?</p>', [
                    'vocabulary' => [
                        ['id' => 'v-0', 'text' => 'reluctant', 'meaning' => 'not wanting to', 'example_html' => ''],
                        ['id' => 'v-1', 'text' => 'affordable', 'meaning' => 'cheap', 'example_html' => ''],
                    ],
                ]),
            ],
        ])->assertRedirect();

        $entries = boxEntries($session->fresh() ?? $session, PracticeFeedbackEntry::TYPE_VOCABULARY);

        expect($entries)->toHaveCount(2)
            ->and(array_column($entries, 'correction_text'))->toBe(['not wanting to', 'cheap']);
    });

    it('does not duplicate entries when the same save is retried', function (): void {
        $session = saveEntrySession(['vocabulary' => textEntries(['reluctant', 'affordable'])]);

        $payload = [
            'topic' => 'Travel',
            'questions' => [
                entryQuestionPayload('<p>Where did you go?</p>', [
                    'vocabulary' => textEntries(['reluctant', 'affordable']),
                ]),
            ],
        ];

        $this->actingAs(User::factory()->create())->put(route('sessions.update', $session), $payload);
        $this->actingAs(User::factory()->create())->put(route('sessions.update', $session), $payload);

        expect(PracticeFeedbackEntry::count())->toBe(2);
    });

    it('removes only the entry that was removed in the editor', function (): void {
        $session = saveEntrySession(['vocabulary' => textEntries(['first', 'second', 'third'])]);

        $this->actingAs(User::factory()->create())->put(route('sessions.update', $session), [
            'topic' => 'Travel',
            'questions' => [
                entryQuestionPayload('<p>Where did you go?</p>', [
                    'vocabulary' => textEntries(['first', 'third']),
                ]),
            ],
        ]);

        expect(array_column(boxEntries($session, PracticeFeedbackEntry::TYPE_VOCABULARY), 'content_text'))->toBe(['first', 'third']);
    });

    it('keeps the entries of one box apart from the entries of another', function (): void {
        $session = saveEntrySession([
            'vocabulary' => textEntries(['same text'], 'a'),
            'phrase' => textEntries(['same text'], 'a'),
        ]);

        expect(PracticeFeedbackEntry::count())->toBe(2);
    });

    it('stamps a server id on entries that arrive without one', function (): void {
        $session = saveEntrySession([
            'vocabulary' => [['text' => 'reluctant']],
        ]);

        $entry = boxEntries($session, PracticeFeedbackEntry::TYPE_VOCABULARY)[0];

        expect($entry->client_id)->toBeString()->not->toBe('');
    });

    it('keeps grammar notes as separate rich entries and never splits them', function (): void {
        $session = saveEntrySession([
            'grammar_note' => [
                ['id' => 'note-1', 'html' => '<p>Use the <strong>past tense</strong>.</p>'],
                ['id' => 'note-2', 'html' => '<p>First line<br>second line</p>'],
            ],
        ]);

        $entries = boxEntries($session, PracticeFeedbackEntry::TYPE_GRAMMAR_NOTE);

        expect($entries)->toHaveCount(2)
            ->and($entries[0]->content_html)->toBe('<p>Use the <strong>past tense</strong>.</p>')
            ->and($entries[1]->content_html)->toBe('<p>First line<br>second line</p>')
            ->and($entries[0]->client_id)->toBe('note-1');
    });

    it('drops a grammar note that is left empty', function (): void {
        $session = saveEntrySession([
            'grammar_note' => [
                ['id' => 'note-1', 'html' => '<p>Keep me</p>'],
                ['id' => 'note-2', 'html' => '<p></p>'],
            ],
        ]);

        $entries = boxEntries($session, PracticeFeedbackEntry::TYPE_GRAMMAR_NOTE);

        expect($entries)->toHaveCount(1)
            ->and($entries[0]->content_text)->toBe('Keep me');
    });

    it('keeps the natural version one piece of formatted text', function (): void {
        $session = saveEntrySession([
            'natural_version' => '<p>Where did you <em>end up</em> going?</p>',
        ]);

        $entry = boxEntries($session, PracticeFeedbackEntry::TYPE_NATURAL_VERSION)[0];

        expect($entry->content_html)->toBe('<p>Where did you <em>end up</em> going?</p>')
            ->and(PracticeFeedbackEntry::count())->toBe(1);
    });
});

describe('the vocabulary table', function (): void {
    it('stores a row across the word, meaning and example columns', function (): void {
        $session = saveEntrySession(['vocabulary' => [
            [
                'id' => 'v-0',
                'text' => 'reluctant',
                'meaning' => 'unwilling',
                'example_html' => '<p>He was <em>reluctant</em> to go.</p>',
            ],
        ]]);

        $entry = boxEntries($session, PracticeFeedbackEntry::TYPE_VOCABULARY)[0];

        expect($entry->content_text)->toBe('reluctant')
            ->and($entry->correction_text)->toBe('unwilling')
            ->and($entry->correction_html)->toBe('')
            ->and($entry->reason)->toBe('He was reluctant to go.')
            ->and($entry->reason_html)->toBe('He was <em>reluctant</em> to go.');
    });

    it('splits a pasted word and meaning by the same separator rules as the editor', function (): void {
        $session = saveEntrySession(['vocabulary' => [
            ['id' => 'v-0', 'text' => 'reluctant → unwilling'],
        ]]);

        $entry = boxEntries($session, PracticeFeedbackEntry::TYPE_VOCABULARY)[0];

        expect($entry->content_text)->toBe('reluctant')
            ->and($entry->correction_text)->toBe('unwilling');
    });

    it('returns rows with word, meaning and example when the session is opened', function (): void {
        $session = PracticeSession::factory()->for(Topic::factory())->create();
        $question = PracticeQuestion::factory()->for($session)->create(['position' => 1]);

        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => PracticeFeedbackEntry::TYPE_VOCABULARY,
            'content_text' => 'reluctant',
            'correction_text' => 'unwilling',
            'reason' => 'He was reluctant.',
            'reason_html' => '<p>He was reluctant.</p>',
            'is_split' => true,
        ]);

        $rows = $session->toEditorPayload()['questions'][0]['feedback']['vocabulary'];

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['text'])->toBe('reluctant')
            ->and($rows[0]['meaning'])->toBe('unwilling')
            ->and($rows[0]['example_html'])->toBe('<p>He was reluctant.</p>');
    });
});

describe('the phrases table', function (): void {
    it('stores a row across the phrase, meaning and example columns', function (): void {
        $session = saveEntrySession(['phrase' => [
            [
                'id' => 'p-0',
                'text' => 'Could I have the menu, please?',
                'meaning' => 'a polite way to ask',
                'example_html' => '<p>Could I have <em>the menu</em>, please?</p>',
            ],
        ]]);

        $entry = boxEntries($session, PracticeFeedbackEntry::TYPE_PHRASE)[0];

        expect($entry->content_text)->toBe('Could I have the menu, please?')
            ->and($entry->correction_text)->toBe('a polite way to ask')
            ->and($entry->correction_html)->toBe('')
            ->and($entry->reason)->toBe('Could I have the menu, please?')
            ->and($entry->reason_html)->toBe('Could I have <em>the menu</em>, please?');
    });

    it('splits a pasted phrase and meaning by the same separator rules as the editor', function (): void {
        $session = saveEntrySession(['phrase' => [
            ['id' => 'p-0', 'text' => 'off the beaten track → away from the usual places'],
        ]]);

        $entry = boxEntries($session, PracticeFeedbackEntry::TYPE_PHRASE)[0];

        expect($entry->content_text)->toBe('off the beaten track')
            ->and($entry->correction_text)->toBe('away from the usual places');
    });

    it('keeps a phrase with commas and a question mark in one column', function (): void {
        $session = saveEntrySession(['phrase' => [
            ['id' => 'p-0', 'text' => 'Excuse me, could I have the menu, please?'],
        ]]);

        $entry = boxEntries($session, PracticeFeedbackEntry::TYPE_PHRASE)[0];

        expect($entry->content_text)->toBe('Excuse me, could I have the menu, please?')
            ->and($entry->correction_text)->toBe('')
            ->and($entry->is_split)->toBeTrue();
    });

    it('returns rows with phrase, meaning and example when the session is opened', function (): void {
        $session = PracticeSession::factory()->for(Topic::factory())->create();
        $question = PracticeQuestion::factory()->for($session)->create(['position' => 1]);

        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => PracticeFeedbackEntry::TYPE_PHRASE,
            'content_text' => 'Could I have the bill, please?',
            'correction_text' => 'a polite way to ask for the bill',
            'reason' => 'Could I have the bill, please?',
            'reason_html' => '<em>Could I have the bill</em>, please?',
            'is_split' => true,
        ]);

        $rows = $session->toEditorPayload()['questions'][0]['feedback']['phrase'];

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['text'])->toBe('Could I have the bill, please?')
            ->and($rows[0]['meaning'])->toBe('a polite way to ask for the bill')
            ->and($rows[0]['example_html'])->toBe('<em>Could I have the bill</em>, please?');
    });

    it('splits a phrase saved before the columns existed into its columns', function (): void {
        $session = PracticeSession::factory()->for(Topic::factory())->create();
        $question = PracticeQuestion::factory()->for($session)->create(['position' => 1]);

        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => PracticeFeedbackEntry::TYPE_PHRASE,
            'content_html' => '',
            'content_text' => "off the beaten track - away from the usual places\na long way",
        ]);

        $rows = $session->toEditorPayload()['questions'][0]['feedback']['phrase'];

        expect($rows)->toHaveCount(2)
            ->and($rows[0]['text'])->toBe('off the beaten track')
            ->and($rows[0]['meaning'])->toBe('away from the usual places')
            ->and($rows[1]['text'])->toBe('a long way')
            ->and($rows[1]['meaning'])->toBe('')
            ->and(PracticeFeedbackEntry::count())->toBe(2);
    });
});

describe('the limits a box can hold', function (): void {
    it('refuses a pasted list that would store more than two hundred entries', function (): void {
        $texts = array_map(fn (int $index): string => "entry {$index}", range(1, 200));

        $response = $this->actingAs(User::factory()->create())->post(route('sessions.store'), [
            'topic' => 'Travel',
            'questions' => [
                entryQuestionPayload('<p>Where did you go?</p>', [
                    'mistake' => textEntries([implode("\n", [...$texts, 'one too many'])], 'paste'),
                ]),
            ],
        ]);

        $response->assertSessionHasErrors('questions.0.feedback.mistake');

        expect(session('errors')->first('questions.0.feedback.mistake'))
            ->toBe('A box can hold up to 200 entries. Remove one to add another.');

        expect(PracticeSession::count())->toBe(0);
    });

    it('refuses an entry longer than a thousand characters', function (): void {
        $response = $this->actingAs(User::factory()->create())->post(route('sessions.store'), [
            'topic' => 'Travel',
            'questions' => [
                entryQuestionPayload('<p>Where did you go?</p>', [
                    'mistake' => textEntries([str_repeat('a', 1001)], 'paste'),
                ]),
            ],
        ]);

        $response->assertSessionHasErrors('questions.0.feedback.mistake');

        expect(session('errors')->first('questions.0.feedback.mistake'))
            ->toBe('Each entry can be up to 1000 characters. Split it into two entries.');

        expect(PracticeSession::count())->toBe(0);
    });

    it('accepts a box holding exactly two hundred entries', function (): void {
        $texts = array_map(fn (int $index): string => "entry {$index}", range(1, 200));

        saveEntrySession(['mistake' => textEntries($texts)]);

        expect(PracticeFeedbackEntry::count())->toBe(200);
    });
});

describe('sessions saved before entries existed', function (): void {
    it('splits a saved multi line box into entries when the session is opened', function (): void {
        $session = PracticeSession::factory()->for(Topic::factory())->create();
        $question = PracticeQuestion::factory()->for($session)->create(['position' => 1]);

        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => PracticeFeedbackEntry::TYPE_MISTAKE,
            'content_html' => '',
            'content_text' => "I go → I went\nsaid → told",
        ]);

        $payload = $session->toEditorPayload();

        $entries = $payload['questions'][0]['feedback']['mistake'];

        expect($entries)->toHaveCount(2)
            ->and($entries[0]['text'])->toBe('I go')
            ->and($entries[0]['correction_html'])->toBe('I went')
            ->and($entries[1]['text'])->toBe('said')
            ->and($entries[1]['correction_html'])->toBe('told')
            ->and($entries[0]['id'])->toBeString()->not->toBe('')
            ->and($entries[1]['id'])->toBeString()->not->toBe('')
            ->and($entries[1]['id'])->not->toBe($entries[0]['id']);

        expect(PracticeFeedbackEntry::count())->toBe(2);
    });

    it('leaves a grammar note and the natural version untouched', function (): void {
        $session = PracticeSession::factory()->for(Topic::factory())->create();
        $question = PracticeQuestion::factory()->for($session)->create(['position' => 1]);

        PracticeFeedbackEntry::factory()->for($question)->rich()->create();
        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => PracticeFeedbackEntry::TYPE_NATURAL_VERSION,
            'content_html' => '<p>Where did you <em>end up</em> going?</p>',
            'content_text' => 'Where did you end up going?',
        ]);

        $payload = $session->toEditorPayload();

        expect($payload['questions'][0]['feedback']['grammar_note'])->toHaveCount(1)
            ->and($payload['questions'][0]['feedback']['grammar_note'][0]['html'])
            ->toBe('<p>Use the <strong>past tense</strong> here.</p>')
            ->and($payload['questions'][0]['feedback']['natural_version'])
            ->toBe('<p>Where did you <em>end up</em> going?</p>')
            ->and(PracticeFeedbackEntry::count())->toBe(2);
    });

    it('changes nothing when the session is opened again', function (): void {
        $session = PracticeSession::factory()->for(Topic::factory())->create();
        $question = PracticeQuestion::factory()->for($session)->create(['position' => 1]);

        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => PracticeFeedbackEntry::TYPE_PHRASE,
            'content_html' => '',
            'content_text' => "a long way\noff",
        ]);

        $session->toEditorPayload();
        $first = PracticeFeedbackEntry::query()->pluck('id', 'content_text');

        $session->refresh()->toEditorPayload();
        $second = PracticeFeedbackEntry::query()->pluck('id', 'content_text');

        expect($second->all())->toBe($first->all())
            ->and(PracticeFeedbackEntry::count())->toBe(2);
    });

    it('is converted by the maintenance command', function (): void {
        $session = PracticeSession::factory()->for(Topic::factory())->create();
        $question = PracticeQuestion::factory()->for($session)->create(['position' => 1]);

        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => PracticeFeedbackEntry::TYPE_VOCABULARY,
            'content_html' => '',
            'content_text' => "journey\nreluctant",
        ]);

        $this->artisan('practice:split-legacy-feedback')->assertSuccessful();

        expect(PracticeFeedbackEntry::count())->toBe(2)
            ->and(PracticeFeedbackEntry::query()->pluck('position')->all())->toBe([0, 1]);

        $this->artisan('practice:split-legacy-feedback')->assertSuccessful();

        expect(PracticeFeedbackEntry::count())->toBe(2);
    });
});
