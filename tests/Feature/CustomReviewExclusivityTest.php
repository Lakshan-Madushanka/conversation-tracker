<?php

declare(strict_types=1);

use App\Actions\Collections\SaveItemMark;
use App\Actions\PracticeQuestions\SavePracticeQuestions;
use App\Actions\Reviews\RecordRating;
use App\Actions\Reviews\SyncReviewSchedule;
use App\Enums\CollectionItemStatus;
use App\Enums\CustomReviewStatus;
use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use App\Models\CustomReview;
use App\Models\CustomReviewAttempt;
use App\Models\CustomReviewAttemptItem;
use App\Models\CustomReviewItem;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Rating;
use App\Models\Review;
use App\Models\Topic;
use App\Support\SearchText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A finished session holding enough words to cross the recommended size, one of
 * them still asking for attention.
 *
 * @return array{session: PracticeSession, identity: array<string, string>}
 */
function exclusivitySource(): array
{
    $at = CarbonImmutable::now();

    $session = PracticeSession::factory()
        ->finished()
        ->for(Topic::factory()->create(['name' => 'Ordering tea']))
        ->create([
            'created_at' => $at,
            'updated_at' => $at,
            'finished_at' => $at,
            'last_changed_at' => $at,
        ]);

    $question = PracticeQuestion::factory()->for($session)->create([
        'position' => 1,
    ]);

    $words = [
        'alpha',
        'beta',
        'gamma',
        ...array_map(
            fn (int $index): string => 'word'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            range(1, 21),
        ),
    ];

    $identity = [];

    foreach ($words as $position => $word) {
        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => FeedbackType::Vocabulary->value,
            'content_text' => $word,
            'content_html' => '<p>'.e($word).'</p>',
            'correction_text' => 'the meaning of '.$word,
            'correction_html' => '<p>the meaning of '.e($word).'</p>',
            'reason' => '',
            'example' => '',
            'example_html' => [],
            'position' => $position,
            'is_split' => true,
            'client_id' => (string) Str::uuid(),
        ]);

        $identity[$word] = SearchText::normalize($word);
    }

    // One word the learner still has to give a meaning, so the "waiting for a
    // fix" note has something to count.
    PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => FeedbackType::Vocabulary->value,
        'content_text' => 'needs meaning',
        'content_html' => '<p>needs meaning</p>',
        'correction_text' => '',
        'correction_html' => '',
        'reason' => '',
        'example' => '',
        'example_html' => [],
        'position' => count($words),
        'is_split' => true,
        'client_id' => (string) Str::uuid(),
    ]);

    return ['session' => $session, 'identity' => $identity];
}

/**
 * Three Easy ratings in a row for one word, the last of them today's completed
 * daily review. This gives the rest of the app real history to read: schedules,
 * recent results, status suggestions, practice days and the daily card.
 *
 * @param  array<string, string>  $identity
 */
function exclusivityHistory(array $identity): Review
{
    $today = CarbonImmutable::now();
    $latest = null;

    foreach ([-2, -1, 0] as $offset) {
        $moment = $today->addDays($offset);

        $review = new Review;
        $review->created_at = $moment;
        $review->updated_at = $moment;
        $review->finished_at = $moment;
        $review->save();

        $review->items()->create([
            'position' => 0,
            'kind' => FeedbackType::Vocabulary->value,
            'item_identity' => $identity['alpha'],
        ]);

        DB::table('ratings')->insert([
            'review_id' => $review->id,
            'kind' => FeedbackType::Vocabulary->value,
            'item_identity' => $identity['alpha'],
            'rating' => ReviewRating::Easy->value,
            'unique_key' => Rating::keyFor($review->id, FeedbackType::Vocabulary, $identity['alpha']),
            'created_at' => $moment,
            'updated_at' => $moment,
        ]);

        app(SyncReviewSchedule::class)->handle(
            FeedbackType::Vocabulary,
            $identity['alpha'],
        );

        $latest = $review;
    }

    $latest->is_daily_review = true;
    $latest->completed_on = $today->startOfDay();
    $latest->save();

    return $latest;
}

/**
 * The Inertia props of one page, read through the address the learner uses.
 *
 * @return array<string, mixed>
 */
