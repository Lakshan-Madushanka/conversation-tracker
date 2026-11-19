<?php

declare(strict_types=1);

use App\Enums\CollectionItemStatus;
use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use App\Models\CustomReview;
use App\Models\CustomReviewAttempt;
use App\Models\Notebook;
use App\Models\PracticeFeedbackEntry;
use App\Support\NotebookIdentity;
use Carbon\Carbon;
use Illuminate\Support\Str;

afterEach(function (): void {
    Carbon::setTestNow();
});

/** One saved notebook word, the simplest content an edit can touch. */
function lastUpdatedWord(Notebook $notebook, string $word, string $meaning = 'money'): PracticeFeedbackEntry
{
    return PracticeFeedbackEntry::factory()->for($notebook)->create([
        'practice_question_id' => null,
        'type' => FeedbackType::Vocabulary->value,
        'content_text' => $word,
        'correction_text' => $meaning,
        'client_id' => (string) Str::uuid(),
    ]);
}

/** The editing time the notebook listing shows for one notebook. */
function lastUpdatedOnListing(Notebook $notebook): Carbon
{
    $notebooks = test()->getJson(route('notebooks.index'))->assertOk()->json('notebooks');

    $row = collect($notebooks)->firstWhere('id', $notebook->id);

    expect($row)->not->toBeNull();

    return Carbon::parse($row['last_updated_at']);
}

describe('the notebook last-updated time', function (): void {
    it('moves when the board saves an entry', function (): void {
        Carbon::setTestNow('2026-11-01 09:00:00');
        $notebook = Notebook::factory()->create([
            'name' => 'Kitchen words',
            'last_changed_at' => Carbon::now(),
        ]);

        Carbon::setTestNow('2026-11-02 10:00:00');

        $this->putJson(route('notebooks.autosave.update', $notebook), [
            'name' => $notebook->name,
            'topic' => $notebook->topic->name,
            'description' => '',
            'version' => $notebook->version,
            'save_key' => (string) Str::uuid(),
            'feedback' => [
                'vocabulary' => [['id' => 'w1', 'text' => 'bank', 'meaning' => 'money', 'example_html' => '']],
                'phrase' => [],
                'grammar_note' => [],
            ],
        ])->assertOk();

        expect(lastUpdatedOnListing($notebook)->toDateTimeString())
            ->toBe('2026-11-02 10:00:00');
    });

    it('stays put when a word is marked in Collections', function (): void {
        Carbon::setTestNow('2026-11-02 10:00:00');
        $notebook = Notebook::factory()->create([
            'name' => 'Kitchen words',
            'last_changed_at' => Carbon::now(),
        ]);
        lastUpdatedWord($notebook, 'bank');

        Carbon::setTestNow('2026-11-03 11:00:00');

        $this->putJson(route('collections.marks.store'), [
            'kind' => FeedbackType::Vocabulary->value,
            'item' => NotebookIdentity::wordKey($notebook->id, 'bank'),
            'starred' => true,
            'status' => CollectionItemStatus::Learned->value,
        ])->assertOk();

        expect(lastUpdatedOnListing($notebook)->toDateTimeString())
            ->toBe('2026-11-02 10:00:00')
            ->and($notebook->refresh()->updated_at?->toDateTimeString())
            ->toBe('2026-11-02 10:00:00');
    });

    it('stays put when the notebook is practised and the attempt rated', function (): void {
        Carbon::setTestNow('2026-11-02 10:00:00');
        $notebook = Notebook::factory()->create([
            'name' => 'Kitchen words',
            'last_changed_at' => Carbon::now(),
        ]);
        lastUpdatedWord($notebook, 'bank');

        Carbon::setTestNow('2026-11-03 11:00:00');

        $this->postJson(route('notebooks.review.store', $notebook), [
            'creation_key' => 'practice-one',
            'confirmed' => false,
        ])->assertOk()->assertJsonPath('outcome', 'created');

        $review = CustomReview::query()->sole();
        $attempt = CustomReviewAttempt::query()->sole();

        $this->post(route('customReviews.attempts.rate', [$review, $attempt]), [
            'kind' => FeedbackType::Vocabulary->value,
            'item_identity' => NotebookIdentity::wordKey($notebook->id, 'bank'),
            'rating' => ReviewRating::Easy->value,
        ])->assertRedirect();

        expect(lastUpdatedOnListing($notebook)->toDateTimeString())
            ->toBe('2026-11-02 10:00:00')
            ->and($notebook->refresh()->updated_at?->toDateTimeString())
            ->toBe('2026-11-02 10:00:00');
    });
});
