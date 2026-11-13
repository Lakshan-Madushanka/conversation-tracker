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
use App\Models\ReviewSchedule;
use App\Models\Topic;
use App\Queries\TodaysReview;
use App\Support\NotebookIdentity;
use App\Support\ReviewSettings;
use App\Support\SearchText;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * One saved row of a notebook box, in the shape the board saves it.
 *
 * @param  array<string, mixed>  $attributes
 */
function nbDeleteRow(
    Notebook $notebook,
    FeedbackType $type,
    string $subject,
    string $meaning = '',
    string $html = '',
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
            'content_html' => $html,
            'content_text' => $subject,
            'correction_html' => '',
            'correction_text' => $meaning,
            'reason' => '',
            'example' => '',
            'example_html' => [],
            'is_split' => true,
        ], $attributes));
}

function nbDeleteWord(Notebook $notebook, string $word, string $meaning = 'a meaning'): PracticeFeedbackEntry
{
    return nbDeleteRow($notebook, FeedbackType::Vocabulary, $word, $meaning);
}

function nbDeleteNote(Notebook $notebook, string $html, string $text): PracticeFeedbackEntry
{
    return nbDeleteRow($notebook, FeedbackType::GrammarNote, $text, '', $html);
}

/** The scoped identity a notebook item carries. */
function nbDeleteKey(Notebook $notebook, string $text): string
{
    return NotebookIdentity::wordKey((int) $notebook->id, SearchText::normalize($text));
}

/** One word of a finished session, so another scope can hold the same spelling. */
function nbDeleteSessionWord(string $word, string $meaning): PracticeFeedbackEntry
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

/** A notebook under its own named topic. */
function nbDeleteNotebook(string $name = 'Kitchen words', string $topic = 'Kitchen'): Notebook
{
    return Notebook::factory()->create([
        'name' => $name,
        'topic_id' => Topic::factory()->create(['name' => $topic])->id,
    ]);
}

function nbDeletePreview(Notebook $notebook): TestResponse
{
    return test()->getJson(route('notebooks.deletePreview', $notebook));
}

function nbDeleteConfirm(object $test, Notebook $notebook, string $token): TestResponse
{
    return $test->deleteJson(route('notebooks.destroy', $notebook), ['token' => $token]);
}

/**
 * The board as the editor sends it.
 *
 * @param  array<string, mixed>  $feedback
 * @return array<string, mixed>
 */
function nbDeleteSavePayload(Notebook $notebook, array $feedback = []): array
{
    return [
        'name' => $notebook->name,
        'topic' => $notebook->topic->name,
        'description' => $notebook->description_html ?? '',
        'version' => $notebook->version,
        'save_key' => (string) Str::uuid(),
        'feedback' => [...[
            'vocabulary' => [],
            'phrase' => [],
            'grammar_note' => [],
        ], ...$feedback],
    ];
}

