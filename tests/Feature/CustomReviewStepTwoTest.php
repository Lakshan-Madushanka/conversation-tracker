<?php

declare(strict_types=1);

use App\Enums\CustomReviewStatus;
use App\Enums\FeedbackType;
use App\Models\CustomReview;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use App\Support\CustomReviewEligibility;
use App\Support\CustomReviewRules;
use App\Support\MistakeIdentity;
use App\Support\SearchText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A finished session at a known moment, with a topic the review can name.
 *
 * @param  array<string, mixed>  $attributes
 */
function stepTwoSession(
    string $topic = 'Ordering coffee',
    array $attributes = [],
): PracticeSession {
    $at = CarbonImmutable::now();

    return PracticeSession::factory()
        ->finished()
        ->for(Topic::factory()->create(['name' => $topic]))
        ->create(array_merge([
            'created_at' => $at,
            'updated_at' => $at,
            'finished_at' => $at,
            'last_changed_at' => $at,
        ], $attributes));
}

/** @param  array<string, mixed>  $attributes */
function stepTwoQuestion(
    PracticeSession $session,
    int $position = 1,
    array $attributes = [],
): PracticeQuestion {
    return PracticeQuestion::factory()->for($session)->create(array_merge([
        'position' => $position,
    ], $attributes));
}

/** @param  array<string, mixed>  $attributes */
function stepTwoRow(
    PracticeQuestion $question,
    string $text,
    FeedbackType $type = FeedbackType::Vocabulary,
    string $correction = '',
    array $attributes = [],
): PracticeFeedbackEntry {
    $position = $question->feedbackEntries()
        ->where('type', $type->value)
        ->count();

    return PracticeFeedbackEntry::factory()->for($question)->create(array_merge([
        'type' => $type->value,
        'content_text' => $text,
        'correction_text' => $correction,
        'correction_html' => $correction === '' ? '' : '<p>'.e($correction).'</p>',
        'reason' => '',
        'example' => '',
        'example_html' => [],
        'position' => $position,
        'is_split' => true,
    ], $attributes));
}

function stepTwoNote(
    PracticeQuestion $question,
    string $html,
    string $text = '',
): PracticeFeedbackEntry {
    return PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => FeedbackType::GrammarNote->value,
        'content_html' => $html,
        'content_text' => $text !== '' ? $text : trim(strip_tags($html)),
        'position' => 0,
    ]);
}

/** @param  list<string>  $identities */
function stepTwoAdd(
    object $test,
    FeedbackType $kind,
    array $identities,
    int $reviewId,
    bool $confirmed = false,
): TestResponse {
    return $test->postJson(route('customReviews.items.store'), [
        'kind' => $kind->value,
        'identities' => $identities,
        'review' => $reviewId,
        'confirmed' => $confirmed,
    ]);
}

/** The review started the way the learner starts one. */
function stepTwoStartReview(object $test): CustomReview
{
    $test->post(route('customReviews.store'))->assertRedirect();

    /** @var CustomReview $review */
    $review = CustomReview::query()->orderByDesc('id')->firstOrFail();

    return $review;
}

/**
 * One group's items by identity, so an assertion can name the item it reads.
 *
 * @param  Collection<int, array<string, mixed>>  $items
 * @return Collection<string, array<string, mixed>>
 */
function stepTwoByIdentity(Collection $items): Collection
{
    return $items->keyBy('identity');
}

it('saves a name and a description on their own, trimming both', function (): void {
    $review = CustomReview::factory()->draft()->create(['name' => 'Old name']);

    $this->putJson(route('customReviews.update', $review), [
        'name' => '  Morning coffee  ',
        'description' => '  Words to revisit  ',
    ])
        ->assertOk()
        ->assertJsonPath('id', $review->id)
        ->assertJsonPath('version', 0)
        ->assertJsonPath('review.name', 'Morning coffee')
        ->assertJsonPath('review.description', 'Words to revisit')
        ->assertJsonPath('review.status', 'draft')
        ->assertJsonPath('review.too_many_items', false)
        ->assertJsonPath('review.too_many_label', null);

    // A description of only spaces is no description at all, and the name stays.
    $this->putJson(route('customReviews.update', $review), [
        'description' => '   ',
    ])
        ->assertOk()
        ->assertJsonPath('review.name', 'Morning coffee')
        ->assertJsonPath('review.description', null);

    // A name saved alone never clears the description that is already there.
    $this->putJson(route('customReviews.update', $review), [
        'description' => 'Kept for later',
    ])->assertOk();

    $this->putJson(route('customReviews.update', $review), ['name' => 'Tea'])
        ->assertOk()
        ->assertJsonPath('review.name', 'Tea')
        ->assertJsonPath('review.description', 'Kept for later');
});

