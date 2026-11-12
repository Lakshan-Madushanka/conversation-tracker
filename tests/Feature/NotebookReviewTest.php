<?php

declare(strict_types=1);

use App\Actions\Collections\SaveItemMark;
use App\Enums\CollectionItemStatus;
use App\Enums\CustomReviewStatus;
use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use App\Models\CollectionItemMark;
use App\Models\CustomReview;
use App\Models\CustomReviewAttempt;
use App\Models\CustomReviewAttemptItem;
use App\Models\CustomReviewItem;
use App\Models\Notebook;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use App\Support\CustomReviewEligibility;
use App\Support\NotebookIdentity;
use App\Support\SearchText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One saved row of a notebook box.
 *
 * @param  array<string, mixed>  $attributes
 */
function nbReviewRow(
    Notebook $notebook,
    FeedbackType $type,
    string $subject,
    string $meaning = '',
    string $example = '',
    array $attributes = [],
): PracticeFeedbackEntry {
    $next = (int) $notebook->entries()->where('type', $type->value)->max('position') + 1;

    return PracticeFeedbackEntry::factory()
        ->for($notebook)
        ->create(array_merge([
            'practice_question_id' => null,
            'type' => $type,
            'position' => $next,
            'client_id' => (string) Str::uuid(),
            'content_html' => '',
            'content_text' => $subject,
            'correction_html' => '',
            'correction_text' => $meaning,
            'reason' => '',
            'example' => $example,
            'example_html' => [],
            'is_split' => true,
        ], $attributes));
}

/** A notebook word carrying a meaning, the simplest usable item. */
function nbReviewWord(Notebook $notebook, string $word, string $meaning = 'a meaning'): PracticeFeedbackEntry
{
    return nbReviewRow($notebook, FeedbackType::Vocabulary, $word, $meaning);
}

/** The scoped identity a notebook word carries. */
function nbReviewKey(Notebook $notebook, string $word): string
{
    return NotebookIdentity::wordKey($notebook->id, $word);
}

/** One word of a finished session, so another scope can hold the same spelling. */
function nbReviewSessionWord(string $word, string $meaning): PracticeFeedbackEntry
{
    $session = PracticeSession::factory()->finished()->for(Topic::factory())->create();
    $question = PracticeQuestion::factory()->for($session)->create(['position' => 1]);

    return PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => FeedbackType::Vocabulary->value,
        'content_text' => $word,
        'correction_text' => $meaning,
        'correction_html' => '<p>'.e($meaning).'</p>',
        'position' => 0,
        'is_split' => true,
    ]);
}

/**
 * The body a notebook creation carries.
 *
 * @param  list<string>|null  $identities
 * @param  list<string>|null  $kinds
 * @return array<string, mixed>
 */
function nbReviewBody(string $key, ?array $identities = null, ?array $kinds = null, bool $confirmed = false): array
{
    $body = ['creation_key' => $key, 'confirmed' => $confirmed];

    if ($identities !== null) {
        $body['identities'] = $identities;
    }

    if ($kinds !== null) {
        $body['kinds'] = $kinds;
    }

    return $body;
}

/** Every identity the candidates endpoint would offer for a notebook. */
function nbReviewIdentities(Notebook $notebook): array
{
    return array_column(
        test()->getJson(route('notebooks.reviewCandidates', $notebook))->json('candidates'),
        'identity',
    );
}

