<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Rating;
use App\Models\Review;
use App\Models\ReviewSchedule;
use App\Models\Topic;
use App\Support\ReviewSettings;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function (): void {
    Carbon::setTestNow();
    DB::disableQueryLog();
});

/**
 * A finished session the learner can study from.
 */
function saySession(?CarbonImmutable $at = null): PracticeSession
{
    $at ??= CarbonImmutable::now();

    return PracticeSession::factory()
        ->finished()
        ->for(Topic::factory()->create())
        ->create([
            'created_at' => $at,
            'finished_at' => $at,
        ]);
}

/**
 * The more natural version saved beside one question.
 */
function sayNatural(PracticeQuestion $question, string $text, ?string $html = null): PracticeFeedbackEntry
{
    return PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => FeedbackType::NaturalVersion,
        'content_text' => $text,
        'content_html' => $html ?? '<p>'.e($text).'</p>',
    ]);
}

/**
 * One past question, with a more natural version unless asked otherwise.
 */
function sayQuestion(
    PracticeSession $session,
    string $text = 'What do you do in your free time?',
    ?string $natural = 'I enjoy cooking and going for long walks.',
    ?string $questionHtml = null,
): PracticeQuestion {
    $question = PracticeQuestion::factory()->for($session)->create([
        'position' => $session->questions()->count() + 1,
        'question_text' => $text,
        'question_html' => $questionHtml ?? '<p>'.e($text).'</p>',
    ]);

    if ($natural !== null) {
        sayNatural($question, $natural);
    }

    return $question;
}

/**
 * One table row saved the way the editor saves a word, a phrase or a mistake.
 */
function sayRow(
    PracticeSession $session,
    string $subject,
    string $meaning,
    FeedbackType $type = FeedbackType::Vocabulary,
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
        'example' => '',
        'example_html' => [],
        'reason' => '',
        'position' => 0,
        'is_split' => true,
        'client_id' => (string) Str::uuid(),
    ]);
}

/**
 * Start a review through the screen's own button.
 */
function sayStart(object $test, bool $daily = true): Review
{
    $test->post(route('review.store'), ['daily' => $daily])->assertRedirect();

    return Review::query()->latest('id')->firstOrFail();
}

describe('which questions become exercises', function (): void {
    it('uses only questions that carry both a tutor question and a natural version', function (): void {
        $session = saySession();
        $usable = sayQuestion($session, 'Where are you from?', 'I am from a small town by the sea.');

        sayQuestion($session, 'How was your day?', null);
        sayQuestion($session, 'Any plans for the weekend?', '   ');
        sayQuestion($session, '   ', 'A natural version with no question.');

        $review = sayStart($this);

        expect($review->items)->toHaveCount(1)
            ->and($review->items->sole()->kind)->toBe(FeedbackType::Question)
            ->and($review->items->sole()->item_identity)->toBe((string) $usable->id);
    });

    it('leaves unfinished and archived sessions out', function (): void {
        sayQuestion(
            PracticeSession::factory()->for(Topic::factory()->create())->create(),
            'An unfinished question?',
            'A natural version.',
        );

        sayQuestion(
            PracticeSession::factory()->archived()->create(),
            'An archived question?',
            'A natural version.',
        );

        $kept = sayQuestion(saySession(), 'A visible question?', 'A natural version.');

        $review = sayStart($this);

        expect($review->items)->toHaveCount(1)
            ->and($review->items->sole()->item_identity)->toBe((string) $kept->id);
    });

    it('treats identical questions in different sessions as separate items', function (): void {
        $first = sayQuestion(saySession(), 'How was your weekend?', 'It was relaxing.');
        $second = sayQuestion(saySession(), 'How was your weekend?', 'It was relaxing.');

        $review = sayStart($this);

        expect($review->items)->toHaveCount(2)
            ->and($review->items->pluck('item_identity')->sort()->values()->all())
            ->toBe([(string) $first->id, (string) $second->id]);
    });

    it('keeps a question an item when its words are edited', function (): void {
        $session = saySession();
        $question = sayQuestion($session, 'What did you do last night?', 'I stayed in and read.');

        $review = sayStart($this);

        $question->update([
            'question_text' => 'What did you do yesterday evening?',
            'question_html' => '<p>What did you do yesterday evening?</p>',
        ]);

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('current.card.item_identity', (string) $question->id)
                ->where('current.card.question_text', 'What did you do yesterday evening?'));
    });

    it('skips a question whose natural version is removed during the review', function (): void {
        $session = saySession();
        sayQuestion($session, 'A kept question?', 'A natural version.');
        $removed = sayQuestion($session, 'A removed question?', 'Another natural version.');

        $review = sayStart($this);
        expect($review->items)->toHaveCount(2);

        $removed->feedbackEntries()
            ->where('type', FeedbackType::NaturalVersion)
            ->delete();

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('sections', 1)
                ->where('sections.0.total', 1)
                ->where('current.card.question_text', 'A kept question?'));
    });
});

