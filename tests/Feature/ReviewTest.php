<?php

declare(strict_types=1);

use App\Actions\Collections\MoveMarkAfterEdit;
use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Rating;
use App\Models\Review;
use App\Models\Topic;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A finished session the learner can study from.
 */
function reviewSession(): PracticeSession
{
    return PracticeSession::factory()
        ->finished()
        ->for(Topic::factory()->create())
        ->create([
            'created_at' => now(),
            'finished_at' => now(),
        ]);
}

/**
 * One table row, saved the way the editor saves a word or a phrase.
 */
function reviewRow(
    PracticeSession $session,
    string $subject,
    string $meaning,
    FeedbackType $type = FeedbackType::Vocabulary,
    string $example = '',
): PracticeFeedbackEntry {
    $question = PracticeQuestion::factory()->for($session)->create([
        'position' => $session->questions()->count() + 1,
    ]);

    return PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => $type,
        'content_text' => $subject,
        'content_html' => $subject,
        'correction_text' => $meaning,
        'correction_html' => $meaning,
        'example' => $example,
        'example_html' => [],
        'reason' => '',
        'position' => 0,
        'is_split' => true,
    ]);
}

/**
 * Start a review through the screen's own button and return the sitting it made.
 */
function reviewStart(object $test): Review
{
    $test->post(route('review.store'))->assertRedirect();

    /** @var Review $review */
    $review = Review::query()->latest('id')->firstOrFail();

    return $review;
}