function exclusivityProps(object $test, string $url): array
{
    /** @var array{props: array<string, mixed>} $page */
    $page = $test->get($url)->assertOk()->viewData('page');

    // Shared props carry empty stdClass markers; JSON round-tripping gives one
    // plain shape to compare without caring about object identity.
    /** @var array<string, mixed> $props */
    $props = json_decode(
        json_encode($page['props'], JSON_THROW_ON_ERROR),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    return $props;
}

/**
 * Everything the Collections page says about the learner's own items, with the
 * one custom-review affordance removed: whether an item is already in the
 * review being built is part of the custom feature, not a change to the item.
 *
 * @return array<string, mixed>
 */
function exclusivityCollectionFacts(object $test): array
{
    $props = exclusivityProps($test, route('collections.index'));

    unset($props['review_target'], $props['review_context']);

    /** @var list<array<string, mixed>> $items */
    $items = $props['items'];

    $props['items'] = array_map(function (array $item): array {
        unset($item['in_review']);

        return $item;
    }, $items);

    return $props;
}

/**
 * The Progress totals, practice days and streak the delete warning reads.
 *
 * @return array<string, mixed>
 */
function exclusivityDeletionFacts(object $test, PracticeSession $session): array
{
    $response = $test->getJson(route('sessions.deletePreview', [
        'session' => $session->id,
        'tz' => 'UTC',
    ]))->assertOk();

    return [
        'counts' => $response->json('counts'),
        'progress' => $response->json('progress'),
    ];
}

/**
 * Every row outside custom reviews, plus every page other than the custom
 * review pages that is built from them.
 *
 * @return array<string, mixed>
 */
function exclusivityWorld(object $test, PracticeSession $session, Review $suggestionReview): array
{
    $tables = [
        'topics',
        'practice_sessions',
        'practice_questions',
        'practice_feedback_entries',
        'collection_item_marks',
        'reviews',
        'review_items',
        'ratings',
        'review_schedules',
        'review_suggestion_declines',
    ];

    $snapshot = ['tables' => []];

    foreach ($tables as $table) {
        $snapshot['tables'][$table] = DB::table($table)
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();
    }

    $snapshot['home'] = exclusivityProps($test, route('home'));
    $snapshot['library'] = exclusivityProps($test, route('sessions.index'));
    $snapshot['review_home'] = exclusivityProps($test, route('review.index'));
    $snapshot['collections'] = exclusivityCollectionFacts($test);
    $snapshot['suggestions'] = exclusivityProps($test, route('review.session', [
        'review' => $suggestionReview->id,
    ]))['suggestions'];
    $snapshot['deletion'] = exclusivityDeletionFacts($test, $session);

    return $snapshot;
}

/**
 * The four tables a custom review owns. Nothing outside the feature may write
 * to them.
 *
 * @return array<string, list<array<string, mixed>>>
 */
function exclusivityCustomTables(): array
{
    $tables = [
        'custom_reviews',
        'custom_review_items',
        'custom_review_attempts',
        'custom_review_attempt_items',
    ];

    $snapshot = [];

    foreach ($tables as $table) {
        $snapshot[$table] = DB::table($table)
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();
    }

    return $snapshot;
}

/**
 * Save one rating on a running attempt, the way the exercise screen does.
 */
function exclusivityRate(
    object $test,
    CustomReview $review,
    CustomReviewAttempt $attempt,
    string $identity,
    ReviewRating $rating,
): void {
    $test->post(route('customReviews.attempts.rate', [
        'customReview' => $review->id,
        'attempt' => $attempt->id,
    ]), [
        'kind' => FeedbackType::Vocabulary->value,
        'item_identity' => $identity,
        'rating' => $rating->value,
    ])->assertRedirect();
}

it('changes nothing outside custom reviews, whatever the learner does inside one', function (): void {
    $source = exclusivitySource();
    $session = $source['session'];
    $identity = $source['identity'];
    $history = exclusivityHistory($identity);

    app(SaveItemMark::class)->handle(
        FeedbackType::Vocabulary,
        $identity['alpha'],
        true,
        CollectionItemStatus::StillDifficult,
    );

    $before = exclusivityWorld($this, $session, $history);

    $unchanged = function () use ($session, $history, $before): void {
        expect(exclusivityWorld($this, $session, $history))->toBe($before);
    };

    // Start a review: the saved set itself is the only thing that appears.
    $this->post(route('customReviews.store'))->assertRedirect();
    $review = CustomReview::query()->sole();
    $unchanged();

    /** @param list<string> $words */
    $add = function (array $words, bool $confirmed = false) use ($review, $identity): void {
        $payload = [
            'kind' => FeedbackType::Vocabulary->value,
            'identities' => array_map(
                fn (string $word): string => $identity[$word],
                $words,
            ),
            'review' => $review->id,
        ];

        if ($confirmed) {
            $payload['confirmed'] = true;
        }

        $this->postJson(route('customReviews.items.store'), $payload)->assertOk();
    };

    // Add its first items.
    $add(['alpha', 'beta', 'gamma']);
    $unchanged();

    // Name and describe it.
    $this->putJson(route('customReviews.update', $review), [
        'name' => 'Tea words',
        'description' => 'A small set for ordering tea.',
    ])->assertOk();
    $unchanged();

    // Mark it Finished.
    $this->putJson(route('customReviews.status', $review), [
        'status' => CustomReviewStatus::Finished->value,
    ])->assertOk();
    $unchanged();

    // Practise it.
    $this->post(route('customReviews.attempts.store', $review))->assertRedirect();
    $attempt = CustomReviewAttempt::query()->sole();
    $unchanged();

    exclusivityRate($this, $review, $attempt, $identity['alpha'], ReviewRating::Easy);
    $unchanged();

    // Take a rating back.
    $this->deleteJson(route('customReviews.attempts.undo', [
        'customReview' => $review->id,
        'attempt' => $attempt->id,
    ]), [
        'kind' => FeedbackType::Vocabulary->value,
        'item_identity' => $identity['alpha'],
    ])->assertRedirect();
    $unchanged();

    // Rate it again, then end the attempt early.
    exclusivityRate($this, $review, $attempt, $identity['alpha'], ReviewRating::Okay);
    $unchanged();

    $this->post(route('customReviews.attempts.finishEarly', [
        'customReview' => $review->id,
        'attempt' => $attempt->id,
    ]))->assertRedirect();
    $unchanged();

    // Read the attempt and the review: neither may repair anything outside
    // the custom tables.
    $this->get(route('customReviews.attempts.show', [
        'customReview' => $review->id,
        'attempt' => $attempt->id,
    ]))->assertOk();
    $unchanged();

    $this->get(route('customReviews.show', $review))->assertOk();
    $unchanged();

    // Fill it to the recommendation: twenty items, no warning.
    $add(array_map(
        fn (int $index): string => 'word'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
        range(1, 17),
    ));

    expect(CustomReviewItem::query()->where('custom_review_id', $review->id)->count())->toBe(20);
    $unchanged();

    // An add that would cross the recommendation warns before writing.
    $crossing = array_map(
        fn (int $index): string => $identity['word'.str_pad((string) $index, 2, '0', STR_PAD_LEFT)],
        range(18, 20),
    );

    $this->postJson(route('customReviews.items.store'), [
        'kind' => FeedbackType::Vocabulary->value,
        'identities' => $crossing,
        'review' => $review->id,
    ])->assertOk()->assertJson([
        'outcome' => 'needs_confirmation',
        'resulting_count' => 23,
    ]);
    $unchanged();

    // Confirming the same add writes the items and the size tag only.
    $this->postJson(route('customReviews.items.store'), [
        'kind' => FeedbackType::Vocabulary->value,
        'identities' => $crossing,
        'review' => $review->id,
        'confirmed' => true,
    ])->assertOk()->assertJson([
        'outcome' => 'added',
        'total' => 23,
    ]);
    $unchanged();

    // Remove items again.
    $this->deleteJson(route('customReviews.items.destroy', $review), [
        'kind' => FeedbackType::Vocabulary->value,
        'identities' => $crossing,
    ])->assertOk();
    $unchanged();

    // Empty it.
    $this->postJson(route('customReviews.clear', $review))->assertOk();
    $unchanged();

    // Fill it again and finish a whole attempt.
    $add(['alpha', 'beta']);
    $this->putJson(route('customReviews.status', $review), [
        'status' => CustomReviewStatus::Finished->value,
    ])->assertOk();
    $unchanged();

    $this->post(route('customReviews.attempts.store', $review))->assertRedirect();
    $attempt = CustomReviewAttempt::query()->latest('id')->firstOrFail();
    $unchanged();

    exclusivityRate($this, $review, $attempt, $identity['alpha'], ReviewRating::Easy);
    $unchanged();

    exclusivityRate($this, $review, $attempt, $identity['beta'], ReviewRating::Okay);
    $unchanged();

    expect($attempt->refresh()->status->value)->toBe('completed');

    // Delete the review and everything it recorded.
    $this->delete(route('customReviews.destroy', [
        'customReviewId' => $review->id,
    ]))->assertRedirect(route('customReviews.index'));

    expect(CustomReview::query()->count())->toBe(0);
    $unchanged();
});

it('leaves custom reviews untouched when the rest of the app is used', function (): void {
    $source = exclusivitySource();
    $session = $source['session'];
    $identity = $source['identity'];
    $history = exclusivityHistory($identity);

    $this->post(route('customReviews.store'))->assertRedirect();
    $review = CustomReview::query()->sole();

    $this->postJson(route('customReviews.items.store'), [
        'kind' => FeedbackType::Vocabulary->value,
        'identities' => [$identity['alpha'], $identity['beta'], $identity['gamma']],
        'review' => $review->id,
    ])->assertOk();

    $this->putJson(route('customReviews.status', $review), [
        'status' => CustomReviewStatus::Finished->value,
    ])->assertOk();

    $this->post(route('customReviews.attempts.store', $review))->assertRedirect();
    $attempt = CustomReviewAttempt::query()->sole();
    exclusivityRate($this, $review, $attempt, $identity['alpha'], ReviewRating::Easy);

    $before = exclusivityCustomTables();

    // The learner marks an item, rates a daily item and answers a suggestion.
    app(SaveItemMark::class)->handle(
        FeedbackType::Vocabulary,
        $identity['beta'],
        true,
        CollectionItemStatus::Learned,
    );

    app(RecordRating::class)->handle(
        $history,
        FeedbackType::Vocabulary,
        $identity['beta'],
        ReviewRating::Hard,
    );

    $offer = collect(exclusivityProps($this, route('review.session', [
        'review' => $history->id,
    ]))['suggestions'])->first();

    expect($offer)->toBeArray();

    $this->post(route('review.suggestionDecline', [
        'review' => $history->id,
    ]), [
        'kind' => $offer['kind'],
        'item_identity' => $offer['item_identity'],
        'status' => $offer['status'],
        'run_started_id' => $offer['run_started_id'],
    ])->assertRedirect();

    // Archiving and restoring a session changes what is eligible, never what a
    // review or an attempt has already stored.
    $this->postJson(route('sessions.archive', $session))->assertOk();
    $this->postJson(route('sessions.restore', $session))->assertOk();

    expect(exclusivityCustomTables())->toBe($before);
});

it('keeps a past attempt’s snapshot when the item it named is corrected', function (): void {
    $at = CarbonImmutable::now();

    $session = PracticeSession::factory()
        ->finished()
        ->for(Topic::factory()->create(['name' => 'Noticing mistakes']))
        ->create([
            'created_at' => $at,
            'updated_at' => $at,
            'finished_at' => $at,
            'last_changed_at' => $at,
        ]);

    $question = PracticeQuestion::factory()->for($session)->create([
        'position' => 1,
        'question_text' => 'What did you notice?',
    ]);

    PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => FeedbackType::Vocabulary->value,
        'content_text' => 'teh',
        'content_html' => '<p>teh</p>',
        'correction_text' => 'slow to act',
        'correction_html' => '<p>slow to act</p>',
        'client_id' => 'row-1',
        'position' => 0,
        'is_split' => true,
    ]);

    $this->post(route('customReviews.store'))->assertRedirect();
    $review = CustomReview::query()->sole();

    $this->postJson(route('customReviews.items.store'), [
        'kind' => FeedbackType::Vocabulary->value,
        'identities' => [SearchText::normalize('teh')],
        'review' => $review->id,
    ])->assertOk();

    $this->putJson(route('customReviews.status', $review), [
        'status' => CustomReviewStatus::Finished->value,
    ])->assertOk();

    $this->post(route('customReviews.attempts.store', $review))->assertRedirect();

    expect(CustomReviewAttemptItem::query()->sole()->snapshot_text)->toBe('teh');

    // Correct the typo through the editor, the path that moves a review's
    // reference from one identity to the other.
    DB::transaction(fn (): mixed => app(SavePracticeQuestions::class)->handle($session, [
        [
            'question_html' => '<p>What did you notice?</p>',
            'answer_html' => '<p>A sentence.</p>',
            'feedback' => [
                'vocabulary' => [[
                    'id' => 'row-1',
                    'text' => 'the',
                    'meaning' => 'slow to act',
                ]],
            ],
        ],
    ]));

    expect(CustomReviewItem::query()->sole()->item_identity)
        ->toBe(SearchText::normalize('the'))
        ->and(CustomReviewAttemptItem::query()->sole()->item_identity)
        ->toBe(SearchText::normalize('teh'))
        ->and(CustomReviewAttemptItem::query()->sole()->snapshot_text)
        ->toBe('teh');

    $this->get(route('customReviews.show', $review))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('groups.0.items.0.identity', SearchText::normalize('the'))
            ->where('groups.0.items.0.title', 'the')
            // The past attempt keeps the words it actually showed. A corrected
            // spelling is not a deletion, so nothing is marked Deleted there.
            ->where('attempts.0.items.0.text', 'teh')
            ->where('attempts.0.items.0.deleted', false));
});