it('refuses a blank name, a long name and a long description', function (): void {
    $review = CustomReview::factory()->create(['name' => 'Kept']);

    $this->putJson(route('customReviews.update', $review), ['name' => '   '])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'Give this review a name.');

    $this->putJson(route('customReviews.update', $review), [
        'name' => str_repeat('a', CustomReviewRules::NAME_MAX_LENGTH + 1),
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'Keep the name to 80 characters or fewer.');

    $this->putJson(route('customReviews.update', $review), [
        'description' => str_repeat('a', CustomReviewRules::DESCRIPTION_MAX_LENGTH + 1),
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.description.0', 'Keep the description to 500 characters or fewer.');

    expect($review->fresh()?->name)->toBe('Kept');
});

it('lets two reviews share a name and keeps the default numbering apart', function (): void {
    $first = stepTwoStartReview($this);
    $second = stepTwoStartReview($this);

    expect($first->name)->toBe('Custom Review 1')
        ->and($second->name)->toBe('Custom Review 2');

    // Renaming one never renumbers the others.
    $this->putJson(route('customReviews.update', $first), [
        'name' => 'Travel words',
    ])->assertOk();

    expect($second->fresh()?->name)->toBe('Custom Review 2');

    // The number a renamed review frees is the one the next default name takes.
    $third = stepTwoStartReview($this);

    expect($third->name)->toBe('Custom Review 1');

    // Two reviews may share a name.
    $this->putJson(route('customReviews.update', $second), [
        'name' => 'Travel words',
    ])->assertOk();

    expect(CustomReview::query()->where('name', 'Travel words')->count())->toBe(2);
});

it('moves a review between draft and finished only when the learner asks', function (): void {
    $session = stepTwoSession();
    $question = stepTwoQuestion($session);
    stepTwoRow($question, 'cafe', FeedbackType::Vocabulary, 'a place for coffee');
    stepTwoRow($question, 'relish', FeedbackType::Vocabulary, 'to enjoy');

    $review = stepTwoStartReview($this);

    stepTwoAdd($this, FeedbackType::Vocabulary, ['cafe'], $review->id)->assertOk();
    expect($review->fresh()?->status)->toBe(CustomReviewStatus::Draft);

    $this->putJson(route('customReviews.status', $review), ['status' => 'finished'])
        ->assertOk()
        ->assertJsonPath('review.status', 'finished')
        ->assertJsonPath('review.status_label', 'Finished');

    // Another item arrives, but only the learner moves a Finished review.
    stepTwoAdd($this, FeedbackType::Vocabulary, ['relish'], $review->id)->assertOk();
    expect($review->fresh()?->status)->toBe(CustomReviewStatus::Finished);

    $this->putJson(route('customReviews.status', $review), ['status' => 'draft'])
        ->assertOk()
        ->assertJsonPath('review.status', 'draft')
        ->assertJsonPath('review.status_label', 'Draft');

    expect($review->fresh()?->status)->toBe(CustomReviewStatus::Draft);
});

it('refuses to finish an empty review and refuses a status it does not own', function (): void {
    $review = CustomReview::factory()->create();

    $this->putJson(route('customReviews.status', $review), ['status' => 'finished'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.status.0', 'Add items before marking this review finished.');

    expect($review->fresh()?->status)->toBe(CustomReviewStatus::Empty);

    $this->putJson(route('customReviews.status', $review), ['status' => 'empty'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.status.0', 'Choose Draft or Finished.');

    $this->get(route('customReviews.show', $review))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('review.status', 'empty')
            ->where('review.status_label', 'Empty')
            ->where('add_items_url', route('collections.index', [
                'review' => $review->id,
                'eligible' => 1,
            ])));
});

it('carries the size tag past twenty items and drops it at twenty', function (): void {
    $session = stepTwoSession();
    $question = stepTwoQuestion($session);
    $words = array_map(fn (int $index): string => 'word'.$index, range(1, 21));

    foreach ($words as $word) {
        stepTwoRow($question, $word);
    }

    $review = stepTwoStartReview($this);

    // The size is advice: the add waits for a word, then goes through whole.
    stepTwoAdd($this, FeedbackType::Vocabulary, $words, $review->id)
        ->assertOk()
        ->assertJsonPath('outcome', 'needs_confirmation');

    stepTwoAdd($this, FeedbackType::Vocabulary, $words, $review->id, true)
        ->assertOk()
        ->assertJsonPath('outcome', 'added')
        ->assertJsonPath('total', 21)
        ->assertJsonPath('review.too_many_items', true)
        ->assertJsonPath('review.too_many_label', 'Too Many Items · 21 items');

    $this->get(route('customReviews.show', $review))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('review.item_count', 21)
            ->where('review.too_many_items', true)
            ->where('review.too_many_label', 'Too Many Items · 21 items'));

    $this->deleteJson(route('customReviews.items.destroy', $review), [
        'kind' => FeedbackType::Vocabulary->value,
        'identities' => ['word1'],
    ])->assertOk();

    $this->get(route('customReviews.show', $review))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('review.item_count', 20)
            ->where('review.too_many_items', false)
            ->where('review.too_many_label', null));
});

it('takes the client size threshold from the same number as the server', function (): void {
    $source = file_get_contents(resource_path('js/lib/custom-review.ts'));

    expect($source)->toBeString()
        ->and(preg_match('/export const RECOMMENDED_ITEMS = (\d+);/', (string) $source, $matches))
        ->toBe(1)
        ->and((int) $matches[1])->toBe(CustomReviewRules::RECOMMENDED_ITEMS);
});

it('says why each item cannot be used yet and keeps it in the review', function (): void {
    $session = stepTwoSession();
    $question = stepTwoQuestion($session);

    stepTwoRow($question, 'plain');
    stepTwoRow($question, 'clear', FeedbackType::Vocabulary, 'easy to see');

    // Two separators in one line, nothing split out: the row itself asks to be split.
    $splitLine = 'broken - also - here';
    $splitIdentity = SearchText::normalize($splitLine);
    stepTwoRow($question, $splitLine);

    stepTwoRow($question, 'I goed', FeedbackType::Mistake, '');

    $plainNote = stepTwoNote($question, '<p>Use the past tense here.</p>');
    $boldNote = stepTwoNote($question, '<p>Use <strong>went</strong> after yesterday.</p>');

    $questionWithoutNatural = stepTwoQuestion($session, 2, [
        'question_text' => 'What would you order?',
    ]);
    $questionWithNatural = stepTwoQuestion($session, 3, [
        'question_text' => 'Where did you go?',
    ]);
    stepTwoRow($questionWithNatural, 'I went home', FeedbackType::NaturalVersion);

    $archived = stepTwoSession('A past day', ['archived_at' => CarbonImmutable::now()]);
    stepTwoRow(stepTwoQuestion($archived), 'oldword');

    $review = stepTwoStartReview($this);

    stepTwoAdd($this, FeedbackType::Vocabulary, ['plain', 'clear', $splitIdentity, 'oldword'], $review->id)->assertOk();
    stepTwoAdd($this, FeedbackType::Mistake, [
        MistakeIdentity::pairKey('I goed', ''),
    ], $review->id)->assertOk();
    stepTwoAdd($this, FeedbackType::GrammarNote, [
        'note-'.$plainNote->id,
        'note-'.$boldNote->id,
    ], $review->id)->assertOk();
    stepTwoAdd($this, FeedbackType::Question, [
        (string) $questionWithoutNatural->id,
        (string) $questionWithNatural->id,
    ], $review->id)->assertOk();

    $this->get(route('customReviews.show', $review))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('review.item_count', 9)
            ->where('groups.0.items', function (Collection $items) use ($splitIdentity): bool {
                $by = stepTwoByIdentity($items);

                return $by['plain']['eligible'] === false
                    && $by['plain']['reason'] === CustomReviewEligibility::ADD_MEANING
                    && $by['clear']['eligible'] === true
                    && $by['clear']['reason'] === null
                    && $by[$splitIdentity]['reason'] === CustomReviewEligibility::SPLIT_CORRECTION
                    && $by['oldword']['eligible'] === false
                    && $by['oldword']['reason'] === CustomReviewEligibility::NOT_AVAILABLE;
            })
            ->where('groups.1.items', function (Collection $items) use ($plainNote, $boldNote): bool {
                $by = stepTwoByIdentity($items);

                return $by['note-'.$plainNote->id]['reason'] === CustomReviewEligibility::BOLD_KEY_PART
                    && $by['note-'.$boldNote->id]['eligible'] === true;
            })
            ->where('groups.2.items', function (Collection $items): bool {
                $by = stepTwoByIdentity($items);

                return $by[MistakeIdentity::pairKey('I goed', '')]['eligible'] === false
                    && $by[MistakeIdentity::pairKey('I goed', '')]['reason']
                        === CustomReviewEligibility::ADD_CORRECTION;
            })
            ->where('groups.3.items', function (Collection $items) use ($questionWithoutNatural, $questionWithNatural): bool {
                $by = stepTwoByIdentity($items);

                return $by[(string) $questionWithoutNatural->id]['reason']
                        === CustomReviewEligibility::ADD_NATURAL_VERSION
                    && $by[(string) $questionWithNatural->id]['eligible'] === true;
            }));
});

it('works eligibility out again whenever the review is opened', function (): void {
    $session = stepTwoSession();
    $question = stepTwoQuestion($session);
    $word = stepTwoRow($question, 'plain');

    $review = stepTwoStartReview($this);
    stepTwoAdd($this, FeedbackType::Vocabulary, ['plain'], $review->id)->assertOk();

    $this->get(route('customReviews.show', $review))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('groups.0.items.0.eligible', false)
            ->where('groups.0.items.0.reason', CustomReviewEligibility::ADD_MEANING));

    DB::table('practice_feedback_entries')
        ->where('id', $word->id)
        ->update([
            'correction_text' => 'easy to see',
            'correction_html' => '<p>easy to see</p>',
        ]);

    $this->get(route('customReviews.show', $review))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('review.item_count', 1)
            ->where('groups.0.items.0.eligible', true)
            ->where('groups.0.items.0.reason', null));
});

