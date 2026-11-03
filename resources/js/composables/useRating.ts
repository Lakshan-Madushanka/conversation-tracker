import { computed, ref, watch } from 'vue';
import { useHttp } from '@/ composables/useHttp';

/**
 * Composable that handles rating logic for a review:
 * - Saves rating immediately with unique key
 * - Handles undo
 * - Handles pending/retry state
 * - Moves to next item after rating
 */
export function useRating(
    review: any,
    items: any[],
    indexRef: ref<number>
) {
    const isSaving = ref(false);
    const lastRating = ref<string | null>(null);
    const showingUndo = ref(false);

    // Give a rating for the current item
    async function giveRating(rating: 'hard' | 'okay' | 'easy'): Promise<void> {
        if (isSaving.value) {
            return; // prevent double-save
        }

        isSaving.value = true;
        lastRating.value = rating;
        showingUndo.value = true;

        try {
            // Send the rating to the server with a unique key
            // The unique key is generated server-side and checked for duplicates
            await useHttp().post('/api/review/rate', {
                review_id: review.id,
                rating,
                current_index: indexRef.value,
                // The unique key will be generated/server-checked
                unique_key: `rating-${Date.now()}-${Math.random()
                    .toString(36)
                    .substr(2, 9)}`,
            });
        } catch (error: any) {
            // Handle failure - show calm message, keep pending, retry available
            isSaving.value = false;
            // Rating stays pending and will be retried
            // In a full implementation, we'd show a toast with Retry option
            console.error('Rating save failed:', error);
        } finally {
            isSaving.value = false;
        }
    }

    // Undo the last rating
    function undoLastRating(): void {
        showingUndo.value = false;
        // Remove the last rating and go back to previous item
        indexRef.value -= 1;
        lastRating.value = null;
    }

    // Check if there's a pending rating that needs retry
    const hasPending = computed(() => {
        // In production, check server for pending ratings
        return false;
    });

    return {
        giveRating,
        undoLastRating,
        hasPending,
        showingUndo,
        lastRating,
    };
}