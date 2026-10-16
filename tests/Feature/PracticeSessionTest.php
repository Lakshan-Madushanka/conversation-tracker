<?php

declare(strict_types=1);

use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use Inertia\Testing\AssertableInertia as Assert;

describe('starting a session', function (): void {
    it('renders the new session page with existing topics to pick from', function (): void {
        Topic::factory()->create(['name' => 'Travel']);

        $this->get(route('sessions.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('sessions/Create')
                ->where('session', null)
                ->has('topics', 1)
                ->where('topics.0.name', 'Travel'));
    });
});

describe('saving a session', function (): void {
    it('creates a session with a new topic and opens it for editing', function (): void {
        $response = $this->post(route('sessions.store'), [
            'topic' => 'Ordering at a restaurant',
            'questions' => [
                [
                    'question_html' => '<p>What would you like to start with?</p>',
                    'answer_html' => '<p>I would like the soup, please.</p>',
                ],
            ],
        ]);

        $session = PracticeSession::sole();

        $response->assertRedirect(route('sessions.edit', $session));

        expect($session->status)->toBe('unfinished')
            ->and($session->topic->name)->toBe('Ordering at a restaurant');

        $question = PracticeQuestion::sole();

        expect($question->position)->toBe(1)
            ->and($question->question_html)->toBe('<p>What would you like to start with?</p>')
            ->and($question->question_text)->toBe('What would you like to start with?')
            ->and($question->answer_html)->toBe('<p>I would like the soup, please.</p>')
            ->and($question->answer_text)->toBe('I would like the soup, please.');
    });

    it('reuses a topic that differs only in capitalisation or spacing', function (string $spelling): void {
        Topic::factory()->create(['name' => 'Travel']);

        $this->post(route('sessions.store'), [
            'topic' => $spelling,
            'questions' => [[]],
        ])->assertRedirect();

        expect(Topic::count())->toBe(1)
            ->and(Topic::sole()->name)->toBe('Travel')
            ->and(PracticeSession::sole()->topic->name)->toBe('Travel');
    })->with([
        'lower case' => ['travel'],
        'upper case' => ['TRAVEL'],
        'surrounding spaces' => ['  Travel  '],
        'mixed case with surrounding whitespace' => ["\ttravel\n"],
    ]);

    it('reuses a topic when only inner spaces differ', function (): void {
        Topic::factory()->create(['name' => 'Travel plans']);

        $this->post(route('sessions.store'), [
            'topic' => 'Travel  plans',
            'questions' => [[]],
        ])->assertRedirect();

        expect(Topic::count())->toBe(1)
            ->and(Topic::sole()->name)->toBe('Travel plans');
    });

    it('rejects saving without a topic and explains why', function (): void {
        $this->from(route('sessions.create'))
            ->post(route('sessions.store'), [
                'topic' => '   ',
                'questions' => [[]],
            ])
            ->assertSessionHasErrors([
                'topic' => 'A session needs a topic so you can find it later.',
            ]);

        expect(PracticeSession::count())->toBe(0);
    });

    it('saves an empty question and answer when a topic is present', function (): void {
        $this->post(route('sessions.store'), [
            'topic' => 'Travel',
            'questions' => [[]],
        ])->assertRedirect();

        $question = PracticeQuestion::sole();

        expect($question->question_html)->toBe('')
            ->and($question->question_text)->toBe('')
            ->and($question->answer_html)->toBe('')
            ->and($question->answer_text)->toBe('');
    });

    it('treats a question that is only blank space as empty', function (): void {
        $this->post(route('sessions.store'), [
            'topic' => 'Travel',
            'questions' => [
                [
                    'question_html' => '<p>   <br></p>',
                    'answer_html' => '<p></p>',
                ],
            ],
        ])->assertRedirect();

        $question = PracticeQuestion::sole();

        expect($question->question_html)->toBe('')
            ->and($question->question_text)->toBe('')
            ->and($question->answer_html)->toBe('')
            ->and($question->answer_text)->toBe('');
    });

    it('removes unsafe content from the question and answer before saving', function (): void {
        $unsafe = '<p onclick="steal()">Hello <script>alert(1)</script>'
            .'<img src="x.png" onerror="alert(1)">'
            .'<iframe src="https://evil.example"></iframe>'
            .'<strong>world</strong></p>';

        $this->post(route('sessions.store'), [
            'topic' => 'Travel',
            'questions' => [
                [
                    'question_html' => $unsafe,
                    'answer_html' => '<p style="color: red">My <em>answer</em></p>',
                ],
            ],
        ])->assertRedirect();

        expect(PracticeQuestion::sole()->question_html)
            ->toBe('<p>Hello <strong>world</strong></p>')
            ->and(PracticeQuestion::sole()->answer_html)
            ->toBe('<p>My <em>answer</em></p>');
    });

    it('rejects a topic longer than the limit', function (): void {
        $this->post(route('sessions.store'), [
            'topic' => str_repeat('a', 121),
            'questions' => [[]],
        ])->assertSessionHasErrors('topic');
    });

    it('rejects a question longer than the limit', function (): void {
        $this->post(route('sessions.store'), [
            'topic' => 'Travel',
            'questions' => [
                ['question_html' => '<p>'.str_repeat('a', 100_000).'</p>'],
            ],
        ])->assertSessionHasErrors('questions.0.question_html');
    });
});

describe('opening a saved session', function (): void {
    it('loads the saved topic, question and answer into the editor', function (): void {
        $topic = Topic::factory()->create(['name' => 'Travel']);
        $session = PracticeSession::factory()->for($topic)->create();

        PracticeQuestion::factory()->for($session)->create([
            'question_html' => '<p><strong>Bold</strong> question</p>',
            'question_text' => 'Bold question',
            'answer_html' => '<p>Answer</p>',
            'answer_text' => 'Answer',
        ]);

        $this->get(route('sessions.edit', $session))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('sessions/Create')
                ->where('session.topic.name', 'Travel')
                ->where('session.questions.0.question_html', '<p><strong>Bold</strong> question</p>')
                ->where('session.questions.0.answer_html', '<p>Answer</p>')
                ->has('topics', 1));
    });
});

