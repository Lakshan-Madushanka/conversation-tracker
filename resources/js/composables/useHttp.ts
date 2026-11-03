/**
 * Simple HTTP helper for API calls.
 * Uses fetch with JSON support and error handling.
 */
export function useHttp() {
    const baseUrl = import.meta.env.VITE_API_BASE_URL || '';

    async function post(path: string, body: any, headers: Record<string, string> = {}): Promise<any> {
        const url = baseUrl + path;

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                ...headers,
            },
            body: JSON.stringify(body),
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(
                errorData.message || `HTTP ${response.status}`,
            );
        }

        return response.json();
    }

    async function postWithAuth(path: string, body: any, headers: Record<string, string> = {}): Promise<any> {
        // Add auth header if available
        const authHeaders = {
            ...headers,
            // In production, this would come from the auth token
        };

        return post(path, body, authHeaders);
    }

    return {
        post,
        postWithAuth,
    };
}