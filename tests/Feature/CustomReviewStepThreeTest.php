<?php

declare(strict_types=1);

use App\Actions\CustomReviews\AddCustomReviewItems;
use App\Actions\CustomReviews\SetCustomReviewStatus;
use App\Enums\CustomReviewStatus;
use App\Enums\FeedbackType;
use App\Models\CustomReview;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use App\Support\CustomReviewPractice;
use App\Support\SearchText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/** A review as the listing receives it. */
function stepThreeReview(
    string $name,
    CustomReviewStatus $status = CustomReviewStatus::Empty,
    ?string $description = null,
    ?CarbonImmutable $createdAt = null,
): CustomReview {
    $at = $createdAt ?? CarbonImmutable::now();

    return CustomReview::factory()->create([
        'name' => $name,
        'description' => $description,
        'status' => $status,
        'created_at' => $at,
        'updated_at' => $at,
    ]);
}

/**
 * A finished session with one question, ready to give a review items.
 *
 * @return array{session: PracticeSession, question: PracticeQuestion}
 */
function stepThreeBox(bool $archived = false): array
{
    $at = CarbonImmutable::now();

    $session = PracticeSession::factory()
        ->finished()
        ->for(Topic::factory())
        ->create([
            'created_at' => $at,
            'updated_at' => $at,
            'finished_at' => $at,
            'last_changed_at' => $at,
            'archived_at' => $archived ? $at : null,
        ]);

    return [
        'session' => $session,
        'question' => PracticeQuestion::factory()->for($session)->create([
            'position' => 1,
        ]),
    ];
}

/** One word in a box, named by the identity a review stores. */
function stepThreeWord(
    PracticeQuestion $question,
    string $word,
    int $position = 0,
): string {
    PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => FeedbackType::Vocabulary->value,
        'content_text' => $word,
        'correction_text' => 'the meaning of '.$word,
        'correction_html' => '<p>the meaning of '.e($word).'</p>',
        'position' => $position,
        'is_split' => true,
    ]);

    return SearchText::normalize($word);
}

/**
 * @param  list<string>  $identities
 */
function stepThreeAttach(CustomReview $review, array $identities): void
{
    app(AddCustomReviewItems::class)->handle(
        $review,
        FeedbackType::Vocabulary,
        $identities,
    );
}

function stepThreeFinish(CustomReview $review): void
{
    app(SetCustomReviewStatus::class)->handle(
        $review,
        CustomReviewStatus::Finished,
    );
}

