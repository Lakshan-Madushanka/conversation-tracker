<?php

declare(strict_types=1);

use App\Actions\PracticeQuestions\SavePracticeFeedback;
use App\Enums\FeedbackType;
use App\Http\Resources\SessionEditorResource;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\User;

/**
 * Build one question payload for the session request.
 *
 * @param  array<string, string>  $feedback
 * @return array{question_html: string, answer_html: string, feedback: array<string, string>}
 */
function questionPayload(string $question, array $feedback = []): array
{
    return [
        'question_html' => $question,
        'answer_html' => '',
        'feedback' => $feedback,
    ];
}

test('a session can contain ten ordered questions', function (): void {
    $user = User::factory()->create();

    $questions = [];

    for ($index = 1; $index <= 10; $index++) {
        $questions[] = questionPayload("<p>Question number {$index}</p>");
    }

    $this->actingAs($user)->post(route('sessions.store'), [
        'topic' => 'Travel',
        'questions' => $questions,
    ])->assertRedirect();

    $session = PracticeSession::firstOrFail();

    expect($session->questions)->toHaveCount(10);

    $position = 1;

    foreach ($session->questions as $question) {
        expect($question->position)->toBe($position)
            ->and($question->question_text)->toBe("Question number {$position}");

        $position++;
    }
});

test('all five feedback boxes can be saved for one question', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('sessions.store'), [
        'topic' => 'Travel',
        'questions' => [
            questionPayload('<p>Where did you go?</p>', [
                'mistake' => 'I go → I went',
                'vocabulary' => 'journey',
                'phrase' => 'a long way',
                'grammar_note' => '<p>Use the <strong>past tense</strong>.</p>',
                'natural_version' => '<p>Where did you <em>end up</em> going?</p>',
            ]),
        ],
    ])->assertRedirect();

    $question = PracticeQuestion::firstOrFail();

    expect($question->feedbackEntries)->toHaveCount(5);

    $entries = $question->feedbackEntries->keyBy(fn (PracticeFeedbackEntry $entry): string => $entry->type->value);

    expect($entries[FeedbackType::Mistake->value]->content_text)->toBe('I go')
        ->and($entries[FeedbackType::Mistake->value]->correction_text)->toBe('I went')
        ->and($entries[FeedbackType::Vocabulary->value]->content_text)->toBe('journey')
        ->and($entries[FeedbackType::Phrase->value]->content_text)->toBe('a long way')
        ->and($entries[FeedbackType::GrammarNote->value]->content_html)->toBe('<p>Use the <strong>past tense</strong>.</p>')
        ->and($entries[FeedbackType::NaturalVersion->value]->content_html)->toBe('<p>Where did you <em>end up</em> going?</p>');
});

test('the editor payload provides every saved box keyed by type', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('sessions.store'), [
        'topic' => 'Travel',
        'questions' => [
            questionPayload('<p>Where did you go?</p>', [
                'mistake' => 'I go → I went',
                'grammar_note' => '<p>Use the <strong>past tense</strong>.</p>',
            ]),
        ],
    ]);

    $session = PracticeSession::firstOrFail();
    $payload = SessionEditorResource::make($session)->resolve();

    $feedback = $payload['questions'][0]['feedback'];

    expect($feedback)->toHaveKeys(['mistake', 'grammar_note'])
        ->and($feedback['mistake'])->toHaveCount(1)
        ->and($feedback['mistake'][0]['text'])->toBe('I go')
        ->and($feedback['mistake'][0]['correction_html'])->toBe('I went')
        ->and($feedback['mistake'][0]['id'])->toBeString()->not->toBe('')
        ->and($feedback['grammar_note'][0]['html'])->toBe('<p>Use the <strong>past tense</strong>.</p>')
        ->and($feedback['grammar_note'][0]['id'])->toBeString()->not->toBe('');
});

test('empty feedback boxes do not create entries', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('sessions.store'), [
        'topic' => 'Travel',
        'questions' => [
            questionPayload('<p>Where did you go?</p>', [
                'mistake' => '',
                'vocabulary' => '',
                'phrase' => '',
                'grammar_note' => '',
                'natural_version' => '',
            ]),
        ],
    ])->assertRedirect();

    expect(PracticeFeedbackEntry::count())->toBe(0);
});