describe('the delete warning', function (): void {
    it('names saved rows, study items and affected reviews without changing anything', function (): void {
        $notebook = nbDeleteNotebook();
        nbDeleteWord($notebook, 'bank', 'money');
        nbDeleteWord($notebook, 'bank', 'river side');
        nbDeleteWord($notebook, 'river', 'flow');
        nbDeleteRow($notebook, FeedbackType::Phrase, 'in a row', 'one after another');
        $note = nbDeleteNote($notebook, '<p>Use <strong>was</strong>.</p>', 'Use was.');

        $bankKey = nbDeleteKey($notebook, 'bank');

        $mixed = CustomReview::factory()->draft()->create(['name' => 'Morning words']);
        CustomReviewItem::factory()->create([
            'custom_review_id' => $mixed->id,
            'kind' => FeedbackType::Vocabulary,
            'item_identity' => $bankKey,
        ]);
        CustomReviewItem::factory()->create([
            'custom_review_id' => $mixed->id,
            'kind' => FeedbackType::Vocabulary,
            'item_identity' => SearchText::normalize('money'),
        ]);

        $bankOnly = CustomReview::factory()->draft()->create(['name' => 'Bank set']);
        CustomReviewItem::factory()->create([
            'custom_review_id' => $bankOnly->id,
            'kind' => FeedbackType::Vocabulary,
            'item_identity' => $bankKey,
        ]);

        $before = [
            $notebook->version,
            $notebook->fresh()?->updated_at?->toIso8601String(),
            PracticeFeedbackEntry::query()->count(),
            CustomReviewItem::query()->count(),
            $note->collection_key,
        ];

        $response = nbDeletePreview($notebook)->assertOk();

        expect($response->json('notebook'))->toBe([
            'id' => $notebook->id,
            'name' => 'Kitchen words',
            'topic' => 'Kitchen',
        ])
            ->and($response->json('counts'))->toBe([
                'words' => 3,
                'phrases' => 1,
                'grammar_notes' => 1,
            ])
            ->and($response->json('items'))->toBe([
                'words' => 2,
                'phrases' => 1,
                'grammar_notes' => 1,
            ])
            ->and($response->json('reviews'))->toBe([
                [
                    'id' => $bankOnly->id,
                    'name' => 'Bank set',
                    'removed' => 1,
                    'remaining' => 0,
                    'becomes_empty' => true,
                ],
                [
                    'id' => $mixed->id,
                    'name' => 'Morning words',
                    'removed' => 1,
                    'remaining' => 1,
                    'becomes_empty' => false,
                ],
            ])
            ->and($response->json('token'))->toBeString()->toHaveLength(64);

        expect([
            $notebook->fresh()?->version,
            $notebook->fresh()?->updated_at?->toIso8601String(),
            PracticeFeedbackEntry::query()->count(),
            CustomReviewItem::query()->count(),
            $note->fresh()?->collection_key,
        ])->toBe($before);
    });
});