it('lists every custom review, unfinished ones included, as a card', function (): void {
    $old = CarbonImmutable::now()->subDays(2);

    $empty = stepThreeReview('Empty one', CustomReviewStatus::Empty, null, $old);
    $draft = stepThreeReview('Draft one', CustomReviewStatus::Draft, 'A short note', $old->addDay());
    $finished = stepThreeReview('Finished one', CustomReviewStatus::Finished, null, CarbonImmutable::now());

    $this->get(route('customReviews.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('custom-reviews/Index')
            ->has('reviews', 3)
            ->where('total', 3)
            ->where('all_total', 3)
            ->where('per_page', 20)
            ->where('next_cursor', null)
            ->where('has_more', false)
            ->where('filters.q', '')
            ->where('filters.statuses', [])
            ->where('filters.sort', 'recently_attempted')
            ->where('reviews.0.id', $finished->id)
            ->where('reviews.0.name', 'Finished one')
            ->where('reviews.0.status', 'finished')
            ->where('reviews.0.status_label', 'Finished')
            ->where('reviews.0.item_count', 0)
            ->where('reviews.0.item_count_label', '0 items')
            ->where('reviews.0.too_many_items', false)
            ->where('reviews.0.too_many_label', null)
            ->where('reviews.0.attempt_count', 0)
            ->where('reviews.0.has_attempts', false)
            ->where('reviews.0.attempt_number_label', 'No attempts yet')
            ->where('reviews.0.previous_attempt_status', null)
            ->where('reviews.0.previous_attempt_label', null)
            ->where('reviews.0.attempt_summary_label', 'No attempts yet')
            ->where('reviews.0.view_history_url', null)
            ->where('reviews.0.can_start', false)
            ->where('reviews.0.practice_note', CustomReviewPractice::NOT_AVAILABLE)
            ->where('reviews.1.id', $draft->id)
            ->where('reviews.1.status_label', 'Draft')
            ->where('reviews.1.practice_note', CustomReviewPractice::NEEDS_FINISHING)
            ->where('reviews.2.id', $empty->id)
            ->where('reviews.2.status_label', 'Empty')
            ->where('reviews.2.practice_note', CustomReviewPractice::NEEDS_ITEMS)
        );
});

it('searches names and descriptions without case, accents or extra spaces', function (): void {
    $now = CarbonImmutable::now();

    $accented = stepThreeReview('Café au lait', description: 'Breakfast words', createdAt: $now->subMinutes(2));
    $descriptionOnly = stepThreeReview('Morning set', description: 'Ordering au café', createdAt: $now->subMinute());
    stepThreeReview('Airport phrases', description: 'Travel words', createdAt: $now);

    // Case, accents and repeated spaces are all ignored, and a partial word
    // finds the review that holds it.
    $this->get(route('customReviews.index', ['q' => '  CAFE   AU  ']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('reviews', 2)
            ->where('total', 2)
            ->where('all_total', 3)
            ->where('filters.q', 'CAFE AU')
            ->where('reviews.0.id', $descriptionOnly->id)
            ->where('reviews.1.id', $accented->id)
        );

    $this->get(route('customReviews.index', ['q' => 'caf']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('reviews', 2)
            ->where('total', 2)
        );

    // Every typed word must appear, so adding an unrelated word matches none.
    $this->get(route('customReviews.index', ['q' => 'cafe airport']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('reviews', 0)
            ->where('total', 0)
            ->where('all_total', 3)
        );

    // A single letter is not a search, and the longest search is clipped.
    $this->get(route('customReviews.index', ['q' => 'a']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('reviews', 3)
            ->where('filters.q', '')
        );

    $this->get(route('customReviews.index', ['q' => str_repeat('a', 150)]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.q', str_repeat('a', 100))
        );
});

it('filters by one or several statuses', function (): void {
    $empty = stepThreeReview('Empty one', CustomReviewStatus::Empty);
    $draft = stepThreeReview('Draft one', CustomReviewStatus::Draft);
    $finished = stepThreeReview('Finished one', CustomReviewStatus::Finished);

    $this->get(route('customReviews.index', ['status' => 'draft,finished']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('reviews', 2)
            ->where('total', 2)
            ->where('all_total', 3)
            ->where('filters.statuses', ['draft', 'finished'])
            ->where('reviews.0.id', $finished->id)
            ->where('reviews.1.id', $draft->id)
        );

    // Repeated values are understood exactly like a comma-separated list.
    $this->get(route('customReviews.index', ['status' => ['draft', 'finished']]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('reviews', 2)
            ->where('filters.statuses', ['draft', 'finished'])
        );

    $this->get(route('customReviews.index', ['status' => 'empty']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('reviews', 1)
            ->where('reviews.0.id', $empty->id)
            ->where('filters.statuses', ['empty'])
        );

    // A status the app does not know is ignored quietly.
    $this->get(route('customReviews.index', ['status' => 'archived']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('reviews', 3)
            ->where('filters.statuses', [])
        );
});

it('combines a search with the status filter', function (): void {
    $now = CarbonImmutable::now();

    $matching = stepThreeReview('Café words', CustomReviewStatus::Draft, createdAt: $now->subMinutes(2));
    stepThreeReview('Café words', CustomReviewStatus::Finished, createdAt: $now->subMinute());
    stepThreeReview('Airport words', CustomReviewStatus::Draft, createdAt: $now);

    $this->get(route('customReviews.index', ['q' => 'cafe', 'status' => 'draft']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('reviews', 1)
            ->where('total', 1)
            ->where('all_total', 3)
            ->where('reviews.0.id', $matching->id)
            ->where('filters.q', 'cafe')
            ->where('filters.statuses', ['draft'])
        );
});

it('sorts by most recently attempted, by name and by created date', function (): void {
    $now = CarbonImmutable::now();

    stepThreeReview('banana', createdAt: $now->subMinutes(3));
    stepThreeReview('Apple', createdAt: $now->subMinute());
    stepThreeReview('cherry', createdAt: $now->subMinutes(2));

    // With no attempts yet, the default falls back to the newest created first.
    $this->get(route('customReviews.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.sort', 'recently_attempted')
            ->where('reviews.0.name', 'Apple')
            ->where('reviews.1.name', 'cherry')
            ->where('reviews.2.name', 'banana')
        );

    $this->get(route('customReviews.index', ['sort' => 'created']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.sort', 'created')
            ->where('reviews.0.name', 'Apple')
            ->where('reviews.1.name', 'cherry')
            ->where('reviews.2.name', 'banana')
        );

    // Name order ignores case, so "Apple" and "banana" sort as a reader expects.
    $this->get(route('customReviews.index', ['sort' => 'name']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.sort', 'name')
            ->where('reviews.0.name', 'Apple')
            ->where('reviews.1.name', 'banana')
            ->where('reviews.2.name', 'cherry')
        );

    // An order the app does not know falls back to the default.
    $this->get(route('customReviews.index', ['sort' => 'oldest']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.sort', 'recently_attempted')
        );
});

it('loads twenty reviews at a time and continues from the last one shown', function (): void {
    $now = CarbonImmutable::now();

    foreach (range(0, 24) as $index) {
        stepThreeReview(
            'Review '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            createdAt: $now->subMinutes($index),
        );
    }

    $first = $this->getJson(route('customReviews.index'))
        ->assertOk()
        ->json();

    expect($first['reviews'])->toHaveCount(20)
        ->and($first['has_more'])->toBeTrue()
        ->and($first['next_cursor'])->toBeString()
        ->and($first['total'])->toBe(25)
        ->and($first['all_total'])->toBe(25)
        ->and($first['per_page'])->toBe(20)
        ->and($first['reviews'][0]['name'])->toBe('Review 00')
        ->and($first['reviews'][19]['name'])->toBe('Review 19');

    $second = $this->getJson(route('customReviews.index', [
        'cursor' => $first['next_cursor'],
    ]))
        ->assertOk()
        ->json();

    expect($second['reviews'])->toHaveCount(5)
        ->and($second['has_more'])->toBeFalse()
        ->and($second['next_cursor'])->toBeNull()
        ->and($second['total'])->toBe(25)
        ->and($second['reviews'][0]['name'])->toBe('Review 20')
        ->and($second['reviews'][4]['name'])->toBe('Review 24');
});

it('orders names without case, so a reader sees A to Z', function (): void {
    $now = CarbonImmutable::now();

    stepThreeReview('banana', createdAt: $now->subMinutes(2));
    stepThreeReview('Apple', createdAt: $now->subMinute());
    stepThreeReview('apricot', createdAt: $now);

    $this->get(route('customReviews.index', ['sort' => 'name']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('reviews.0.name', 'Apple')
            ->where('reviews.1.name', 'apricot')
            ->where('reviews.2.name', 'banana')
        );
});

it('continues any chosen order from the last review shown', function (): void {
    $now = CarbonImmutable::now();

    foreach (range(0, 24) as $index) {
        stepThreeReview(
            'Review '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            createdAt: $now->subMinutes($index),
        );
    }

    $first = $this->getJson(route('customReviews.index', ['sort' => 'name']))
        ->assertOk()
        ->json();

    $second = $this->getJson(route('customReviews.index', [
        'sort' => 'name',
        'cursor' => $first['next_cursor'],
    ]))
        ->assertOk()
        ->json();

    $names = array_map(
        fn (array $review): string => $review['name'],
        [...$first['reviews'], ...$second['reviews']],
    );

    expect($names)->toHaveCount(25)
        ->and($names)->toBe(array_values(array_unique($names)))
        ->and($names[0])->toBe('Review 00')
        ->and($names[24])->toBe('Review 24');
});

it('shows the Too Many Items tag with the same threshold and words as the review page', function (): void {
    $box = stepThreeBox();

    $words = [];

    foreach (range(1, 21) as $index) {
        $words[] = stepThreeWord($box['question'], 'word '.$index, $index);
    }

    $review = stepThreeReview('Oversized set', CustomReviewStatus::Draft);
    stepThreeAttach($review, $words);

    $cards = collect($this->getJson(route('customReviews.index'))->assertOk()->json('reviews'))
        ->keyBy('id');
    $card = $cards->get($review->id);

    expect($card)->not->toBeNull()
        ->and($card['item_count'])->toBe(21)
        ->and($card['too_many_items'])->toBeTrue()
        ->and($card['too_many_label'])->toBe('Too Many Items · 21 items');

    $this->get(route('customReviews.show', $review))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('review.too_many_items', true)
            ->where('review.too_many_label', $card['too_many_label'])
        );
});

it('explains what still stands between a review and practice', function (): void {
    $empty = stepThreeReview('Empty set', CustomReviewStatus::Empty);

    $draftBox = stepThreeBox();
    $draftWord = stepThreeWord($draftBox['question'], 'draft word');
    $draft = stepThreeReview('Draft set', CustomReviewStatus::Draft);
    stepThreeAttach($draft, [$draftWord]);

    $readyBox = stepThreeBox();
    $readyWord = stepThreeWord($readyBox['question'], 'ready word');
    $ready = stepThreeReview('Ready set', CustomReviewStatus::Draft);
    stepThreeAttach($ready, [$readyWord]);
    stepThreeFinish($ready);

    $archivedBox = stepThreeBox(archived: true);
    $archivedWord = stepThreeWord($archivedBox['question'], 'archived word');
    $unavailable = stepThreeReview('Unavailable set', CustomReviewStatus::Draft);
    stepThreeAttach($unavailable, [$archivedWord]);
    stepThreeFinish($unavailable);

    $cards = collect($this->getJson(route('customReviews.index'))->assertOk()->json('reviews'))
        ->keyBy('id');

    expect($cards[$empty->id]['practice_note'])->toBe(CustomReviewPractice::NEEDS_ITEMS)
        ->and($cards[$empty->id]['can_start'])->toBeFalse()
        ->and($cards[$draft->id]['practice_note'])->toBe(CustomReviewPractice::NEEDS_FINISHING)
        ->and($cards[$draft->id]['can_start'])->toBeFalse()
        ->and($cards[$ready->id]['practice_note'])->toBeNull()
        ->and($cards[$ready->id]['can_start'])->toBeTrue()
        ->and($cards[$unavailable->id]['practice_note'])->toBe(CustomReviewPractice::NOT_AVAILABLE)
        ->and($cards[$unavailable->id]['can_start'])->toBeFalse();
});

it('shortens a long description on the card and keeps the whole one on the review page', function (): void {
    $description = trim(str_repeat('A long description of the words I keep practising. ', 8));

    $review = stepThreeReview('Long words', description: $description);

    $card = collect($this->getJson(route('customReviews.index'))->assertOk()->json('reviews'))
        ->keyBy('id')
        ->get($review->id);

    expect($card)->not->toBeNull()
        ->and($card['description'])->toBe($description)
        ->and($card['description_excerpt'])->not->toBeNull()
        ->and(mb_strlen($card['description_excerpt']))->toBeLessThanOrEqual(163)
        ->and(str_ends_with($card['description_excerpt'], '...'))->toBeTrue();

    $this->get(route('customReviews.show', $review))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('review.description', $description)
        );
});

it('keeps the card status label in step with the review page', function (): void {
    $box = stepThreeBox();
    $word = stepThreeWord($box['question'], 'relish');

    $review = stepThreeReview('Morning set', CustomReviewStatus::Draft);
    stepThreeAttach($review, [$word]);

    $this->putJson(route('customReviews.status', $review), ['status' => 'finished'])
        ->assertOk()
        ->assertJsonPath('review.status_label', 'Finished');

    $card = collect($this->getJson(route('customReviews.index'))->assertOk()->json('reviews'))
        ->keyBy('id')
        ->get($review->id);

    expect($card['status'])->toBe('finished')
        ->and($card['status_label'])->toBe('Finished');

    $this->get(route('customReviews.show', $review))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('review.status', 'finished')
            ->where('review.status_label', 'Finished')
        );
});

it('reads the listing without changing a review, item, mark, rating or session', function (): void {
    $box = stepThreeBox();
    $word = stepThreeWord($box['question'], 'cafe', 1);
    stepThreeWord($box['question'], 'relish', 2);

    $review = stepThreeReview('Read only', CustomReviewStatus::Draft);
    stepThreeAttach($review, [$word]);

    $tables = [
        'custom_reviews',
        'custom_review_items',
        'practice_sessions',
        'practice_questions',
        'practice_feedback_entries',
        'collection_item_marks',
        'ratings',
        'reviews',
        'review_items',
        'review_schedules',
    ];

    $countsBefore = collect($tables)
        ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
        ->all();
    $reviewBefore = DB::table('custom_reviews')->where('id', $review->id)->first();
    $sessionBefore = DB::table('practice_sessions')->where('id', $box['session']->id)->first();

    $this->get(route('customReviews.index'))->assertOk();
    $this->getJson(route('customReviews.index', ['q' => 'cafe']))->assertOk();

    $countsAfter = collect($tables)
        ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
        ->all();

    expect($countsAfter)->toBe($countsBefore)
        ->and(DB::table('custom_reviews')->where('id', $review->id)->first())->toEqual($reviewBefore)
        ->and(DB::table('practice_sessions')->where('id', $box['session']->id)->first())->toEqual($sessionBefore);
});

it('reads the listing in a fixed number of queries however many reviews there are', function (): void {
    $box = stepThreeBox();
    $word = stepThreeWord($box['question'], 'word');
    $review = stepThreeReview('One review', CustomReviewStatus::Draft);
    stepThreeAttach($review, [$word]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->getJson(route('customReviews.index'))->assertOk();
    $few = count(DB::getQueryLog());

    // Enough reviews to fill the first page, each with an item of its own.
    foreach (range(2, 20) as $index) {
        $otherBox = stepThreeBox();
        $otherWord = stepThreeWord($otherBox['question'], 'word '.$index);
        $other = stepThreeReview('Review '.$index, CustomReviewStatus::Draft);
        stepThreeAttach($other, [$otherWord]);
    }

    DB::flushQueryLog();
    $this->getJson(route('customReviews.index'))->assertOk();
    $many = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($many)->toBe($few);
});

it('starts a named empty review from the listing action', function (): void {
    $this->post(route('customReviews.store'))->assertRedirect();

    $review = CustomReview::query()->sole();

    expect($review->name)->toBe('Custom Review 1')
        ->and($review->status)->toBe(CustomReviewStatus::Empty)
        ->and(DB::table('custom_review_items')->count())->toBe(0);
});
