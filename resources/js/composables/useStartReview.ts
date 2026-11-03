/**
 * Composable for handling review start logic.
 * Uses Inertia data passing for seamless navigation.
 */
export function useStartReview() {
    const isReviewing = ref(false);
    const error = ref<string | null>(null);
    const reviewData = ref<object | null>(null);

    async function startReview(sessionId: number): Promise<boolean> {
        isReviewing.value = true;
        error.value = null;
        reviewData.value = null;

        try {
            // In Inertia, we would use visit, but for API fallback:
            const response = await fetch('/api/review/start', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ session_id: sessionId }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Failed to start review');
            }

            reviewData.value = data;
            isReviewing.value = false;

            // Navigate to exercise screen - in full Inertia app this would be
            // Inertia.visit('/review/exercise', { data, preserveScroll: true })
            // For now, we just store the data and the page will pick it up
            return true;
        } catch (err: any) {
            error.value = err.message;
            isReviewing.value = false;
            return false;
        }
    }

    function reset() {
        isReviewing.value = false;
        error.value = null;
        reviewData.value = null;
    }

    return {
        isReviewing,
        error,
        reviewData,
        startReview,
        reset,
    };
}