describe('Review Home', function (): void {
    it('offers the words and phrases that carry a meaning', function (): void {
        $session = reviewSession();
        reviewRow($session, 'serendipity', 'finding something good by chance');
        reviewRow($session, 'break the ice', 'start a conversation', FeedbackType::Phrase);
        reviewRow($session, 'aimless', '');
        reviewRow($session, 'a mistake', 'a correction', FeedbackType::Mistake);

        $this->get(route('review.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('review/Index')
                ->where('todayReview.state', 'suggest')
                ->where('todayReview.count', 2));
    });

    it('says so when no session has been finished yet', function (): void {
        PracticeSession::factory()->create();

        $this->get(route('review.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('review/Index')
                ->where('todayReview.state', 'empty'));
    });

    it('leaves unfinished and archived sessions out', function (): void {
        reviewRow(PracticeSession::factory()->create(), 'unfinished', 'not ready');

        $archived = PracticeSession::factory()->archived()->create();
        reviewRow($archived, 'archived', 'put away');

        $finished = reviewSession();
        reviewRow($finished, 'visible', 'ready to study');

        $this->get(route('review.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'suggest')
                ->where('todayReview.count', 1));

        $review = reviewStart($this);

        expect($review->items)->toHaveCount(1)
            ->and($review->items->first()->item_identity)->toBe('visible');
    });
});

describe('the exercise screen', function (): void {
    it('shows the word first and keeps the meaning for Show answer', function (): void {
        $session = reviewSession();
        reviewRow($session, 'serendipity', 'finding something good by chance', example: 'A serendipity moment.');

        $review = reviewStart($this);

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('review/Session')
                ->where('notFound', false)
                ->where('finished', false)
                ->where('card.front', 'serendipity')
                ->where('card.meaning', 'finding something good by chance')
                ->where('card.example', 'A serendipity moment.')
                ->where('card.label', 'Word')
                ->where('position.current', 1)
                ->where('position.total', 1));
    });

    it('returns to the next item not yet rated after a refresh', function (): void {
        $session = reviewSession();
        reviewRow($session, 'one', 'first meaning');
        reviewRow($session, 'two', 'second meaning');

        $review = reviewStart($this);
        $first = $review->items->first();
        $second = $review->items->last();

        $this->post(route('review.rate', ['review' => $review->id]), [
            'kind' => $first->kind->value,
            'item_identity' => $first->item_identity,
            'rating' => ReviewRating::Easy->value,
        ])->assertRedirect(route('review.session', ['review' => $review->id, 'step' => 2]));

        // A refresh is the same request the screen makes.
        $this->get(route('review.session', ['review' => $review->id]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('card.item_identity', $second->item_identity)
                ->where('position.current', 2)
                ->where('position.total', 2));
    });

    it('shows a friendly message for an unknown review', function (): void {
        $this->get(route('review.session', ['review' => 999]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('review/Session')
                ->where('notFound', true)
                ->where('card', null));
    });

    it('skips an item whose session was archived during the review', function (): void {
        $kept = reviewSession();
        reviewRow($kept, 'still here', 'a meaning');

        $putAway = reviewSession();
        reviewRow($putAway, 'gone away', 'a meaning');

        $review = reviewStart($this);
        $putAway->archived_at = now();
        $putAway->save();

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('finished', false)
                ->where('card.front', 'still here')
                ->has('steps', 1)
                ->where('position.total', 1));
    });

    it('shows every item as a step the learner can open in any order', function (): void {
        $session = reviewSession();
        reviewRow($session, 'one', 'first meaning');
        reviewRow($session, 'two', 'second meaning');
        reviewRow($session, 'three', 'third meaning');

        $review = reviewStart($this);
        $identities = $review->items->pluck('item_identity')->all();

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('review/Session')
                ->where('currentStep', 1)
                ->where('card.front', $identities[0])
                ->where('cardRating', null)
                ->has('steps', 3)
                ->where('steps.0.number', 1)
                ->where('steps.0.card.front', $identities[0])
                ->where('steps.0.url', route('review.session', ['review' => $review->id, 'step' => 1]))
                ->where('steps.2.number', 3)
                ->where('steps.2.card.front', $identities[2]));

        // The address names the step, so any step opens before the ones
        // before it are rated.
        $this->get(route('review.session', ['review' => $review->id, 'step' => 3]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('currentStep', 3)
                ->where('card.front', $identities[2])
                ->where('position.current', 3));
    });

    it('lets the learner rate a later step before the earlier ones', function (): void {
        $session = reviewSession();
        reviewRow($session, 'one', 'first meaning');
        reviewRow($session, 'two', 'second meaning');
        reviewRow($session, 'three', 'third meaning');

        $review = reviewStart($this);
        $third = $review->items->last();

        $this->post(route('review.rate', ['review' => $review->id]), [
            'kind' => $third->kind->value,
            'item_identity' => $third->item_identity,
            'rating' => ReviewRating::Easy->value,
        ])->assertRedirect(route('review.session', ['review' => $review->id, 'step' => 1]));

        expect(Rating::query()->sole()->item_identity)->toBe($third->item_identity);

        // The rated step still opens, showing the rating it was given.
        $this->get(route('review.session', ['review' => $review->id, 'step' => 3]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('cardRating', 'easy')
                ->where('steps.2.rating', 'easy')
                ->where('steps.0.rating', null));

        // Taking the rating back reopens the same step, ready to rate again.
        $this->delete(route('review.undo', ['review' => $review->id]), [
            'kind' => $third->kind->value,
            'item_identity' => $third->item_identity,
        ])->assertRedirect(route('review.session', ['review' => $review->id, 'step' => 3]));

        expect(Rating::query()->count())->toBe(0);
    });
});

describe('ratings', function (): void {
    it('counts a repeated rating once', function (): void {
        $session = reviewSession();
        reviewRow($session, 'serendipity', 'finding something good by chance');

        $review = reviewStart($this);
        $item = $review->items->sole();

        $payload = [
            'kind' => $item->kind->value,
            'item_identity' => $item->item_identity,
            'rating' => ReviewRating::Okay->value,
        ];

        $this->post(route('review.rate', ['review' => $review->id]), $payload)->assertRedirect();
        $this->post(route('review.rate', ['review' => $review->id]), $payload)->assertRedirect();

        expect(Rating::query()->count())->toBe(1)
            ->and(Rating::query()->sole()->rating)->toBe(ReviewRating::Okay);
    });

    it('takes back the last rating', function (): void {
        $session = reviewSession();
        reviewRow($session, 'serendipity', 'finding something good by chance');

        $review = reviewStart($this);
        $item = $review->items->sole();

        $this->post(route('review.rate', ['review' => $review->id]), [
            'kind' => $item->kind->value,
            'item_identity' => $item->item_identity,
            'rating' => ReviewRating::Hard->value,
        ]);

        $this->delete(route('review.undo', ['review' => $review->id]), [
            'kind' => $item->kind->value,
            'item_identity' => $item->item_identity,
        ])->assertRedirect(route('review.session', ['review' => $review->id, 'step' => 1]));

        expect(Rating::query()->count())->toBe(0);

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('finished', false)
                ->where('card.item_identity', $item->item_identity));
    });

    it('refuses a rating for an item outside the review', function (): void {
        $session = reviewSession();
        reviewRow($session, 'serendipity', 'finding something good by chance');

        $review = reviewStart($this);

        $this->post(route('review.rate', ['review' => $review->id]), [
            'kind' => FeedbackType::Vocabulary->value,
            'item_identity' => 'something-else',
            'rating' => ReviewRating::Easy->value,
        ])->assertNotFound();

        expect(Rating::query()->count())->toBe(0);
    });

    it('shows the finish screen with an encouraging summary', function (): void {
        $session = reviewSession();
        reviewRow($session, 'one', 'first meaning');
        reviewRow($session, 'two', 'second meaning');

        $review = reviewStart($this);

        $ratings = [
            ReviewRating::Easy,
            ReviewRating::Hard,
        ];

        foreach ($review->items as $index => $item) {
            $this->post(route('review.rate', ['review' => $review->id]), [
                'kind' => $item->kind->value,
                'item_identity' => $item->item_identity,
                'rating' => $ratings[$index]->value,
            ]);
        }

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('finished', true)
                ->where('reviewedCount', 2)
                ->where('counts.easy', 1)
                ->where('counts.okay', 0)
                ->where('counts.hard', 1));
    });
});

describe('choosing the items', function (): void {
    it('never reviews more than eight at a time', function (): void {
        $session = reviewSession();

        foreach (range(1, 10) as $number) {
            reviewRow($session, "word {$number}", "meaning {$number}");
        }

        $review = reviewStart($this);

        expect($review->items)->toHaveCount(8);
    });

    it('offers the never-reviewed items when nothing is due yet', function (): void {
        $session = reviewSession();

        foreach (range(1, 10) as $number) {
            reviewRow($session, "word {$number}", "meaning {$number}");
        }

        $first = reviewStart($this);

        // Every item in the first review is rated, leaving two never reviewed.
        foreach ($first->items as $item) {
            $this->post(route('review.rate', ['review' => $first->id]), [
                'kind' => $item->kind->value,
                'item_identity' => $item->item_identity,
                'rating' => ReviewRating::Easy->value,
            ]);
        }

        $firstIdentities = $first->items->pluck('item_identity')->all();

        $second = reviewStart($this);

        // Easy ratings are a week away, so only the never-reviewed items
        // are worth revisiting today. Hard ones would come back tomorrow.
        expect($second->items)->toHaveCount(2);

        $second->items->each(function ($item) use ($firstIdentities): void {
            expect($firstIdentities)->not->toContain($item->item_identity);
        });
    });
});

describe('the typo rule for ratings', function (): void {
    it('moves a rating with a word fixed nowhere else', function (): void {
        $session = reviewSession();
        $entry = reviewRow($session, 'teh', 'slow to act');

        $review = reviewStart($this);
        $item = $review->items->sole();

        $this->post(route('review.rate', ['review' => $review->id]), [
            'kind' => $item->kind->value,
            'item_identity' => $item->item_identity,
            'rating' => ReviewRating::Hard->value,
        ]);

        $entry->update(['content_text' => 'the']);

        app(MoveMarkAfterEdit::class)->handle(
            FeedbackType::Vocabulary,
            'teh',
            'the',
            $entry->id,
        );

        expect(Rating::query()->sole()->item_identity)->toBe('the');
    });

    it('keeps a rating when the old word still appears elsewhere', function (): void {
        $first = reviewSession();
        $entry = reviewRow($first, 'teh', 'slow to act');

        reviewRow(reviewSession(), 'teh', 'slow to act');

        $review = reviewStart($this);
        $item = $review->items->sole();

        $this->post(route('review.rate', ['review' => $review->id]), [
            'kind' => $item->kind->value,
            'item_identity' => $item->item_identity,
            'rating' => ReviewRating::Hard->value,
        ]);

        $entry->update(['content_text' => 'the']);

        app(MoveMarkAfterEdit::class)->handle(
            FeedbackType::Vocabulary,
            'teh',
            'the',
            $entry->id,
        );

        expect(Rating::query()->sole()->item_identity)->toBe('teh');
    });
});
