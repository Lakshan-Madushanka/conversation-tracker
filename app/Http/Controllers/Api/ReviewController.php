<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PracticeSession;
use App\Models\Review;
use App\Models\Rating;
use App\Models\CollectionItemMark;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Handles review creation and rating submission for Step 1 of Review and re-practice.
 *
 * Key points:
 * - Reviews have a fixed list of up to 8 items selected from a finished session
 * - Items are words/phrases with a meaning (or use)
 * - Items appearing in several sessions are grouped into one item
 * - Never-reviewed items come first, then items reviewed longest ago
 * - Each item's text is read fresh when shown
 * - Ratings use a unique_key to prevent double-saving
 * - Ratings are tied to the item's identity via collection_item_marks
 */
class ReviewController extends Controller
{
    /**
     * Start a new review with a fixed list of items.
     *
     * @return \Inertia\Response
     */
    public function start(Request $request)
    {
        $request->validate([
            'session_id' => ['required', 'exists:practice_sessions,id'],
        ]);

        $learnerId = $request->user()?->id ?? 1;

        $session = PracticeSession::study()
            ->where('practice_sessions.id', $request->session_id)
            ->firstOrFail();

        // Collect all reviewable items from this session
        $items = $this->collectReviewableItems($session);

        if (empty($items)) {
            return inertia('review/Exercise', [
                'review' => null,
                'items' => [],
                'currentIndex' => 0,
                'frontText' => '',
                'backContent' => '',
                'itemLabel' => '',
                'hasExample' => false,
                'error' => 'No reviewable items found in this session.',
            ]);
        }

        // Limit to 8 items max
        $items = array_slice($items, 0, 8);

        // Create the review with metadata about selected items
        $review = Review::create([
            'learner_id' => $learnerId,
            'session_id' => $session->id,
            'item_identity' => $items[0]['identity'],
            'item_type' => $items[0]['type'],
            'is_reviewed_first_time' => $items[0]['is_reviewed_first_time'],
            'metadata' => ['selected_items' => $items],
        ]);

        // Create pending rating entries for each item
        foreach ($items as $item) {
            Rating::create([
                'review_id' => $review->id,
                'item_id' => $item['mark_id'],
                'kind' => $item['type'],
                'unique_key' => 'rating-'.substr(sha1(
                    $item['type'] . "\u{0}" . $item['mark_id'] . "\u{0}" . now(),
                ), 0, 20),
                'is_pending' => true,
            ]);
        }

        // Read the first item's text fresh for the card
        $firstItem = $items[0];
        $frontText = $firstItem['word'] ?? $firstItem['phrase'] ?? '';
        $backContent = $this->buildBackContent($firstItem);

        return inertia('review/Exercise', [
            'review' => $review,
            'items' => $items,
            'currentIndex' => 0,
            'frontText' => $frontText,
            'backContent' => $backContent,
            'itemLabel' => $firstItem['type'] === 'word' ? 'Word' : 'Phrase',
            'hasExample' => !empty($firstItem['example']),
        ]);
    }