it('offers one candidate per scoped notebook item with deterministic content and eligibility', function (): void {
    $notebook = Notebook::factory()->create(['name' => 'Kitchen words']);

    // The first saved row is incomplete; the second carries the meaning. The
    // item is usable because some row has a meaning, and shows the first row
    // that does, never a definition borrowed from another scope.
    nbReviewRow($notebook, FeedbackType::Vocabulary, 'bank', '', 'I sat by the bank.');
    nbReviewRow($notebook, FeedbackType::Vocabulary, 'bank', 'river side', 'The river bank.');
    nbReviewRow($notebook, FeedbackType::Phrase, 'in a row', 'one after another');
    nbReviewRow($notebook, FeedbackType::Phrase, 'for good', '');
    nbReviewRow($notebook, FeedbackType::GrammarNote, 'Use the past tense here.');

    nbReviewSessionWord('bank', 'money in a bank');
    Notebook::factory()->create(['name' => 'Business']); // same spelling elsewhere

    $response = $this->getJson(route('notebooks.reviewCandidates', $notebook))->assertOk();

    expect($response->json('count'))->toBe(4)
        ->and($response->json('eligible_count'))->toBe(2)
        ->and($response->json('maximum'))->toBe(50)
        ->and($response->json('recommended'))->toBe(20);

    $candidates = collect($response->json('candidates'));
    $bank = $candidates->firstWhere('identity', nbReviewKey($notebook, 'bank'));

    expect($bank)->not->toBeNull()
        ->and($bank['title'])->toBe('bank')
        ->and($bank['meaning'])->toBe('river side')
        ->and($bank['example'])->toBe('I sat by the bank.')
        ->and($bank['eligible'])->toBeTrue()
        ->and($bank['identity'])->not->toBe(SearchText::normalize('bank'));

    $phrase = $candidates->firstWhere('identity', nbReviewKey($notebook, 'for good'));

    expect($phrase['eligible'])->toBeFalse()
        ->and($phrase['reason'])->toBe(CustomReviewEligibility::ADD_MEANING);

    $note = $candidates->firstWhere('kind', FeedbackType::GrammarNote->value);

    expect($note['eligible'])->toBeFalse()
        ->and($note['reason'])->toBe(CustomReviewEligibility::BOLD_KEY_PART);
});