describe('deleting a notebook', function (): void {
    it('removes only its own rows, leaving its topic, other notebooks and sessions intact', function (): void {
        $topic = Topic::factory()->create(['name' => 'Shared']);

        $going = Notebook::factory()->create(['name' => 'Kitchen words', 'topic_id' => $topic->id]);
        $staying = Notebook::factory()->create(['name' => 'Travel words', 'topic_id' => $topic->id]);

        nbDeleteWord($going, 'bank', 'money');
        nbDeleteWord($staying, 'bank', 'money');
        $sessionWord = nbDeleteSessionWord('bank', 'money in a bank');

        $goingKey = nbDeleteKey($going, 'bank');
        $stayingKey = nbDeleteKey($staying, 'bank');
        $sessionKey = SearchText::normalize('bank');

        app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, $goingKey, true, CollectionItemStatus::StillDifficult);
        app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, $sessionKey, true, CollectionItemStatus::Learned);

        ReviewSchedule::query()->create([
            'kind' => FeedbackType::Vocabulary,
            'item_identity' => $sessionKey,
            'due_on' => now()->toDateString(),
            'last_rated_on' => now()->toDateString(),
            'last_rating' => ReviewRating::Okay,
            'rated_count' => 1,
            'easy_run' => 0,
        ]);

        $review = CustomReview::factory()->draft()->create(['name' => 'Everywhere']);
        CustomReviewItem::factory()->create([
            'custom_review_id' => $review->id,
            'kind' => FeedbackType::Vocabulary,
            'item_identity' => $goingKey,
        ]);
        CustomReviewItem::factory()->create([
            'custom_review_id' => $review->id,
            'kind' => FeedbackType::Vocabulary,
            'item_identity' => $stayingKey,
        ]);
        CustomReviewItem::factory()->create([
            'custom_review_id' => $review->id,
            'kind' => FeedbackType::Vocabulary,
            'item_identity' => $sessionKey,
        ]);

        $token = nbDeletePreview($going)->json('token');

        nbDeleteConfirm($this, $going, $token)
            ->assertOk()
            ->assertJsonPath('deleted', true)
            ->assertJsonPath('notebook.id', $going->id);

        expect(Notebook::query()->whereKey($going->id)->exists())->toBeFalse()
            ->and(PracticeFeedbackEntry::query()->where('notebook_id', $going->id)->count())->toBe(0)
            ->and(Notebook::query()->whereKey($staying->id)->exists())->toBeTrue()
            ->and(PracticeFeedbackEntry::query()->where('notebook_id', $staying->id)->count())->toBe(1)
            ->and($sessionWord->fresh())->not->toBeNull()
            ->and(Topic::query()->whereKey($topic->id)->exists())->toBeTrue();

        // Marks stay under the identity that held them. The notebook's mark is
        // quietly kept, the other notebook does not inherit it, and the
        // session's mark and schedule are untouched.
        expect(CollectionItemMark::query()->where('group_key', $goingKey)->exists())->toBeTrue()
            ->and(CollectionItemMark::query()->where('group_key', $stayingKey)->exists())->toBeFalse()
            ->and(CollectionItemMark::query()->where('group_key', $sessionKey)->exists())->toBeTrue()
            ->and(ReviewSchedule::query()->where('item_identity', $sessionKey)->exists())->toBeTrue();

        $held = CustomReviewItem::query()
            ->where('custom_review_id', $review->id)
            ->pluck('item_identity')
            ->all();

        expect($held)->not->toContain($goingKey)
            ->and($held)->toContain($stayingKey)
            ->and($held)->toContain($sessionKey);
    });

    it('refuses a stale confirmation and answers with fresh numbers', function (): void {
        $notebook = nbDeleteNotebook();
        nbDeleteWord($notebook, 'bank', 'money');

        $token = nbDeletePreview($notebook)->json('token');

        nbDeleteWord($notebook, 'river', 'flow');

        $stale = nbDeleteConfirm($this, $notebook, $token)
            ->assertStatus(409)
            ->assertJsonPath('stale', true)
            ->assertJsonPath('preview.counts.words', 2);

        expect($notebook->fresh())->not->toBeNull()
            ->and($notebook->entries()->count())->toBe(2);

        nbDeleteConfirm($this, $notebook, $stale->json('preview.token'))
            ->assertOk()
            ->assertJsonPath('deleted', true);

        expect(Notebook::query()->whereKey($notebook->id)->exists())->toBeFalse();
    });

    it('empties a custom review that loses its last item and keeps its attempt snapshots', function (): void {
        $notebook = nbDeleteNotebook();
        nbDeleteWord($notebook, 'bank', 'money');

        $this->postJson(route('notebooks.review.store', $notebook), [
            'creation_key' => 'delete-snapshot',
        ])->assertOk()->assertJsonPath('outcome', 'created');

        $review = CustomReview::query()->sole();
        $attempt = CustomReviewAttempt::query()->sole();

        $token = nbDeletePreview($notebook)->json('token');

        nbDeleteConfirm($this, $notebook, $token)->assertOk();

        expect($review->fresh()?->status)->toBe(CustomReviewStatus::Empty)
            ->and(CustomReviewItem::query()->where('custom_review_id', $review->id)->count())->toBe(0)
            ->and(CustomReviewAttempt::query()->whereKey($attempt->id)->exists())->toBeTrue();

        $item = CustomReviewAttemptItem::query()->where('custom_review_attempt_id', $attempt->id)->sole();

        expect($item->is_deleted)->toBeTrue()
            ->and($item->snapshot_text)->toBe('bank');

        $this->get(route('customReviews.show', $review))->assertOk();

        $this->get(route('customReviews.attempts.show', [$review, $attempt]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('custom-reviews/Attempt'));
    });

    it('removes a reference when the last duplicate row goes, even with a matching session word', function (): void {
        $notebook = nbDeleteNotebook();
        $first = nbDeleteWord($notebook, 'bank', 'money');
        $second = nbDeleteWord($notebook, 'bank', 'river side');
        nbDeleteSessionWord('bank', 'money in a bank');

        $this->postJson(route('notebooks.review.store', $notebook), [
            'creation_key' => 'duplicates',
        ])->assertOk()->assertJsonPath('outcome', 'created');

        $review = CustomReview::query()->sole();

        expect(CustomReviewItem::query()->count())->toBe(1);

        // One appearance remains, so the scoped item still exists.
        $this->putJson(route('notebooks.autosave.update', $notebook), nbDeleteSavePayload($notebook, [
            'vocabulary' => [[
                'id' => $first->client_id,
                'text' => 'bank',
                'meaning' => 'money',
                'example_html' => '',
            ]],
        ]))->assertOk();

        expect(CustomReviewItem::query()->count())->toBe(1)
            ->and($review->fresh()?->status)->toBe(CustomReviewStatus::Finished);

        // The last appearance goes. A same-spelling session row must not keep
        // the deleted notebook item alive.
        $this->putJson(route('notebooks.autosave.update', $notebook->fresh()), nbDeleteSavePayload($notebook->fresh()))
            ->assertOk();

        expect($second->fresh())->toBeNull()
            ->and(CustomReviewItem::query()->count())->toBe(0)
            ->and($review->fresh()?->status)->toBe(CustomReviewStatus::Empty);
    });

    it('cannot recreate a deleted notebook from a late save', function (): void {
        $notebook = nbDeleteNotebook();
        nbDeleteWord($notebook, 'bank', 'money');

        $notebookId = (int) $notebook->id;
        $token = nbDeletePreview($notebook)->json('token');

        nbDeleteConfirm($this, $notebook, $token)->assertOk();

        $this->putJson(route('notebooks.autosave.update', $notebookId), nbDeleteSavePayload($notebook))
            ->assertNotFound();

        expect(Notebook::query()->whereKey($notebookId)->exists())->toBeFalse()
            ->and(PracticeFeedbackEntry::query()->count())->toBe(0);

        $this->get(route('notebooks.show', $notebookId))->assertNotFound();
    });
});

