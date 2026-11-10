<?php

declare(strict_types=1);

use App\Enums\CustomReviewStatus;
use App\Models\CustomReview;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The creation dialog's own request: the name the learner kept or changed and
 * the kinds they left chosen.
 *
 * @param  list<string>  $kinds
 */
function dialogCreate(object $test, string $name, array $kinds): TestResponse
{
    return $test->post(route('customReviews.store'), [
        'name' => $name,
        'kinds' => $kinds,
    ]);
}

/**
 * Every kind a review can hold, in the collections' own values.
 *
 * @return list<string>
 */
function dialogAllKinds(): array
{
    return ['vocabulary', 'phrase', 'grammar_note', 'mistake', 'question'];
}

it('creates the review with the name and kinds the dialog sends', function (): void {
    dialogCreate($this, '  Café words  ', ['vocabulary', 'phrase'])
        ->assertRedirect();

    $review = CustomReview::query()->sole();

    expect($review->name)->toBe('Café words')
        ->and($review->allowed_kinds)->toBe(['vocabulary', 'phrase'])
        ->and($review->status)->toBe(CustomReviewStatus::Empty);

    $this->get(route('customReviews.show', $review))->assertInertia(fn (Assert $page): Assert => $page
        ->where('review.allowed_kinds_label', 'Words + Phrases')
    );

    // The narrowing the dialog asked for follows the review everywhere,
    // including the Collections page its Add items opens.
    $this->get(route('collections.index', [
        'review' => $review->id,
        'eligible' => 1,
    ]))->assertInertia(fn (Assert $page): Assert => $page
        ->has('tabs', 2)
        ->where('tabs.0.key', 'vocabulary')
        ->where('tabs.1.key', 'phrases')
    );
});

it('keeps the column empty when the dialog leaves every kind chosen', function (): void {
    dialogCreate($this, 'Everything', dialogAllKinds())->assertRedirect();

    $review = CustomReview::query()->sole();

    expect($review->allowed_kinds)->toBeNull();

    $this->get(route('customReviews.show', $review))->assertInertia(fn (Assert $page): Assert => $page
        ->where('review.allowed_kinds', null)
        ->where('review.allowed_kinds_label', 'All kinds')
    );
});

it('refuses a blank name and creates nothing', function (): void {
    dialogCreate($this, '   ', ['vocabulary'])
        ->assertSessionHasErrors(['name' => 'Give this review a name.']);

    expect(CustomReview::query()->count())->toBe(0);
});

it('refuses a name longer than a review may carry', function (): void {
    dialogCreate($this, str_repeat('a', 81), ['vocabulary'])
        ->assertSessionHasErrors(['name' => 'Keep the name to 80 characters or fewer.']);

    expect(CustomReview::query()->count())->toBe(0);
});

it('asks for at least one kind', function (?array $kinds): void {
    $this->post(route('customReviews.store'), [
        'name' => 'A review',
        'kinds' => $kinds,
    ])->assertSessionHasErrors(['kinds' => 'Choose at least one kind.']);

    expect(CustomReview::query()->count())->toBe(0);
})->with([
    'none chosen' => [[]],
    'nothing passed for the choice' => [null],
]);

it('refuses a kind a review cannot hold', function (): void {
    dialogCreate($this, 'A review', ['vocabulary', 'natural_version'])
        ->assertSessionHasErrors(['kinds.1' => 'Choose from the kinds a review can hold.']);

    expect(CustomReview::query()->count())->toBe(0);
});

it('starts a review on the app\'s own defaults when the request says nothing', function (): void {
    $this->post(route('customReviews.store'))->assertRedirect();

    $review = CustomReview::query()->sole();

    expect($review->name)->toBe('Custom Review 1')
        ->and($review->allowed_kinds)->toBeNull();
});

it('offers the next free name to the dialog', function (): void {
    CustomReview::factory()->create(['name' => 'Custom Review 1']);
    CustomReview::factory()->create(['name' => 'Custom Review 3']);

    $this->get(route('customReviews.index'))->assertInertia(fn (Assert $page): Assert => $page
        ->where('next_review_name', 'Custom Review 2')
    );
});