test('a question whose boxes are all empty saves with no feedback entries', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('sessions.store'), [
        'topic' => 'Travel',
        'questions' => [
            questionPayload('<p>Where did you go?</p>'),
        ],
    ])->assertRedirect();

    expect(PracticeQuestion::count())->toBe(1)
        ->and(PracticeFeedbackEntry::count())->toBe(0);
});

test('blank markup and whitespace-only text count as empty', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('sessions.store'), [
        'topic' => 'Travel',
        'questions' => [
            questionPayload('<p>Where did you go?</p>', [
                'mistake' => '   ',
                'vocabulary' => "\n\n",
                'phrase' => '   ',
                'grammar_note' => '<p></p><p>   </p>',
                'natural_version' => '<br>',
            ]),
        ],
    ])->assertRedirect();

    expect(PracticeFeedbackEntry::count())->toBe(0);
});

test('plain feedback keeps its text and does not store markup', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('sessions.store'), [
        'topic' => 'Travel',
        'questions' => [
            questionPayload('<p>Where did you go?</p>', [
                'mistake' => '<b>said</b> → told',
                'vocabulary' => 'journey',
                'phrase' => "a long way\noff",
            ]),
        ],
    ]);

    $entries = PracticeFeedbackEntry::all()->keyBy(fn (PracticeFeedbackEntry $entry): string => $entry->type->value);

    $phrases = PracticeFeedbackEntry::query()
        ->where('type', FeedbackType::Phrase->value)
        ->orderBy('position')
        ->get();

    expect($entries[FeedbackType::Mistake->value]->content_html)->toBe('')
        ->and($entries[FeedbackType::Mistake->value]->content_text)->toBe('<b>said</b>')
        ->and($entries[FeedbackType::Mistake->value]->correction_text)->toBe('told')
        ->and($entries[FeedbackType::Vocabulary->value]->content_html)->toBe('')
        ->and(array_column($phrases->all(), 'content_text'))->toBe(['a long way', 'off'])
        ->and(array_column($phrases->all(), 'content_html'))->toBe(['', '']);
});

test('unsafe markup is stripped from rich feedback', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('sessions.store'), [
        'topic' => 'Travel',
        'questions' => [
            questionPayload('<p>Where did you go?</p>', [
                'grammar_note' => '<p>Safe</p><script>alert(1)</script><img src=x onerror=alert(1)>',
                'natural_version' => '<p onclick="alert(1)">No <strong>handlers</strong></p>',
            ]),
        ],
    ]);

    $entries = PracticeFeedbackEntry::all()->keyBy(fn (PracticeFeedbackEntry $entry): string => $entry->type->value);

    expect($entries[FeedbackType::GrammarNote->value]->content_html)->toBe('<p>Safe</p>')
        ->and($entries[FeedbackType::GrammarNote->value]->content_text)->toBe('Safe')
        ->and($entries[FeedbackType::NaturalVersion->value]->content_html)->toBe('<p>No <strong>handlers</strong></p>');
});

test('removing one of several questions removes only that question and its feedback', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('sessions.store'), [
        'topic' => 'Travel',
        'questions' => [
            questionPayload('<p>First</p>', ['mistake' => 'first mistake']),
            questionPayload('<p>Second</p>', ['mistake' => 'second mistake']),
            questionPayload('<p>Third</p>', ['mistake' => 'third mistake']),
        ],
    ]);

    $session = PracticeSession::firstOrFail();

    $this->actingAs($user)->put(route('sessions.update', $session), [
        'topic' => 'Travel',
        'questions' => [
            questionPayload('<p>First</p>', ['mistake' => 'first mistake']),
            questionPayload('<p>Third</p>', ['mistake' => 'third mistake']),
        ],
    ])->assertRedirect(route('sessions.edit', $session));

    $session->refresh()->load('questions.feedbackEntries');

    expect($session->questions)->toHaveCount(2)
        ->and($session->questions->pluck('question_text')->all())->toBe(['First', 'Third'])
        ->and($session->questions->pluck('position')->all())->toBe([1, 2]);

    $mistakes = $session->questions
        ->flatMap(fn (PracticeQuestion $question) => $question->feedbackEntries)
        ->pluck('content_text')
        ->all();

    expect($mistakes)->toBe(['first mistake', 'third mistake']);
});