it('opens Collections narrowed to the items a review can use', function (): void {
    $session = stepTwoSession();
    $question = stepTwoQuestion($session);
    stepTwoRow($question, 'usable', FeedbackType::Vocabulary, 'easy to use');
    stepTwoRow($question, 'unusable');

    $review = stepTwoStartReview($this);

    $this->get(route('collections.index', [
        'tab' => 'vocabulary',
        'review' => $review->id,
        'eligible' => 1,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('collections/Index')
            ->where('review_context.id', $review->id)
            ->where('review_context.name', 'Custom Review 1')
            ->where('review_context.eligible', true)
            ->where('review_target.id', $review->id)
            ->where('items', fn (Collection $items): bool => $items->pluck('title')->all() === ['usable']));

    // The ordinary page still lists both, and claims no review context.
    $this->get(route('collections.index', ['tab' => 'vocabulary']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('review_context', null)
            ->has('items', 2));

    // An eligible ask with no review named follows the only review being built.
    $this->get(route('collections.index', ['tab' => 'vocabulary', 'eligible' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('review_context.id', $review->id)
            ->has('items', 1));

    // With two reviews under way there is nothing to agree on, so nothing narrows.
    CustomReview::factory()->draft()->create();

    $this->get(route('collections.index', ['tab' => 'vocabulary', 'eligible' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('review_context', null)
            ->has('items', 2));

    $this->getJson(route('collections.selection', [
        'tab' => 'vocabulary',
        'review' => $review->id,
        'eligible' => 1,
    ]))
        ->assertOk()
        ->assertExactJson(['keys' => ['usable'], 'total' => 1]);
});

it('adds from a finished review without asking which review again', function (): void {
    $session = stepTwoSession();
    $question = stepTwoQuestion($session);
    stepTwoRow($question, 'existing', FeedbackType::Vocabulary, 'already here');
    stepTwoRow($question, 'cafe', FeedbackType::Vocabulary, 'a place for coffee');

    $review = stepTwoStartReview($this);
    stepTwoAdd($this, FeedbackType::Vocabulary, ['existing'], $review->id)->assertOk();
    $this->putJson(route('customReviews.status', $review), ['status' => 'finished'])->assertOk();

    $this->get(route('collections.index', [
        'tab' => 'vocabulary',
        'review' => $review->id,
        'eligible' => 1,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('review_context.id', $review->id)
            ->where('review_target.status', 'finished'));

    stepTwoAdd($this, FeedbackType::Vocabulary, ['cafe'], $review->id)
        ->assertOk()
        ->assertJsonPath('outcome', 'added')
        ->assertJsonPath('total', 2);

    expect($review->fresh()?->status)->toBe(CustomReviewStatus::Finished);
});