it('creates a Finished review and starts an attempt from one eligible notebook item', function (): void {
    $notebook = Notebook::factory()->create(['name' => 'Kitchen words']);
    $row = nbReviewWord($notebook, 'bank', 'river side');

    $response = $this->postJson(route('notebooks.review.store', $notebook), nbReviewBody('create-one'))
        ->assertOk()
        ->assertJsonPath('outcome', 'created');

    $review = CustomReview::query()->sole();

    expect($review->status)->toBe(CustomReviewStatus::Finished)
        ->and($review->allowed_kinds)->toBeNull()
        ->and(CustomReviewItem::query()->where('custom_review_id', $review->id)->count())->toBe(1);

    $attempt = CustomReviewAttempt::query()->sole();
    $item = $attempt->items()->sole();

    expect($item->snapshot_text)->toBe('bank')
        ->and($item->snapshot['front'])->toBe('bank')
        ->and($item->snapshot['meaning'])->toBe('river side')
        ->and($item->snapshot['source_kind'])->toBe('notebook')
        ->and($item->source_session_id)->toBeNull()
        ->and($item->source_url)->toContain('/notebooks/'.$notebook->id)
        ->and($item->source_url)->toContain('entry='.$row->client_id)
        ->and($response->json('attempt_url'))->toBe(route('customReviews.attempts.show', [
            'customReview' => $review->id,
            'attempt' => $attempt->id,
        ]));

    // The attempt screen shows the notebook's own words and opens the
    // notebook, never a session that happens to hold the same spelling.
    $this->get(route('customReviews.attempts.show', [
        'customReview' => $review->id,
        'attempt' => $attempt->id,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('custom-reviews/Attempt')
            ->where('current.card.source_kind', 'notebook')
            ->where('current.card.front', 'bank')
            ->where('current.card.meaning', 'river side')
            ->where('current.card.session_url', fn (string $url): bool => str_contains($url, '/notebooks/'.$notebook->id)));
});

it('still starts an ordinary review empty, so only the notebook shortcut marks one ready', function (): void {
    $this->post(route('customReviews.store'), ['name' => 'Ordinary review'])
        ->assertRedirect();

    $review = CustomReview::query()->sole();

    expect($review->status)->toBe(CustomReviewStatus::Empty)
        ->and($review->creation_key)->toBeNull();
});

it('warns above twenty items, allows twenty without a warning, and allows fifty when confirmed', function (): void {
    $twenty = Notebook::factory()->create(['name' => 'Twenty']);
    $twentyOne = Notebook::factory()->create(['name' => 'Twenty one']);
    $fifty = Notebook::factory()->create(['name' => 'Fifty']);

    foreach (range(1, 20) as $number) {
        nbReviewWord($twenty, 'word '.$number);
        nbReviewWord($twentyOne, 'word '.$number);
        nbReviewWord($fifty, 'word '.$number);
    }

    nbReviewWord($twentyOne, 'word 21');

    foreach (range(21, 50) as $number) {
        nbReviewWord($fifty, 'word '.$number);
    }

    $this->postJson(route('notebooks.review.store', $twenty), nbReviewBody('twenty'))
        ->assertOk()
        ->assertJsonPath('outcome', 'created');

    $this->postJson(route('notebooks.review.store', $twentyOne), nbReviewBody('twenty-one'))
        ->assertOk()
        ->assertJsonPath('outcome', 'needs_confirmation')
        ->assertJsonPath('count', 21);

    expect(CustomReview::query()->where('creation_key', 'twenty-one')->exists())->toBeFalse();

    $this->postJson(route('notebooks.review.store', $twentyOne), nbReviewBody('twenty-one', confirmed: true))
        ->assertOk()
        ->assertJsonPath('outcome', 'created');

    $this->postJson(route('notebooks.review.store', $fifty), nbReviewBody('fifty', confirmed: true))
        ->assertOk()
        ->assertJsonPath('outcome', 'created');

    expect(CustomReviewItem::query()->where('custom_review_id', CustomReview::query()->where('creation_key', 'fifty')->sole()->id)->count())
        ->toBe(50);
});

it('opens selection over fifty items, refuses fifty-one, and creates a review from fifty', function (): void {
    $notebook = Notebook::factory()->create(['name' => 'Fifty one']);

    foreach (range(1, 51) as $number) {
        nbReviewWord($notebook, 'word '.$number);
    }

    $identities = nbReviewIdentities($notebook);

    expect($identities)->toHaveCount(51);

    $this->postJson(route('notebooks.review.store', $notebook), nbReviewBody('fifty-one'))
        ->assertOk()
        ->assertJsonPath('outcome', 'needs_selection')
        ->assertJsonPath('count', 51)
        ->assertJsonPath('maximum', 50);

    expect(CustomReview::query()->count())->toBe(0);

    $this->postJson(route('notebooks.review.store', $notebook), nbReviewBody('fifty-one', $identities, confirmed: true))
        ->assertOk()
        ->assertJsonPath('outcome', 'too_many_selected')
        ->assertJsonPath('maximum', 50);

    expect(CustomReview::query()->count())->toBe(0);

    $chosen = array_slice($identities, 0, 50);

    $this->postJson(route('notebooks.review.store', $notebook), nbReviewBody('fifty-one', $chosen, confirmed: true))
        ->assertOk()
        ->assertJsonPath('outcome', 'created');

    $review = CustomReview::query()->sole();

    expect(CustomReviewItem::query()->where('custom_review_id', $review->id)->count())->toBe(50)
        ->and(CustomReviewAttempt::query()->sole()->items()->count())->toBe(50);
});

it('counts duplicate saved rows once, so many rows can still fit fifty identities', function (): void {
    $notebook = Notebook::factory()->create();

    foreach (range(1, 25) as $number) {
        nbReviewWord($notebook, 'word '.$number);
        nbReviewWord($notebook, 'word '.$number);
    }

    expect($notebook->entries()->count())->toBe(50)
        ->and(nbReviewIdentities($notebook))->toHaveCount(25);

    $this->postJson(route('notebooks.review.store', $notebook), nbReviewBody('duplicates', confirmed: true))
        ->assertOk()
        ->assertJsonPath('outcome', 'created');

    expect(CustomReviewItem::query()->count())->toBe(25);
});

it('refuses to start when nothing chosen is eligible and names what to fix', function (): void {
    $notebook = Notebook::factory()->create();

    nbReviewRow($notebook, FeedbackType::Vocabulary, 'bank', '');
    nbReviewRow($notebook, FeedbackType::GrammarNote, 'Just a sentence.');

    $this->postJson(route('notebooks.review.store', $notebook), nbReviewBody('nothing-usable'))
        ->assertOk()
        ->assertJsonPath('outcome', 'no_eligible')
        ->assertJsonPath('count', 2);

    expect(CustomReview::query()->count())->toBe(0)
        ->and(CustomReviewAttempt::query()->count())->toBe(0);
});

it('retries the same creation without duplicating the review or the attempt', function (): void {
    $notebook = Notebook::factory()->create();
    nbReviewWord($notebook, 'bank');

    $first = $this->postJson(route('notebooks.review.store', $notebook), nbReviewBody('same-press'))
        ->assertOk()
        ->assertJsonPath('outcome', 'created');

    $second = $this->postJson(route('notebooks.review.store', $notebook), nbReviewBody('same-press'))
        ->assertOk()
        ->assertJsonPath('outcome', 'created');

    expect(CustomReview::query()->count())->toBe(1)
        ->and(CustomReviewAttempt::query()->count())->toBe(1)
        ->and($second->json('review_id'))->toBe($first->json('review_id'))
        ->and($second->json('attempt_url'))->toBe($first->json('attempt_url'));
});

it('honours the kinds the learner chooses and never infers them from populated boxes', function (): void {
    $notebook = Notebook::factory()->create();

    nbReviewWord($notebook, 'bank');
    nbReviewRow($notebook, FeedbackType::GrammarNote, 'Use the <strong>past tense</strong> here.', '', '', [
        'content_html' => '<p>Use the <strong>past tense</strong> here.</p>',
    ]);

    $this->getJson(route('notebooks.reviewCandidates', ['notebook' => $notebook, 'kinds' => ['grammar_note']]))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('candidates.0.kind', 'grammar_note');

    $this->postJson(route('notebooks.review.store', $notebook), nbReviewBody('notes-only', kinds: ['grammar_note']))
        ->assertOk()
        ->assertJsonPath('outcome', 'created');

    $review = CustomReview::query()->sole();

    expect($review->allowed_kinds)->toBe(['grammar_note'])
        ->and(CustomReviewItem::query()->where('custom_review_id', $review->id)->pluck('kind')->all())
        ->toEqual([FeedbackType::GrammarNote]);

    $second = Notebook::factory()->create(['name' => 'Everything']);

    nbReviewWord($second, 'table');
    nbReviewRow($second, FeedbackType::GrammarNote, 'Use the <strong>past tense</strong> here.', '', '', [
        'content_html' => '<p>Use the <strong>past tense</strong> here.</p>',
    ]);

    $this->postJson(route('notebooks.review.store', $second), nbReviewBody('every-kind'))
        ->assertOk()
        ->assertJsonPath('outcome', 'created');

    $all = CustomReview::query()->where('creation_key', 'every-kind')->sole();

    expect($all->allowed_kinds)->toBeNull()
        ->and(CustomReviewItem::query()->where('custom_review_id', $all->id)->count())->toBe(2);
});

it('keeps fixed membership when the notebook gains a word later', function (): void {
    $notebook = Notebook::factory()->create();
    nbReviewWord($notebook, 'bank');

    $this->postJson(route('notebooks.review.store', $notebook), nbReviewBody('fixed'))
        ->assertOk()
        ->assertJsonPath('outcome', 'created');

    $review = CustomReview::query()->sole();

    nbReviewWord($notebook, 'counter');

    expect(CustomReviewItem::query()->where('custom_review_id', $review->id)->count())->toBe(1)
        ->and(CustomReviewAttempt::query()->sole()->items()->count())->toBe(1);

    $this->postJson(route('customReviews.items.store'), [
        'kind' => FeedbackType::Vocabulary->value,
        'identities' => [nbReviewKey($notebook, 'counter')],
        'review' => $review->id,
    ])->assertOk()->assertJsonPath('outcome', 'added');

    expect(CustomReviewItem::query()->where('custom_review_id', $review->id)->count())->toBe(2);
});

it('shows edited notebook content to a later attempt while an attempt keeps its snapshot', function (): void {
    $notebook = Notebook::factory()->create();
    $row = nbReviewWord($notebook, 'bank', 'river side');

    $this->postJson(route('notebooks.review.store', $notebook), nbReviewBody('live-content'))
        ->assertOk()
        ->assertJsonPath('outcome', 'created');

    $review = CustomReview::query()->sole();
    $first = CustomReviewAttempt::query()->sole();
    $identity = nbReviewKey($notebook, 'bank');

    $this->post(route('customReviews.attempts.rate', [$review, $first]), [
        'kind' => FeedbackType::Vocabulary->value,
        'item_identity' => $identity,
        'rating' => ReviewRating::Easy->value,
    ])->assertRedirect();

    $this->post(route('customReviews.attempts.finishEarly', [$review, $first]))->assertRedirect();

    $row->update(['correction_text' => 'edge of a river']);

    $this->post(route('customReviews.attempts.store', $review))->assertRedirect();

    $second = CustomReviewAttempt::query()->where('number', 2)->sole();

    expect($first->items()->sole()->snapshot['meaning'])->toBe('river side')
        ->and($second->items()->sole()->snapshot['meaning'])->toBe('edge of a river');
});

it('shows the notebook item on the review page with its notebook source', function (): void {
    $notebook = Notebook::factory()->create(['name' => 'Kitchen words']);
    $row = nbReviewWord($notebook, 'bank', 'river side');

    $this->postJson(route('notebooks.review.store', $notebook), nbReviewBody('show-page'))
        ->assertOk()
        ->assertJsonPath('outcome', 'created');

    $review = CustomReview::query()->sole();

    $this->get(route('customReviews.show', $review))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('custom-reviews/Show')
            ->where('groups.0.key', FeedbackType::Vocabulary->value)
            ->where('groups.0.label', 'Words')
            ->where('groups.0.items.0.title', 'bank')
            ->where('groups.0.items.0.source_kind', 'notebook')
            ->where('groups.0.items.0.notebook.name', 'Kitchen words')
            ->where('groups.0.items.0.session_date', null)
            ->where('groups.0.items.0.available', true)
            ->where('groups.0.items.0.eligible', true)
            ->where('groups.0.items.0.href', fn (string $href): bool => str_contains($href, '/notebooks/'.$notebook->id)
                && str_contains($href, 'entry='.$row->client_id)));
});

it('never answers a deleted notebook item from a matching session word', function (): void {
    $notebook = Notebook::factory()->create();
    $row = nbReviewWord($notebook, 'bank', 'river side');
    nbReviewSessionWord('bank', 'money in a bank');

    $this->postJson(route('notebooks.review.store', $notebook), nbReviewBody('deleted-source'))
        ->assertOk()
        ->assertJsonPath('outcome', 'created');

    $review = CustomReview::query()->sole();
    $first = CustomReviewAttempt::query()->sole();

    $this->post(route('customReviews.attempts.rate', [$review, $first]), [
        'kind' => FeedbackType::Vocabulary->value,
        'item_identity' => nbReviewKey($notebook, 'bank'),
        'rating' => ReviewRating::Easy->value,
    ])->assertRedirect();

    $this->post(route('customReviews.attempts.finishEarly', [$review, $first]))->assertRedirect();

    $row->delete();

    $this->get(route('customReviews.show', $review))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('custom-reviews/Show')->where('groups', []));

    expect(CustomReviewItem::query()->count())->toBe(0)
        ->and(CustomReviewAttemptItem::query()->sole()->is_deleted)->toBeTrue()
        ->and(CustomReviewAttemptItem::query()->sole()->snapshot_text)->toBe('bank');

    $this->post(route('customReviews.attempts.store', $review))
        ->assertRedirect(route('customReviews.show', $review));

    expect(CustomReviewAttempt::query()->count())->toBe(1);
});

it('leaves scoped marks and daily schedules untouched when a notebook attempt is rated', function (): void {
    $notebook = Notebook::factory()->create();
    nbReviewWord($notebook, 'bank');
    $identity = nbReviewKey($notebook, 'bank');

    app(SaveItemMark::class)->handle(
        FeedbackType::Vocabulary,
        $identity,
        true,
        CollectionItemStatus::Learned,
    );

    $this->postJson(route('notebooks.review.store', $notebook), nbReviewBody('isolation'))
        ->assertOk()
        ->assertJsonPath('outcome', 'created');

    $review = CustomReview::query()->sole();

    $this->post(route('customReviews.attempts.rate', [$review, CustomReviewAttempt::query()->sole()]), [
        'kind' => FeedbackType::Vocabulary->value,
        'item_identity' => $identity,
        'rating' => ReviewRating::Easy->value,
    ])->assertRedirect();

    $mark = CollectionItemMark::query()
        ->where('kind', FeedbackType::Vocabulary->value)
        ->where('group_key', $identity)
        ->sole();

    expect($mark->starred)->toBeTrue()
        ->and($mark->status)->toBe(CollectionItemStatus::Learned)
        ->and(DB::table('review_schedules')->count())->toBe(0);
});
