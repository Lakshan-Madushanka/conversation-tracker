<?php

declare(strict_types=1);

use App\Actions\Search\BackfillSearchText;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use App\Queries\SessionSearchMatches;
use Illuminate\Support\Facades\DB;

function searchSession(string $topicName = 'Travel plans'): PracticeSession
{
    $topic = Topic::factory()->create(['name' => $topicName]);

    return PracticeSession::factory()->finished()->for($topic)->create([
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
        'last_changed_at' => now()->subDay(),
    ]);
}

function searchQuestion(
    PracticeSession $session,
    int $position,
    string $question = 'Where did you go?',
    string $answer = 'I went to the beach.',
): PracticeQuestion {
    return PracticeQuestion::factory()->for($session)->create([
        'position' => $position,
        'question_html' => '<p>'.$question.'</p>',
        'question_text' => $question,
        'answer_html' => '<p>'.$answer.'</p>',
        'answer_text' => $answer,
    ]);
}

/**
 * @param  array<string, mixed>  $values
 */
function searchEntry(
    PracticeQuestion $question,
    string $type,
    array $values,
    int $position = 1,
): PracticeFeedbackEntry {
    return PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => $type,
        'position' => $position,
        'is_split' => true,
        ...$values,
    ]);
}

/**
 * @param  array<string, mixed>  $query
 * @return array<string, mixed>
 */
function searchResponse(array $query): array
{
    return test()->getJson(route('sessions.index', $query))->assertOk()->json();
}

it('finds text in every part of a session', function (): void {
    $session = searchSession('Travel plans');
    $question = searchQuestion($session, 1, 'Where did you go?', 'I went to the beach.');

    searchEntry($question, 'mistake', [
        'content_text' => 'I go',
        'correction_text' => 'I went',
        'reason' => 'Use the past tense.',
    ]);
    searchEntry($question, 'vocabulary', [
        'content_text' => 'reservation',
        'correction_text' => 'a booking',
        'example' => 'I have a reservation for two.',
    ]);
    searchEntry($question, 'phrase', [
        'content_text' => 'a long way',
        'correction_text' => 'far away',
        'example' => 'The airport is a long way from here.',
    ]);
    searchEntry($question, 'grammar_note', [
        'content_text' => 'Use the past simple for finished actions.',
    ]);
    searchEntry($question, 'natural_version', [
        'content_text' => 'I would love to go to the beach.',
    ]);

    $cases = [
        ['travel', 'topic', null],
        ['where', 'question', null],
        ['beach', 'answer', null],
        ['go', 'entry', 'mistake'],
        ['past tense', 'entry', 'mistake'],
        ['booking', 'entry', 'vocabulary'],
        ['reservation', 'entry', 'vocabulary'],
        ['far away', 'entry', 'phrase'],
        ['airport', 'entry', 'phrase'],
        ['past simple', 'entry', 'grammar_note'],
        ['would love', 'entry', 'natural_version'],
    ];

    foreach ($cases as [$term, $kind, $type]) {
        $response = searchResponse(['q' => $term]);

        expect($response['sessions'])->toHaveCount(1, "search: {$term}");

        $matches = $response['sessions'][0]['matches'];
        $match = collect($matches)->firstWhere('kind', $kind);

        expect($match)->not->toBeNull("search: {$term}")
            ->and($match['type'] ?? null)->toBe($type, "search: {$term}");
    }
});

it('ignores case, accents and extra spaces', function (): void {
    $session = searchSession('Café mornings');
    $question = searchQuestion($session, 1, 'Do you like crème brûlée?', 'I love it.');
    searchEntry($question, 'vocabulary', [
        'content_text' => 'Crème brûlée',
        'correction_text' => 'a French dessert',
    ]);

    foreach (['cafe', 'CAFÉ', '  creme   brulee  ', 'CREME BRULEE'] as $term) {
        $response = searchResponse(['q' => $term]);

        expect($response['sessions'])->toHaveCount(1, "search: {$term}")
            ->and($response['sessions'][0]['matches'])->not->toBeEmpty("search: {$term}");
    }
});