describe('the say-it-again card', function (): void {
    it('shows the tutor question, the natural version and the original session link', function (): void {
        $session = saySession();
        $question = sayQuestion(
            $session,
            'What do you do in your free time?',
            'I enjoy cooking and going for long walks.',
            '<p>What do you do in your <strong>free time</strong>?</p>',
        );
        sayNatural(
            $question,
            'I enjoy cooking and going for long walks.',
            '<p>I enjoy <em>cooking</em> and going for long walks.</p><ul><li>Keep it warm.</li></ul>',
        );

        $review = sayStart($this);

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('review/Session')
                ->where('current.step', 'say_it_again')
                ->where('current.label', 'Say it again')
                ->where('current.card.kind', 'question')
                ->where('current.card.exercise', 'say_it_again')
                ->where('current.card.label', 'Say it again')
                ->where('current.card.item_identity', (string) $question->id)
                ->where('current.card.question_html', '<p>What do you do in your <strong>free time</strong>?</p>')
                ->where('current.card.question_text', 'What do you do in your free time?')
                ->where('current.card.natural_html', '<p>I enjoy <em>cooking</em> and going for long walks.</p><ul><li>Keep it warm.</li></ul>')
                ->where('current.card.natural_text', 'I enjoy cooking and going for long walks.')
                ->where('current.card.session_url', route('sessions.show', [
                    'session' => $session->id,
                    'question' => (string) $question->position,
                    'field' => 'question',
                ]))
                ->where('current.rating', null));
    });

    it('reduces saved markup to the safe formatting set', function (): void {
        $session = saySession();
        $question = sayQuestion(
            $session,
            'Which is better?',
            'The second one.',
            '<p>Which is <strong>better</strong>?</p><script>alert(1)</script>',
        );
        $question->feedbackEntries()
            ->where('type', FeedbackType::NaturalVersion)
            ->update([
                'content_text' => 'The second one.',
                'content_html' => '<p>The <b>second</b> one.</p><script>alert(1)</script>',
            ]);

        $review = sayStart($this);

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('current.card.question_html', '<p>Which is <strong>better</strong>?</p>')
                ->where('current.card.natural_html', '<p>The <strong>second</strong> one.</p>'));
    });

    it('keeps special characters and non-English letters as saved', function (): void {
        $question = sayQuestion(
            saySession(),
            '¿Dónde está la estación?',
            'Está cerca del café, a la derecha.',
        );

        $review = sayStart($this);

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('current.card.item_identity', (string) $question->id)
                ->where('current.card.question_text', '¿Dónde está la estación?')
                ->where('current.card.natural_text', 'Está cerca del café, a la derecha.'));
    });
});