describe('the complete notebook journey', function (): void {
    it('carries one notebook from creation to deletion', function (): void {
        $this->post(route('notebooks.store'), [
            'name' => 'Kitchen words',
            'topic' => 'Kitchen',
        ])->assertRedirect();

        $notebook = Notebook::query()->sole();

        $this->putJson(route('notebooks.autosave.update', $notebook), nbDeleteSavePayload($notebook, [
            'vocabulary' => [[
                'id' => 'w1',
                'text' => 'spatula',
                'meaning' => 'a kitchen tool',
                'example_html' => '',
            ]],
            'grammar_note' => [[
                'id' => 'g1',
                'html' => '<p>Use the <strong>past simple</strong>.</p>',
            ]],
        ]))->assertOk();

        $bankKey = nbDeleteKey($notebook, 'spatula');

        $this->get(route('notebooks.index', ['q' => 'spatula']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('notebooks/Index')
                ->where('total', 1));

        $this->get(route('collections.index', ['source' => 'notebooks']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('collections/Index')
                ->where('items.0.source', 'notebook'));

        $this->putJson(route('collections.marks.store'), [
            'kind' => FeedbackType::Vocabulary->value,
            'item' => $bankKey,
            'starred' => true,
            'status' => CollectionItemStatus::NONE,
        ])->assertOk();

        $this->postJson(route('notebooks.review.store', $notebook), [
            'creation_key' => 'journey',
        ])->assertOk()->assertJsonPath('outcome', 'created');

        $review = CustomReview::query()->sole();
        $attempt = CustomReviewAttempt::query()->sole();

        $this->post(route('customReviews.attempts.rate', [$review, $attempt]), [
            'kind' => FeedbackType::Vocabulary->value,
            'item_identity' => $bankKey,
            'rating' => ReviewRating::Easy->value,
        ])->assertRedirect();

        $this->putJson(route('notebooks.autosave.update', $notebook->fresh()), nbDeleteSavePayload($notebook->fresh(), [
            'vocabulary' => [[
                'id' => 'w1',
                'text' => 'spatula',
                'meaning' => 'a kitchen tool',
                'example_html' => '',
            ], [
                'id' => 'w2',
                'text' => 'whisk',
                'meaning' => 'a mixing tool',
                'example_html' => '',
            ]],
            'grammar_note' => [[
                'id' => 'g1',
                'html' => '<p>Use the <strong>past simple</strong>.</p>',
            ]],
        ]))->assertOk();

        $suggestion = app(TodaysReview::class)->suggest(
            ReviewSettings::SIZE,
            false,
            Request::create('/review'),
        );

        expect(array_column($suggestion->items, 'identity'))
            ->toContain(nbDeleteKey($notebook, 'whisk'));

        $token = nbDeletePreview($notebook->fresh())->json('token');

        nbDeleteConfirm($this, $notebook, $token)->assertOk()->assertJsonPath('deleted', true);

        $this->get(route('notebooks.index', ['q' => 'spatula']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('notebooks/Index')
                ->where('total', 0));

        expect(PracticeSession::query()->count())->toBe(0)
            ->and($review->fresh()?->status)->toBe(CustomReviewStatus::Empty)
            ->and(CustomReviewAttemptItem::query()->count())->toBe(2)
            ->and(CustomReviewAttemptItem::query()->where('is_deleted', false)->exists())->toBeFalse();
    });
});