describe('updating a session', function (): void {
    it('updates the saved session without creating a duplicate', function (): void {
        $topic = Topic::factory()->create(['name' => 'Travel']);
        $session = PracticeSession::factory()->for($topic)->create();

        PracticeQuestion::factory()->for($session)->create([
            'question_html' => '<p>Old</p>',
            'question_text' => 'Old',
        ]);

        $this->put(route('sessions.update', $session), [
            'topic' => 'Job interview',
            'questions' => [
                [
                    'question_html' => '<p>New <strong>one</strong></p>',
                    'answer_html' => '<p>My answer</p>',
                ],
            ],
        ])->assertRedirect(route('sessions.edit', $session));

        $question = PracticeQuestion::sole();

        expect(PracticeSession::count())->toBe(1)
            ->and(PracticeQuestion::count())->toBe(1)
            ->and($session->fresh()?->topic->name)->toBe('Job interview')
            ->and($question->question_html)->toBe('<p>New <strong>one</strong></p>')
            ->and($question->question_text)->toBe('New one')
            ->and($question->answer_text)->toBe('My answer');
    });

    it('rejects an update without a topic and keeps the saved session unchanged', function (): void {
        $topic = Topic::factory()->create(['name' => 'Travel']);
        $session = PracticeSession::factory()->for($topic)->create();

        PracticeQuestion::factory()->for($session)->create([
            'question_html' => '<p>Original</p>',
            'question_text' => 'Original',
        ]);

        $this->put(route('sessions.update', $session), [
            'topic' => '',
            'questions' => [[]],
        ])->assertSessionHasErrors('topic');

        expect($session->fresh()?->topic->name)->toBe('Travel')
            ->and(PracticeQuestion::sole()->question_html)->toBe('<p>Original</p>');
    });
});