describe('the say-it-again step', function (): void {
    it('appears as its own step after flashcards and mistakes', function (): void {
        $session = saySession();
        sayRow($session, 'serendipity', 'finding something good by chance');
        sayRow($session, 'I has went', 'I have gone', FeedbackType::Mistake);
        sayQuestion($session, 'How was your weekend?', 'It was relaxing.');

        $review = sayStart($this);

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('sections', 3)
                ->where('sections.0.key', 'flashcard')
                ->where('sections.1.key', 'mistake')
                ->where('sections.2.key', 'say_it_again')
                ->where('sections.2.label', 'Say it again')
                ->where('sections.2.icon', 'message-circle')
                ->where('sections.2.number', 3)
                ->where('sections.2.total', 1)
                ->where('sections.2.url', route('review.session', [
                    'review' => $review->id,
                    'step' => 'say_it_again',
                    'position' => 3,
                ]))
                // The first unfinished step opens, as before.
                ->where('current.step', 'flashcard'));
    });

    it('opens by its name or number at any time, before the earlier steps are rated', function (): void {
        $session = saySession();
        sayRow($session, 'serendipity', 'finding something good by chance');
        $question = sayQuestion($session, 'How was your weekend?', 'It was relaxing.');

        $review = sayStart($this);

        $this->get(route('review.session', ['review' => $review->id, 'step' => 'say_it_again']))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('current.step', 'say_it_again')
                ->where('current.card.item_identity', (string) $question->id));

        $this->get(route('review.session', ['review' => $review->id, 'step' => 2]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('current.step', 'say_it_again'));
    });

    it('shows no say-it-again step when no question is usable', function (): void {
        $session = saySession();
        sayRow($session, 'serendipity', 'finding something good by chance');
        sayQuestion($session, 'A question without a natural version?', null);

        $review = sayStart($this);

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('sections', 1)
                ->where('sections.0.key', 'flashcard'));
    });
});

describe('rating a question', function (): void {
    it('saves the rating once and schedules the question', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $question = sayQuestion(saySession(), 'How was your weekend?', 'It was relaxing.');

        $review = sayStart($this);

        $payload = [
            'kind' => FeedbackType::Question->value,
            'item_identity' => (string) $question->id,
            'rating' => ReviewRating::Hard->value,
        ];

        $this->post(route('review.rate', ['review' => $review->id]), $payload)->assertRedirect();
        $this->post(route('review.rate', ['review' => $review->id]), $payload)->assertRedirect();

        $rating = Rating::query()->sole();

        expect($rating->kind)->toBe(FeedbackType::Question)
            ->and($rating->item_identity)->toBe((string) $question->id)
            ->and($rating->rating)->toBe(ReviewRating::Hard)
            ->and(ReviewSchedule::query()->sole()->due_on->toDateString())->toBe('2026-11-04');
    });

    it('moves on to the next question and can take the rating back', function (): void {
        $session = saySession();
        sayQuestion($session, 'First question?', 'First natural version.');
        sayQuestion($session, 'Second question?', 'Second natural version.');

        $review = sayStart($this);
        $items = $review->items->values();
        $first = $items[0];
        $second = $items[1];

        $this->post(route('review.rate', ['review' => $review->id]), [
            'kind' => FeedbackType::Question->value,
            'item_identity' => $first->item_identity,
            'rating' => ReviewRating::Okay->value,
        ])->assertRedirect(route('review.session', [
            'review' => $review->id,
            'step' => 'say_it_again',
            'position' => $second->position,
        ]));

        $this->delete(route('review.undo', ['review' => $review->id]), [
            'kind' => FeedbackType::Question->value,
            'item_identity' => $first->item_identity,
        ])->assertRedirect(route('review.session', [
            'review' => $review->id,
            'step' => 'say_it_again',
            'position' => $first->position,
        ]));

        expect(Rating::query()->count())->toBe(0);

        $this->get(route('review.session', ['review' => $review->id, 'step' => 'say_it_again']))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('current.card.item_identity', $first->item_identity)
                ->where('current.rating', null));

        // The second question is still waiting, untouched.
        $this->get(route('review.session', [
            'review' => $review->id,
            'step' => 'say_it_again',
            'position' => $second->position,
        ]))->assertInertia(fn (Assert $page): Assert => $page
            ->where('current.card.item_identity', $second->item_identity)
            ->where('current.rating', null));
    });
});