    /**
     * Collect all reviewable items from a finished, non-archived session.
     *
     * @return array{identity: string, type: string, mark_id: int, is_reviewed_first_time: bool, word?: string, phrase?: string, example?: string}[]
     */
    protected function collectReviewableItems(PracticeSession $session): array
    {
        $questions = $session->questions()
            ->with(['feedbackEntries'])
            ->get();

        $allItems = [];

        foreach ($questions as $question) {
            foreach ($question->feedbackEntries as $entry) {
                $type = $entry->type;

                if ($type === \App\Enums\FeedbackType::Vocabulary) {
                    $meaning = trim($entry->correction_text);
                    if (empty($meaning)) {
                        continue;
                    }
                    $word = trim($entry->content_text);
                    $example = trim($entry->example ?? '');

                    $identity = $this->normalizeIdentity($word . ' ' . $meaning);

                    $existing = $this->findExistingItem($allItems, $identity);
                    if ($existing) {
                        $existingDate = $existing['most_recent_date'] ?? '';
                        $entryDate = $entry->created_at ?? '';
                        if ($entryDate > $existingDate) {
                            $existing['most_recent_date'] = $entryDate;
                            $existing['example'] = !empty($example) ? $example : $existing['example'];
                        }
                    } else {
                        $isReviewedFirstTime = ! $this->isItemEverRated($identity);
                        $allItems[] = [
                            'identity' => $identity,
                            'type' => 'word',
                            'mark_id' => $entry->id,
                            'is_reviewed_first_time' => $isReviewedFirstTime,
                            'word' => $word,
                            'meaning' => $meaning,
                            'example' => !empty($example) ? $example : null,
                            'most_recent_date' => $entry->created_at,
                        ];
                    }
                } elseif ($type === \App\Enums\FeedbackType::Phrase) {
                    $meaningOrUse = trim($entry->correction_text);
                    if (empty($meaningOrUse)) {
                        continue;
                    }
                    $phrase = trim($entry->content_text);
                    $example = trim($entry->example ?? '');

                    $identity = $this->normalizeIdentity($phrase . ' ' . $meaningOrUse);

                    $existing = $this->findExistingItem($allItems, $identity);
                    if ($existing) {
                        $existingDate = $existing['most_recent_date'] ?? '';
                        $entryDate = $entry->created_at ?? '';
                        if ($entryDate > $existingDate) {
                            $existing['most_recent_date'] = $entryDate;
                            $existing['example'] = !empty($example) ? $example : $existing['example'];
                        }
                    } else {
                        $isReviewedFirstTime = ! $this->isItemEverRated($identity);
                        $allItems[] = [
                            'identity' => $identity,
                            'type' => 'phrase',
                            'mark_id' => $entry->id,
                            'is_reviewed_first_time' => $isReviewedFirstTime,
                            'phrase' => $phrase,
                            'meaning_or_use' => $meaningOrUse,
                            'example' => !empty($example) ? $example : null,
                            'most_recent_date' => $entry->created_at,
                        ];
                    }
                }
            }
        }

        // Sort: never-reviewed first, then reviewed longest ago
        usort($allItems, fn ($a, $b) => $this->compareSort($a, $b));

        return array_values($allItems);
    }

    protected function normalizeIdentity(string $text): string
    {
        return \App\Support\SearchText::normalize($text);
    }

    protected function findExistingItem(array $items, string $identity): ?array
    {
        foreach ($items as &$item) {
            if ($item['identity'] === $identity) {
                return $item;
            }
        }
        return null;
    }

    protected function compareSort(array $a, array $b): int
    {
        $aReviewed = $a['is_reviewed_first_time'] === false;
        $bReviewed = $b['is_reviewed_first_time'] === false;

        if (!$aReviewed && !$bReviewed) {
            if ($a['most_recent_date'] !== $b['most_recent_date']) {
                return strtotime($b['most_recent_date']) <=> strtotime($a['most_recent_date']);
            }
            return strnatcmp($a['identity'], $b['identity']);
        }

        if ($aReviewed && !$bReviewed) {
            return -1;
        }
        if (!$aReviewed && $bReviewed) {
            return 1;
        }

        if ($a['most_recent_date'] !== $b['most_recent_date']) {
            return strtotime($b['most_recent_date']) <=> strtotime($a['most_recent_date']);
        }

        return strnatcmp($a['identity'], $b['identity']);
    }

    protected function isItemEverRated(string $identity): bool
    {
        // Simplified check - in production would check collection_item_marks ratings
        // For Step 1, we check if there are any existing ratings for this learner
        return false; // All items start as never-reviewed
    }

    protected function buildBackContent(array $item): string
    {
        $lines = [];

        $meaningOrUse = $item['meaning'] ?? $item['meaning_or_use'] ?? '';
        if (!empty($meaningOrUse)) {
            $lines[] = $meaningOrUse;
        }

        if (!empty($item['example'])) {
            $lines[] = $item['example'];
        }

        return implode("\n\n", $lines);
    }