it('matches parts of words', function (): void {
    $session = searchSession('Ordering at a restaurant');
    searchQuestion($session, 1, 'A table for two, please.', 'Of course.');

    $response = searchResponse(['q' => 'restaur']);

    expect($response['sessions'])->toHaveCount(1)
        ->and($response['sessions'][0]['matches'][0]['kind'])->toBe('topic');
});

it('asks for every word in the same item', function (): void {
    $session = searchSession('Travel');
    searchQuestion(
        $session,
        1,
        'Where did you go on holiday?',
        'I went to the beach with my sister.',
    );

    $sameAnswer = searchResponse(['q' => 'went beach']);
    $acrossItems = searchResponse(['q' => 'holiday sister']);

    expect($sameAnswer['sessions'])->toHaveCount(1)
        ->and(collect($sameAnswer['sessions'][0]['matches'])->pluck('kind')->all())
        ->toBe(['answer'])
        ->and($acrossItems['sessions'])->toBe([]);
});

it('searches only the chosen content types', function (): void {
    $session = searchSession('Going out');
    $question = searchQuestion($session, 1, 'Where are you going?', 'I am going home.');
    searchEntry($question, 'mistake', ['content_text' => 'I go']);

    $topicOnly = searchResponse(['q' => 'going', 'types' => 'mistake']);
    $mistake = searchResponse(['q' => 'go', 'types' => 'mistake']);

    expect($topicOnly['sessions'])->toBe([])
        ->and($mistake['sessions'])->toHaveCount(1)
        ->and($mistake['sessions'][0]['matches'])->toHaveCount(1)
        ->and($mistake['sessions'][0]['matches'][0]['type'])->toBe('mistake');
});

it('searches together with the topic filter', function (): void {
    $travel = searchSession('Travel');
    searchQuestion($travel, 1, 'A nice place', 'The sea.');

    $food = searchSession('Food');
    searchQuestion($food, 1, 'A nice meal', 'The soup.');

    $response = searchResponse(['q' => 'nice', 'topics' => $travel->topic_id]);

    expect($response['sessions'])->toHaveCount(1)
        ->and($response['sessions'][0]['id'])->toBe($travel->id);
});

it('ignores a search shorter than two non-space characters', function (string $search): void {
    searchSession();

    $response = searchResponse(['q' => $search]);

    expect($response['sessions'])->toHaveCount(1)
        ->and($response['filters']['q'])->toBe('');
})->with(['a', ' a ', '   ', '']);

it('keeps at most one hundred characters of a search', function (): void {
    $response = searchResponse(['q' => str_repeat('a', 120)]);

    expect($response['filters']['q'])->toHaveLength(100);
});

it('treats symbols and non-English letters as ordinary text', function (): void {
    $session = searchSession('Symbols');
    $question = searchQuestion($session, 1, 'Ready?', 'Yes.');
    searchEntry($question, 'vocabulary', [
        'content_text' => '100% (sure) "ok" [x]',
        'correction_text' => 'привет',
    ]);

    foreach (['100%', '(sure)', '"ok"', '[x]', 'привет'] as $term) {
        $response = searchResponse(['q' => $term]);

        expect($response['sessions'])->toHaveCount(1, "search: {$term}")
            ->and($response['sessions'][0]['matches'])->not->toBeEmpty("search: {$term}");
    }
});

it('labels each matching item with its kind and section', function (): void {
    $session = searchSession('Travel');
    $question = searchQuestion($session, 2, 'Where did you go?', 'I went to the beach.');
    searchEntry($question, 'mistake', ['content_text' => 'beach']);
    searchEntry($question, 'vocabulary', ['content_text' => 'beach']);

    $matches = searchResponse(['q' => 'beach'])['sessions'][0]['matches'];

    expect(collect($matches)->pluck('kind')->all())
        ->toBe(['answer', 'entry', 'entry'])
        ->and(collect($matches)->pluck('type')->all())
        ->toBe([null, 'mistake', 'vocabulary'])
        ->and($matches[0]['question_position'])->toBe(2)
        ->and(collect($matches)->pluck('entry_position')->all())
        ->toBe([null, 1, 1]);
});