test('saving the same session twice does not duplicate questions or feedback', function (): void {
    $user = User::factory()->create();

    $payload = [
        'topic' => 'Travel',
        'questions' => [
            questionPayload('<p>Where did you go?</p>', ['mistake' => 'I go → I went']),
        ],
    ];

    $this->actingAs($user)->post(route('sessions.store'), $payload);

    $session = PracticeSession::firstOrFail();

    $this->actingAs($user)->put(route('sessions.update', $session), $payload)
        ->assertRedirect(route('sessions.edit', $session));

    $this->actingAs($user)->put(route('sessions.update', $session), $payload)
        ->assertRedirect(route('sessions.edit', $session));

    expect(PracticeQuestion::count())->toBe(1)
        ->and(PracticeFeedbackEntry::count())->toBe(1);
});

test('a feedback box can be emptied again on a later save', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('sessions.store'), [
        'topic' => 'Travel',
        'questions' => [
            questionPayload('<p>Where did you go?</p>', ['mistake' => 'I go → I went']),
        ],
    ]);

    $session = PracticeSession::firstOrFail();

    $this->actingAs($user)->put(route('sessions.update', $session), [
        'topic' => 'Travel',
        'questions' => [
            questionPayload('<p>Where did you go?</p>', ['mistake' => '']),
        ],
    ]);

    expect(PracticeFeedbackEntry::count())->toBe(0);
});

test('twenty questions can be saved in one request', function (): void {
    $user = User::factory()->create();

    $questions = [];

    for ($index = 1; $index <= 20; $index++) {
        $questions[] = questionPayload("<p>Question {$index}</p>", ['mistake' => "Mistake {$index}"]);
    }

    $this->actingAs($user)->post(route('sessions.store'), [
        'topic' => 'Travel',
        'questions' => $questions,
    ])->assertRedirect();

    expect(PracticeQuestion::count())->toBe(20)
        ->and(PracticeFeedbackEntry::count())->toBe(20);
});

test('a session cannot hold more than fifty questions', function (): void {
    $user = User::factory()->create();

    $questions = [];

    for ($index = 1; $index <= 51; $index++) {
        $questions[] = questionPayload("<p>Question {$index}</p>");
    }

    $response = $this->actingAs($user)->post(route('sessions.store'), [
        'topic' => 'Travel',
        'questions' => $questions,
    ]);

    $response->assertSessionHasErrors('questions');
    expect(session('errors')->first('questions'))->toBe('A session can hold up to 50 questions. Remove one to add another.');
    expect(PracticeSession::count())->toBe(0);
});

test('a session can hold exactly fifty questions', function (): void {
    $user = User::factory()->create();

    $questions = [];

    for ($index = 1; $index <= 50; $index++) {
        $questions[] = questionPayload("<p>Question {$index}</p>");
    }

    $this->actingAs($user)->post(route('sessions.store'), [
        'topic' => 'Travel',
        'questions' => $questions,
    ])->assertRedirect();

    expect(PracticeQuestion::count())->toBe(50);
});

test('feedback content must be a string when provided', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('sessions.store'), [
        'topic' => 'Travel',
        'questions' => [
            questionPayload('<p>Where did you go?</p>', ['mistake' => ['not', 'a', 'string']]),
        ],
    ]);

    $response->assertSessionHasErrors('questions.0.feedback.mistake');
});

test('feedback must be an array when provided', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('sessions.store'), [
        'topic' => 'Travel',
        'questions' => [
            [
                'question_html' => '<p>Where did you go?</p>',
                'answer_html' => '',
                'feedback' => 'not an array',
            ],
        ],
    ]);

    $response->assertSessionHasErrors('questions.0.feedback');
});

test('feedback examples can be stored', function (): void {
    $question = PracticeQuestion::factory()->create();

    $action = app(SavePracticeFeedback::class);

    $id = Str::Uuid()->toString();

    $feedback = [
        'vocabulary' => [
            [
                'id' => $id,
                'text' => 'a b',
                'meaning' => 'v',
                'example_html' => [
                    [
                        'id' => '0619d2f8-b17c-406b-8d0a-95749159f880', 'text' => 'c',
                    ],
                ],
            ],
        ],
    ];

    $action->handle($question, $feedback);

    $record = PracticeFeedbackEntry::query()->where(['client_id' => $id])->first();

    expect($record)->not()->toBeNull()
        ->and($record->example)->toBe('c')
        ->and($record->example_html)->not()->toBeEmpty()
        ->and($record->example_html)
        ->toBe([
            [
                'id' => '0619d2f8-b17c-406b-8d0a-95749159f880',
                'text' => 'c',
                'html' => 'c',
            ],
        ]);
});