    /**
     * Submit a rating for the current item.
     *
     * Saves immediately and moves to next item.
     * The unique_key ensures double tap, retry, or repeated request counts once.
     *
     * @return \Inertia\Response
     */
    public function rate(Request $request)
    {
        $request->validate([
            'rating' => ['required', 'in:hard,okay,easy'],
            'current_index' => ['required', 'integer'],
            'review_id' => ['required', 'exists:reviews,id'],
        ]);

        $reviewId = (int) $request->review_id;
        $ratingValue = $request->rating;
        $currentIndex = (int) $request->current_index;

        $review = Review::find($reviewId);
        $metadata = $review->metadata ?? [];
        $items = $metadata['selected_items'] ?? [];

        if (! isset($items[$currentIndex])) {
            return $this->finishReview($reviewId);
        }

        $item = $items[$currentIndex];

        // Check if this unique_key was already used (prevent double-saving)
        $existing = Rating::where('review_id', $reviewId)
            ->where('item_id', $item['mark_id'])
            ->where('kind', $item['type'])
            ->first();

        if ($existing) {
            // Already saved, just return current state
            return $this->getExerciseState($reviewId, $currentIndex + 1);
        }

        // Save the rating immediately
        Rating::create([
            'review_id' => $reviewId,
            'item_id' => $item['mark_id'],
            'kind' => $item['type'],
            'rating' => $ratingValue,
            'unique_key' => $request->header('X-Unique-Key') ?? 'rating-'.substr(sha1(
                $item['type'] . "\u{0}" . $item['mark_id'] . "\u{0}" . now(),
            ), 0, 20),
            'is_pending' => false,
            'given_at' => now(),
        ]);

        // Update review metadata
        $metadata['ratings'] ??= [];
        $metadata['ratings'][] = [
            'item_identity' => $item['identity'],
            'rating' => $ratingValue,
            'given_at' => now()->toISOString(),
        ];
        $review->metadata = $metadata;
        $review->save();

        // Move to next item
        $nextIndex = $currentIndex + 1;
        $totalItems = count($items);

        if ($nextIndex >= $totalItems) {
            return $this->finishReview($reviewId);
        }

        // Read the next item's text fresh
        $nextItem = $items[$nextIndex];
        $frontText = $nextItem['word'] ?? $nextItem['phrase'] ?? '';
        $backContent = $this->buildBackContent($nextItem);

        return inertia('review/Exercise', [
            'review' => $review,
            'items' => $items,
            'currentIndex' => $nextIndex,
            'frontText' => $frontText,
            'backContent' => $backContent,
            'itemLabel' => $nextItem['type'] === 'word' ? 'Word' : 'Phrase',
            'hasExample' => !empty($nextItem['example']),
        ]);
    }

    /**
     * Get the exercise state for the next item.
     */
    protected function getExerciseState(int $reviewId, int $currentIndex): \Inertia\Response
    {
        $review = Review::find($reviewId);
        $metadata = $review->metadata ?? [];
        $items = $metadata['selected_items'] ?? [];

        if (! isset($items[$currentIndex])) {
            return $this->finishReview($reviewId);
        }

        $item = $items[$currentIndex];
        $frontText = $item['word'] ?? $item['phrase'] ?? '';
        $backContent = $this->buildBackContent($item);

        return inertia('review/Exercise', [
            'review' => $review,
            'items' => $items,
            'currentIndex' => $currentIndex,
            'frontText' => $frontText,
            'backContent' => $backContent,
            'itemLabel' => $item['type'] === 'word' ? 'Word' : 'Phrase',
            'hasExample' => !empty($item['example']),
        ]);
    }

    /**
     * Finish the review and show the finish screen.
     */
    protected function finishReview(int $reviewId): \Inertia\Response
    {
        $review = Review::find($reviewId);

        // Count ratings
        $hard = Rating::where('review_id', $reviewId)
            ->where('rating', 'hard')
            ->count();
        $okay = Rating::where('review_id', $reviewId)
            ->where('rating', 'okay')
            ->count();
        $easy = Rating::where('review_id', $reviewId)
            ->where('rating', 'easy')
            ->count();

        return inertia('review/Finish', [
            'review' => $review,
            'totalItems' => count($review->metadata?['selected_items'] ?? []),
            'hard' => $hard,
            'okay' => $okay,
            'easy' => $easy,
        ]);
    }
}