it('keeps a sensible number of items per session and counts the rest', function (): void {
    $session = searchSession('Many notes');
    $question = searchQuestion($session, 1);

    foreach (range(1, 60) as $position) {
        searchEntry(
            $question,
            'grammar_note',
            ['content_text' => "Alpha note {$position}"],
            $position,
        );
    }

    $payload = searchResponse(['q' => 'alpha'])['sessions'][0];

    expect($payload['match_count'])->toBe(60)
        ->and($payload['matches'])->toHaveCount(SessionSearchMatches::MAX_MATCHES);
});

it('returns a friendly empty result and a total that matches the search', function (): void {
    $matching = searchSession('Travel');
    searchQuestion($matching, 1, 'A lovely beach', 'Yes.');
    $other = searchSession('Food');
    searchQuestion($other, 1, 'A lovely soup', 'Yes.');

    $response = searchResponse(['q' => 'beach']);

    expect($response['sessions'])->toHaveCount(1)
        ->and($response['total'])->toBe(1)
        ->and($response['all_total'])->toBe(2)
        ->and($response['filters']['q'])->toBe('beach');

    $none = searchResponse(['q' => 'zzzz']);

    expect($none['sessions'])->toBe([])
        ->and($none['total'])->toBe(0)
        ->and($none['all_total'])->toBe(2);
});

it('searches rows that need attention and rows not yet split', function (): void {
    $session = searchSession('Travel');
    $question = searchQuestion($session, 1);

    searchEntry($question, 'mistake', [
        'content_text' => 'alpha',
        'correction_text' => '',
        'is_split' => false,
    ]);

    $response = searchResponse(['q' => 'alpha']);

    expect($response['sessions'])->toHaveCount(1)
        ->and($response['sessions'][0]['matches'][0]['type'])->toBe('mistake');
});

it('keeps the number of queries the same as the page grows', function (): void {
    $measure = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        searchResponse(['q' => 'alpha']);

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $first = searchSession('One');
    $question = searchQuestion($first, 1);
    searchEntry($question, 'vocabulary', ['content_text' => 'alpha']);

    $one = $measure();

    foreach (range(2, 12) as $index) {
        $session = searchSession("Topic {$index}");
        $question = searchQuestion($session, 1);
        searchEntry($question, 'vocabulary', ['content_text' => 'alpha']);
    }

    expect($measure())->toBe($one);
});

it('backfills search text without touching timestamps and can run again', function (): void {
    $session = searchSession('Café');
    $question = searchQuestion($session, 1, 'Crème brûlée?', 'Oui.');
    searchEntry($question, 'vocabulary', ['content_text' => 'Café']);

    DB::table('topics')->update(['search_text' => null]);
    DB::table('practice_questions')->update([
        'question_search_text' => null,
        'answer_search_text' => null,
    ]);
    DB::table('practice_feedback_entries')->update(['search_text' => null]);

    $updatedAt = DB::table('practice_questions')->value('updated_at');

    $first = app(BackfillSearchText::class)->handle();

    expect($first)->toBe(['topics' => 1, 'questions' => 1, 'entries' => 1])
        ->and(DB::table('topics')->value('search_text'))->toBe('cafe')
        ->and(DB::table('practice_questions')->value('question_search_text'))->toBe('creme brulee?')
        ->and(DB::table('practice_feedback_entries')->value('search_text'))->toBe('cafe')
        ->and(DB::table('practice_questions')->value('updated_at'))->toBe($updatedAt);

    $second = app(BackfillSearchText::class)->handle();

    expect($second)->toBe(['topics' => 0, 'questions' => 0, 'entries' => 0]);
});

it('keeps the searchable copy up to date when content changes', function (): void {
    $session = searchSession('Travel');
    $question = searchQuestion($session, 1);
    $entry = searchEntry($question, 'vocabulary', ['content_text' => 'harbour']);

    expect($entry->search_text)->toBe('harbour');

    $entry->content_text = 'marina';
    $entry->save();

    expect($entry->fresh()?->search_text)->toBe('marina')
        ->and(searchResponse(['q' => 'harbour'])['sessions'])->toBe([])
        ->and(searchResponse(['q' => 'marina'])['sessions'])->toHaveCount(1);
});
