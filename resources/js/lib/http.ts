/**
 * The handful of request details every call the editor makes with `fetch`
 * shares. Inertia's own client handles these for its visits; anything sent
 * by hand has to repeat them.
 */

/**
 * Reads Laravel's CSRF cookie. The app talks to its own endpoints with
 * `fetch`, which (unlike Inertia's client) has to add the header by hand.
 */
export function csrfToken(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/u);

    return match ? decodeURIComponent(match[1]) : null;
}

/**
 * Sends a JSON request to the app's own origin: same-origin cookies, the CSRF
 * header, and headers that ask for and announce JSON. The headers are fixed,
 * so a caller passes only the rest of the request.
 */
export async function jsonRequest(
    url: string,
    init: Omit<RequestInit, 'headers' | 'credentials'> = {},
): Promise<Response> {
    const token = csrfToken();

    return fetch(url, {
        ...init,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token === null ? {} : { 'X-XSRF-TOKEN': token }),
        },
    });
}