describe('say-it-again in the daily suggestion', function (): void {
    it('mixes questions into the suggestion by their target shares', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = saySession();

        foreach (range(1, 8) as $i) {
            sayRow($session, "word-{$i}", 'a meaning');
            sayRow($session, "said-{$i}", "told-{$i}", FeedbackType::Mistake);
        }

        foreach (range(1, 4) as $i) {
            sayQuestion($session, "Question {$i}?", "A natural answer {$i}.");
        }

        $review = sayStart($this);

        expect($review->items)->toHaveCount(ReviewSettings::SIZE)
            ->and($review->items->filter(fn ($item): bool => $item->kind === FeedbackType::Question))
            ->toHaveCount(1);
    });

    it('shows the question count in the mix and the honest estimate', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = saySession();

        foreach (range(1, 8) as $i) {
            sayRow($session, "word-{$i}", 'a meaning');
            sayRow($session, "said-{$i}", "told-{$i}", FeedbackType::Mistake);
        }

        foreach (range(1, 4) as $i) {
            sayQuestion($session, "Question {$i}?", "A natural answer {$i}.");
        }

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'suggest')
                ->where('todayReview.count', 8)
                ->where('todayReview.mix', '5 flashcards, 2 mistakes to fix, 1 question to say again')
                ->where('todayReview.minutes', 7));
    });

    it('offers questions in Review a bit more, leaving out what was already rated today', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = saySession();

        foreach (range(1, 6) as $i) {
            sayRow($session, "word-{$i}", 'a meaning');
        }

        foreach (range(1, 6) as $i) {
            sayQuestion($session, "Question {$i}?", "A natural answer {$i}.");
        }

        $review = sayStart($this);

        foreach ($review->items as $item) {
            $this->post(route('review.rate', ['review' => $review->id]), [
                'kind' => $item->kind->value,
                'item_identity' => $item->item_identity,
                'rating' => ReviewRating::Easy->value,
            ])->assertRedirect();
        }

        // Reaching the finish screen completes the daily review.
        $this->get(route('review.session', ['review' => $review->id]))->assertOk();

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'done')
                ->where('todayReview.canReviewMore', true));

        $this->post(route('review.store'), ['more' => true])->assertRedirect();

        /** @var Review $bitMore */
        $bitMore = Review::query()->latest('id')->firstOrFail();

        $ratedToday = Rating::query()->pluck('item_identity')->all();

        expect($bitMore->items)->not->toBeEmpty()
            ->and($bitMore->items->filter(fn ($item): bool => $item->kind === FeedbackType::Question))
            ->not->toBeEmpty();

        foreach ($bitMore->items as $item) {
            expect($ratedToday)->not->toContain($item->item_identity);
        }
    });
});

describe('say-it-again at scale', function (): void {
    it('builds a full question-only suggestion in a bounded number of queries', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');

        $topic = Topic::factory()->create(['name' => 'Question pool']);
        $sessionId = (int) DB::table('practice_sessions')->insertGetId([
            'topic_id' => $topic->id,
            'status' => 'finished',
            'created_at' => now(),
            'updated_at' => now(),
            'finished_at' => now(),
            'last_changed_at' => now(),
        ]);

        foreach (array_chunk(range(1, 2000), 500) as $chunk) {
            $questions = [];
            $naturals = [];

            foreach ($chunk as $i) {
                $questions[] = [
                    'id' => 500000 + $i,
                    'practice_session_id' => $sessionId,
                    'position' => $i,
                    'question_html' => '<p>Question '.$i.'?</p>',
                    'question_text' => 'Question '.$i.'?',
                    'answer_html' => '<p>An answer.</p>',
                    'answer_text' => 'An answer.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $naturals[] = [
                    'practice_question_id' => 500000 + $i,
                    'type' => 'natural_version',
                    'content_html' => '<p>A natural version '.$i.'.</p>',
                    'content_text' => 'A natural version '.$i.'.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('practice_questions')->insert($questions);
            DB::table('practice_feedback_entries')->insert($naturals);
        }

        DB::enableQueryLog();
        $this->get(route('home'))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($queries)->toBeLessThanOrEqual(20);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.count', 8)
                ->where('todayReview.mix', '8 questions to say again')
                ->where('todayReview.minutes', 10));
    });
});
