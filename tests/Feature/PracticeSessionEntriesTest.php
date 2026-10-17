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
            'mistake' => textEntries(['I go → I went', 'said → told', 'a lot → a lot of']),
        ]);

        $entries = boxEntries($session, PracticeFeedbackEntry::TYPE_MISTAKE);

        expect($entries)->toHaveCount(3)
            ->and(array_column($entries, 'content_text'))->toBe(['I go → I went', 'said → told', 'a lot → a lot of'])
            ->and(array_column($entries, 'position'))->toBe([0, 1, 2]);
    });

    it('splits the lines inside one submitted entry and keeps the first in place', function (): void {
        $session = saveEntrySession([
            'mistake' => textEntries(["I go → I went\nsaid → told\na lot → a lot of"], 'paste'),
        ]);

        $entries = boxEntries($session, PracticeFeedbackEntry::TYPE_MISTAKE);

        expect($entries)->toHaveCount(3)
            ->and(array_column($entries, 'content_text'))->toBe(['I go → I went', 'said → told', 'a lot → a lot of'])
            ->and($entries[0]->client_id)->toBe('paste-0')
            ->and($entries[1]->client_id)->not->toBe('')
            ->and($entries[1]->client_id)->not->toBe($entries[0]->client_id);
    });

    it('applies the cleaning rules when it stores pasted lines', function (): void {
        $session = saveEntrySession([
            'vocabulary' => textEntries(["•\treluctant\n1. 3 days\n- reluctant — unwilling\n\n   "], 'paste'),
        ]);

        $entries = boxEntries($session, PracticeFeedbackEntry::TYPE_VOCABULARY);

        expect(array_column($entries, 'content_text'))->toBe(['reluctant', '3 days', 'reluctant — unwilling']);
    });

    it('keeps a very long line as one entry instead of cutting it', function (): void {
        $long = str_repeat('a', 900);

        $session = saveEntrySession(['phrase' => textEntries([$long], 'paste')]);

        expect(array_column(boxEntries($session, PracticeFeedbackEntry::TYPE_PHRASE), 'content_text'))->toBe([$long]);
    });
});

describe('entries keep their identity between saves', function (): void {
    it('updates the same entry when it is edited and does not add another', function (): void {
        $session = saveEntrySession(['mistake' => textEntries(['I go → I went', 'said → told'])]);

        $this->actingAs(User::factory()->create())->put(route('sessions.update', $session), [
            'topic' => 'Travel',
            'questions' => [
                entryQuestionPayload('<p>Where did you go?</p>', [
                    'mistake' => textEntries(['I went', 'said → told']),
                ]),
            ],
        ])->assertRedirect();

        $entries = boxEntries($session->fresh() ?? $session, PracticeFeedbackEntry::TYPE_MISTAKE);

        expect($entries)->toHaveCount(2)
            ->and(array_column($entries, 'content_text'))->toBe(['I went', 'said → told']);
    });

    it('does not duplicate entries when the same save is retried', function (): void {
        $session = saveEntrySession(['mistake' => textEntries(['I go → I went', 'said → told'])]);

        $payload = [
            'topic' => 'Travel',
            'questions' => [
                entryQuestionPayload('<p>Where did you go?</p>', [
                    'mistake' => textEntries(['I go → I went', 'said → told']),
                ]),
            ],
        ];

        $this->actingAs(User::factory()->create())->put(route('sessions.update', $session), $payload);
        $this->actingAs(User::factory()->create())->put(route('sessions.update', $session), $payload);

        expect(PracticeFeedbackEntry::count())->toBe(2);
    });

    it('removes only the entry that was removed in the editor', function (): void {
        $session = saveEntrySession(['mistake' => textEntries(['first', 'second', 'third'])]);

        $this->actingAs(User::factory()->create())->put(route('sessions.update', $session), [
            'topic' => 'Travel',
            'questions' => [
                entryQuestionPayload('<p>Where did you go?</p>', [
                    'mistake' => textEntries(['first', 'third']),
                ]),
            ],
        ]);

        expect(array_column(boxEntries($session, PracticeFeedbackEntry::TYPE_MISTAKE), 'content_text'))->toBe(['first', 'third']);
    });

    it('keeps the entries of one box apart from the entries of another', function (): void {
        $session = saveEntrySession([
            'mistake' => textEntries(['same text'], 'a'),
            'vocabulary' => textEntries(['same text'], 'a'),
        ]);

        expect(PracticeFeedbackEntry::count())->toBe(2);
    });

    it('stamps a server id on entries that arrive without one', function (): void {
        $session = saveEntrySession([
            'mistake' => [['text' => 'I go → I went']],
        ]);

        $entry = boxEntries($session, PracticeFeedbackEntry::TYPE_MISTAKE)[0];

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
            ->and($entries[0]['text'])->toBe('I go → I went')
            ->and($entries[1]['text'])->toBe('said → told')
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
