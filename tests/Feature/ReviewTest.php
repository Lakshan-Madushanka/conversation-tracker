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
use App\Models\ReviewItem;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
 * One table row, saved the way the editor saves a word, a phrase or a mistake.
 */
function reviewRow(
    PracticeSession $session,
    string $subject,
    string $meaning,
    FeedbackType $type = FeedbackType::Vocabulary,
    string $example = '',
    string $reason = '',
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
        'reason' => $reason,
        'position' => 0,
        'is_split' => true,
        'client_id' => (string) Str::uuid(),
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

/**
 * The review items of one saved kind, in the order the learner meets them.
 *
 * @return Collection<int, ReviewItem>
 */
function reviewItemsOf(Review $review, FeedbackType $kind): Collection
{
    return $review->items
        ->filter(fn (ReviewItem $item): bool => $item->kind === $kind)
        ->values();
}

describe('Review Home', function (): void {
    it('offers the words, phrases and mistakes that carry a meaning', function (): void {
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
                ->where('todayReview.count', 3)
                // Two items share the flashcard step; the mistake has its own.
                ->where('todayReview.mix', '2 flashcards, 1 mistake to fix')
                ->where('todayReview.minutes', 3));
    });

    it('leaves a mistake without a correction for the collections', function (): void {
        $session = reviewSession();
        reviewRow($session, 'I has went', '', FeedbackType::Mistake);
        reviewRow($session, 'visible', 'ready to study');

        $this->get(route('review.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'suggest')
                ->where('todayReview.count', 1));

        $review = reviewStart($this);

        expect($review->items)->toHaveCount(1)
            ->and($review->items->sole()->kind)->toBe(FeedbackType::Vocabulary);
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
                ->where('current.step', 'flashcard')
                ->where('current.card.front', 'serendipity')
                ->where('current.card.meaning', 'finding something good by chance')
                ->where('current.card.example', 'A serendipity moment.')
                ->where('current.card.label', 'Word')
                ->where('position.current', 0)
                ->where('position.total', 1));
    });

    it('shows the mistake as it was said with its correction', function (): void {
        $session = reviewSession();
        reviewRow(
            $session,
            'I has went there.',
            'I have gone there.',
            FeedbackType::Mistake,
            reason: 'Use the past participle after “have”.',
        );

        $review = reviewStart($this);

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('review/Session')
                ->where('current.step', 'mistake')
                ->where('current.card.exercise', 'mistake')
                ->where('current.card.label', 'Fix the mistake')
                ->where('current.card.said', 'I has went there.')
                ->where('current.card.correction_html', 'I have gone there.')
                ->where('current.card.reason', 'Use the past participle after “have”.')
                ->where('current.card.more_reasons', [])
                ->where('current.rating', null));
    });

    it('keeps only bold and italic in the correction', function (): void {
        $session = reviewSession();
        $entry = reviewRow($session, 'Say the thing.', 'Say <b>the</b> thing.', FeedbackType::Mistake);
        $entry->update(['correction_html' => 'Say <b>the</b> thing.<script>alert(1)</script>']);

        $review = reviewStart($this);

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('current.card.correction_html', 'Say <strong>the</strong> thing.'));
    });

    it('shows the most recent reason and keeps the older ones under More notes', function (): void {
        $correction = 'I have gone there.';
        $said = 'I has went there.';

        $old = reviewSession();
        reviewRow($old, $said, $correction, FeedbackType::Mistake, reason: 'Use the past participle.');

        $middle = reviewSession();
        reviewRow($middle, $said, $correction, FeedbackType::Mistake, reason: 'Say it out loud as well.');

        // The newest appearance carries no reason, so the newest reason still
        // explains the mistake, and the older one waits under More notes.
        $newest = reviewSession();
        reviewRow($newest, $said, $correction, FeedbackType::Mistake);

        $review = reviewStart($this);

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('current.card.reason', 'Say it out loud as well.')
                ->where('current.card.more_reasons', ['Use the past participle.']));
    });

    it('links to the original session at the mistake row', function (): void {
        $session = reviewSession();
        $entry = reviewRow($session, 'I has went there.', 'I have gone there.', FeedbackType::Mistake);

        $review = reviewStart($this);

        $expected = route('sessions.show', [
            'session' => $session->id,
            'from' => 'collections',
            'question' => '1',
            'field' => 'entry',
            'type' => 'mistake',
            'position' => '0',
            'entry' => (string) $entry->client_id,
        ]);

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('current.card.session_url', $expected));
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
        ])->assertRedirect(route('review.session', [
            'review' => $review->id,
            'step' => 'flashcard',
            'position' => $second->position,
        ]));

        // A refresh is the same request the screen makes.
        $this->get(route('review.session', ['review' => $review->id]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('current.card.item_identity', $second->item_identity)
                ->where('position.current', 1)
                ->where('position.total', 2));
    });

    it('shows a friendly message for an unknown review', function (): void {
        $this->get(route('review.session', ['review' => 999]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('review/Session')
                ->where('notFound', true)
                ->where('current', null)
                ->has('sections', 0));
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
                ->where('current.card.front', 'still here')
                ->has('sections', 1)
                ->where('sections.0.total', 1)
                ->where('position.total', 1));
    });

    it('shows one step for a review that holds a single kind', function (): void {
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
                ->has('sections', 1)
                ->where('sections.0.key', 'flashcard')
                ->where('sections.0.label', 'Flashcards')
                ->where('sections.0.icon', 'book-open')
                ->where('sections.0.total', 3)
                ->where('sections.0.rated', 0)
                ->where('sections.0.completed', false)
                ->where('current.step', 'flashcard')
                ->where('current.stepPosition', 1)
                ->where('current.card.front', $identities[0])
                ->where('current.rating', null));

        // The address names the step and the position, so any item opens
        // before the ones before it are rated.
        $this->get(route('review.session', ['review' => $review->id, 'step' => 'flashcard', 'position' => 3]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('current.stepPosition', 3)
                ->where('current.card.front', $identities[2]));
    });

    it('shows one step per kind in the friendly order, each openable in any order', function (): void {
        $session = reviewSession();
        reviewRow($session, 'one', 'first meaning');
        reviewRow($session, 'two', 'second meaning');
        reviewRow($session, 'break the ice', 'start a conversation', FeedbackType::Phrase);
        reviewRow($session, 'I has went', 'I have gone', FeedbackType::Mistake);

        $review = reviewStart($this);
        $mistake = reviewItemsOf($review, FeedbackType::Mistake)->sole();

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('sections', 2)
                ->where('sections.0.key', 'flashcard')
                ->where('sections.0.total', 3)
                ->where('sections.1.key', 'mistake')
                ->where('sections.1.label', 'Fix the mistake')
                ->where('sections.1.icon', 'square-pen')
                ->where('sections.1.total', 1)
                ->where('sections.1.url', route('review.session', [
                    'review' => $review->id,
                    'step' => 'mistake',
                    'position' => $mistake->position,
                ])));

        // The step opens by its name, and by its number, before anything is rated.
        $this->get(route('review.session', ['review' => $review->id, 'step' => 'mistake']))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('current.step', 'mistake')
                ->where('current.card.exercise', 'mistake')
                ->where('current.card.item_identity', $mistake->item_identity));

        $this->get(route('review.session', ['review' => $review->id, 'step' => 2]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('current.step', 'mistake'));
    });

    it('remembers the next unrated item in each step', function (): void {
        $session = reviewSession();
        reviewRow($session, 'one', 'first meaning');
        reviewRow($session, 'two', 'second meaning');
        reviewRow($session, 'I has went', 'I have gone', FeedbackType::Mistake);
        reviewRow($session, 'I seen it', 'I saw it', FeedbackType::Mistake);

        $review = reviewStart($this);
        $flashcards = reviewItemsOf($review, FeedbackType::Vocabulary);
        $mistakes = reviewItemsOf($review, FeedbackType::Mistake);

        $this->post(route('review.rate', ['review' => $review->id]), [
            'kind' => $flashcards[0]->kind->value,
            'item_identity' => $flashcards[0]->item_identity,
            'rating' => ReviewRating::Easy->value,
        ])->assertRedirect(route('review.session', [
            'review' => $review->id,
            'step' => 'flashcard',
            'position' => $flashcards[1]->position,
        ]));

        // The mistake step still starts at its own first item.
        $this->get(route('review.session', ['review' => $review->id, 'step' => 'mistake']))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('current.step', 'mistake')
                ->where('current.stepPosition', 1)
                ->where('current.card.item_identity', $mistakes[0]->item_identity));

        // Coming back to the flashcard step returns to its next unrated item.
        $this->get(route('review.session', ['review' => $review->id, 'step' => 'flashcard']))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('current.stepPosition', 2)
                ->where('current.card.item_identity', $flashcards[1]->item_identity));

        $this->post(route('review.rate', ['review' => $review->id]), [
            'kind' => $mistakes[0]->kind->value,
            'item_identity' => $mistakes[0]->item_identity,
            'rating' => ReviewRating::Hard->value,
        ])->assertRedirect(route('review.session', [
            'review' => $review->id,
            'step' => 'mistake',
            'position' => $mistakes[1]->position,
        ]));
    });

    it('opens a finished step as All done here and points at the next unfinished one', function (): void {
        $session = reviewSession();
        reviewRow($session, 'one', 'first meaning');
        reviewRow($session, 'two', 'second meaning');
        reviewRow($session, 'I has went', 'I have gone', FeedbackType::Mistake);

        $review = reviewStart($this);
        $mistake = reviewItemsOf($review, FeedbackType::Mistake)->sole();
        $firstFlashcard = reviewItemsOf($review, FeedbackType::Vocabulary)->first();

        $this->post(route('review.rate', ['review' => $review->id]), [
            'kind' => $mistake->kind->value,
            'item_identity' => $mistake->item_identity,
            'rating' => ReviewRating::Easy->value,
        ])->assertRedirect(route('review.session', [
            'review' => $review->id,
            'step' => 'flashcard',
            'position' => $firstFlashcard->position,
        ]));

        $this->get(route('review.session', ['review' => $review->id, 'step' => 'mistake']))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('current', null)
                ->where('completedSection.key', 'mistake')
                ->where('completedSection.label', 'Fix the mistake')
                ->where('completedSection.total', 1)
                ->where('completedSection.counts.easy', 1)
                ->where('completedSection.counts.hard', 0)
                ->where('completedSection.next_url', route('review.session', [
                    'review' => $review->id,
                    'step' => 'flashcard',
                    'position' => $firstFlashcard->position,
                ]))
                ->where('sections.1.rated', 1)
                ->where('sections.1.completed', true));
    });

    it('takes back a rating and reopens the same mistake', function (): void {
        $session = reviewSession();
        reviewRow($session, 'one', 'first meaning');
        reviewRow($session, 'I has went', 'I have gone', FeedbackType::Mistake);

        $review = reviewStart($this);
        $mistake = reviewItemsOf($review, FeedbackType::Mistake)->sole();

        $this->post(route('review.rate', ['review' => $review->id]), [
            'kind' => $mistake->kind->value,
            'item_identity' => $mistake->item_identity,
            'rating' => ReviewRating::Easy->value,
        ]);

        $this->delete(route('review.undo', ['review' => $review->id]), [
            'kind' => $mistake->kind->value,
            'item_identity' => $mistake->item_identity,
        ])->assertRedirect(route('review.session', [
            'review' => $review->id,
            'step' => 'mistake',
            'position' => $mistake->position,
        ]));

        $this->get(route('review.session', [
            'review' => $review->id,
            'step' => 'mistake',
            'position' => $mistake->position,
        ]))->assertInertia(fn (Assert $page): Assert => $page
            ->where('current.step', 'mistake')
            ->where('current.card.item_identity', $mistake->item_identity)
            ->where('current.rating', null));

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
        ])->assertRedirect(route('review.session', [
            'review' => $review->id,
            'step' => 'flashcard',
            'position' => $item->position,
        ]));

        expect(Rating::query()->count())->toBe(0);

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('finished', false)
                ->where('current.card.item_identity', $item->item_identity));
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
        reviewRow($session, 'I has went', 'I have gone', FeedbackType::Mistake);

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
