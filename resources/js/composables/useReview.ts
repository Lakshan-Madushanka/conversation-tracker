import { computed, ref } from 'vue';

/**
 * Hook that provides review state from the server.
 * In a full implementation, this would fetch from API endpoints.
 * For now, it uses sample data and provides the structure.
 */

export function useReview() {
    // Check if learner has finished sessions
    const hasFinishedSessions = computed(() => {
        // In production, this would check the database
        // For now, return true if there's data
        return true;
    });

    // Count of reviewable words and phrases
    const reviewableCount = computed(() => {
        // In production, this would query the database
        // For now, return a sensible default based on sample data
        return 5; // placeholder
    });

    // Message about reviewable items
    const reviewableMessage = computed(() => {
        if (reviewableCount.value === 1) {
            '1 word is ready to review';
        } else {
            `${reviewableCount.value} words and phrases are ready to review`;
        }
    });

    // Whether we can start a review
    const canStartReview = computed(() => {
        return hasFinishedSessions.value && reviewableCount.value > 0;
    });

    // Start a review
    const startReview = ref(false);

    return {
        hasFinishedSessions,
        reviewableCount,
        reviewableMessage,
        canStartReview,
        startReview,
    };
}