<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * One entry of the given type inside a fresh session, so every entry owns its
 * own topic and the collection sees it as a separate origin.
 *
 * @param  array<string, mixed>  $attributes
 */
function collectionEntry(FeedbackType $type, string $content, array $attributes = []): PracticeFeedbackEntry
{
    $topic = Topic::factory()->create();
    $session = PracticeSession::factory()->finished()->for($topic)->create();
    $question = PracticeQuestion::factory()->for($session)->create();

    return PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => $type,
        'content_text' => $content,
        'is_split' => true,
    ] + $attributes);
}

it('lists each feedback type under its own tab, newest first', function (): void {
    collectionEntry(FeedbackType::Vocabulary, 'older word', ['created_at' => now()->subDays(2)]);
    collectionEntry(FeedbackType::Vocabulary, 'newer word', ['created_at' => now()->subDay()]);
    collectionEntry(FeedbackType::Phrase, 'catch up');
    collectionEntry(FeedbackType::GrammarNote, 'Use the past tense.');
    collectionEntry(FeedbackType::Mistake, 'I go');

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('collections/Index')
            ->count('vocabulary.data', 2)
            ->where('vocabulary.data.0.content_text', 'newer word')
            ->where('vocabulary.data.1.content_text', 'older word')
            ->count('phrases.data', 1)
            ->where('phrases.data.0.type', FeedbackType::Phrase->value)
            ->count('grammar.data', 1)
            ->where('grammar.data.0.type', FeedbackType::GrammarNote->value)
            ->count('mistakes.data', 1)
            ->where('mistakes.data.0.type', FeedbackType::Mistake->value));
});

it('paginates every tab under its own page name', function (): void {
    foreach (range(1, 5) as $index) {
        collectionEntry(FeedbackType::Vocabulary, "word {$index}");
        collectionEntry(FeedbackType::Phrase, "phrase {$index}");
        collectionEntry(FeedbackType::GrammarNote, "note {$index}");
        collectionEntry(FeedbackType::Mistake, "mistake {$index}");
    }

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('collections/Index')
            ->where('vocabulary.total', 5)
            ->where('vocabulary.per_page', 4)
            ->where('vocabulary.next_page_url', url('/collections?vocabulary=2'))
            ->where('phrases.next_page_url', url('/collections?phrase=2'))
            ->where('grammar.next_page_url', url('/collections?grammar_note=2'))
            ->where('mistakes.next_page_url', url('/collections?mistake=2')));
});

it('filters only the tab the search belongs to', function (): void {
    collectionEntry(FeedbackType::Vocabulary, 'soup');
    collectionEntry(FeedbackType::Vocabulary, 'relish');
    collectionEntry(FeedbackType::Phrase, 'soup of the day');
    collectionEntry(FeedbackType::Phrase, 'catch up');

    $this->get(route('collections.index', ['search' => 'soup', 'forPage' => 'vocabulary']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('collections/Index')
            ->where('vocabulary.total', 1)
            ->where('vocabulary.data.0.content_text', 'soup')
            ->where('phrases.total', 2));
});

it('treats like wildcards in the search term as plain characters', function (): void {
    collectionEntry(FeedbackType::Vocabulary, 'soup');
    collectionEntry(FeedbackType::Vocabulary, 'relish');
    collectionEntry(FeedbackType::Vocabulary, '100% sure');

    $this->get(route('collections.index', ['search' => '%', 'forPage' => 'vocabulary']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('collections/Index')
            ->where('vocabulary.total', 1)
            ->where('vocabulary.data.0.content_text', '100% sure'));
});

it('treats an underscore in the search term as a plain character', function (): void {
    collectionEntry(FeedbackType::Vocabulary, 'cat');
    collectionEntry(FeedbackType::Vocabulary, 'car');
    collectionEntry(FeedbackType::Vocabulary, 'c_t');

    $this->get(route('collections.index', ['search' => '_', 'forPage' => 'vocabulary']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('collections/Index')
            ->where('vocabulary.total', 1)
            ->where('vocabulary.data.0.content_text', 'c_t'));
});

it('finds a word by what it means', function (): void {
    collectionEntry(FeedbackType::Vocabulary, 'soup', ['correction_text' => 'a liquid food']);

    $this->get(route('collections.index', ['search' => 'liquid', 'forPage' => 'vocabulary']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('collections/Index')
            ->where('vocabulary.total', 1)
            ->where('vocabulary.data.0.content_text', 'soup'));
});

it('finds a phrase by its example', function (): void {
    collectionEntry(FeedbackType::Phrase, 'of course', ['example' => 'Of course, right away.']);

    $this->get(route('collections.index', ['search' => 'right away', 'forPage' => 'phrases']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('collections/Index')
            ->where('phrases.total', 1)
            ->where('phrases.data.0.content_text', 'of course'));
});

it('never lists an entry that holds nothing', function (): void {
    collectionEntry(FeedbackType::Vocabulary, 'soup');

    $question = PracticeQuestion::factory()
        ->for(PracticeSession::factory()->finished())
        ->create();

    PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => FeedbackType::Vocabulary,
        'content_text' => '',
        'content_html' => '',
        'is_split' => true,
    ]);

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('collections/Index')
            ->where('vocabulary.total', 1)
            ->where('vocabulary.data.0.content_text', 'soup'));
});

it('leaves the entries of an unfinished session out', function (): void {
    $topic = Topic::factory()->create();
    $draft = PracticeSession::factory()->for($topic)->create([
        'status' => PracticeSession::STATUS_UNFINISHED,
    ]);
    $question = PracticeQuestion::factory()->for($draft)->create();

    PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => FeedbackType::Vocabulary,
        'content_text' => 'draft word',
        'is_split' => true,
    ]);

    collectionEntry(FeedbackType::Vocabulary, 'finished word');

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('collections/Index')
            ->where('vocabulary.total', 1)
            ->where('vocabulary.data.0.content_text', 'finished word'));
});

it('includes the session and topic every entry came from', function (): void {
    $topic = Topic::factory()->create(['name' => 'Ordering at a restaurant']);
    $session = PracticeSession::factory()->finished()->for($topic)->create();
    $question = PracticeQuestion::factory()->for($session)->create();

    PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => FeedbackType::Vocabulary,
        'content_text' => 'relish',
        'is_split' => true,
    ]);

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('collections/Index')
            ->where('vocabulary.data.0.practice_question.practice_session.id', $session->id)
            ->where('vocabulary.data.0.practice_question.practice_session.topic.id', $topic->id)
            ->where('vocabulary.data.0.practice_question.practice_session.topic.name', 'Ordering at a restaurant'));
});
