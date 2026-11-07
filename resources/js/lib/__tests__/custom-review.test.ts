import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import type { AutoSaveRequest } from '@/composables/useAutoSave';
import {
    createReviewDescriptionSaver,
    createReviewNameSaver,
    RECOMMENDED_ITEMS,
    reviewIsTooMany,
    reviewStatusExplanation,
    reviewStatusLabel,
    reviewTooManyLabel,
    updateReviewStatus,
} from '@/lib/custom-review';
import type { CustomReviewSummary } from '@/types';

/** One save request around the words on screen. */
function request<TSnapshot extends object>(
    snapshot: TSnapshot,
): AutoSaveRequest<TSnapshot> {
    return {
        ...snapshot,
        sessionId: null,
        version: null,
        clientKey: 'client-key',
        saveKey: 'save-key',
    };
}

function summary(
    overrides: Partial<CustomReviewSummary> = {},
): CustomReviewSummary {
    return {
        id: 4,
        name: 'Travel words',
        description: null,
        status: 'draft',
        status_label: 'Draft',
        item_count: 3,
        item_count_label: '3 items',
        too_many_items: false,
        too_many_label: null,
        ...overrides,
    };
}

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('the size tag', () => {
    it('starts past the recommended size, never at it', () => {
        expect(RECOMMENDED_ITEMS).toBe(20);
        expect(reviewIsTooMany(20)).toBe(false);
        expect(reviewIsTooMany(21)).toBe(true);
    });

    it('names the size the way the page shows it', () => {
        expect(reviewTooManyLabel(21)).toBe('Too Many Items · 21 items');
        expect(reviewTooManyLabel(2)).toBe('Too Many Items · 2 items');
    });
});

describe('the status as the learner reads it', () => {
    it('capitalises each status once', () => {
        expect(reviewStatusLabel('empty')).toBe('Empty');
        expect(reviewStatusLabel('draft')).toBe('Draft');
        expect(reviewStatusLabel('finished')).toBe('Finished');
    });

    it('explains what each status means', () => {
        expect(reviewStatusExplanation('empty')).toBe(
            'Empty: nothing in it yet.',
        );
        expect(reviewStatusExplanation('draft')).toBe(
            'Draft: still being built.',
        );
        expect(reviewStatusExplanation('finished')).toBe(
            'Finished: ready to practise.',
        );
    });
});

describe('saving the review name', () => {
    it('sends the name alone and reads the review back', async () => {
        const saved = summary({ name: 'Morning coffee' });
        const fetch = vi.fn(async () =>
            jsonResponse({ id: 4, version: 0, review: saved }),
        );
        vi.stubGlobal('fetch', fetch);

        const outcome = await createReviewNameSaver(4)(
            request({ name: 'Morning coffee' }),
        );

        expect(outcome).toMatchObject({
            outcome: 'saved',
            saved: { review: saved },
        });
        expect(fetch).toHaveBeenCalledTimes(1);

        const [url, init] = fetch.mock.calls[0] as unknown as [
            string,
            RequestInit,
        ];
        expect(url).toContain('/custom-reviews/4');
        expect(init.method).toBe('PUT');
        expect(JSON.parse(init.body as string)).toEqual({
            name: 'Morning coffee',
        });
    });

    it('keeps the text when the server refuses the name', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () =>
                jsonResponse(
                    { errors: { name: ['Give this review a name.'] } },
                    422,
                ),
            ),
        );

        await expect(
            createReviewNameSaver(4)(request({ name: '   ' })),
        ).resolves.toEqual({
            outcome: 'invalid',
            errors: { name: 'Give this review a name.' },
        });
    });

    it('reports a request that never arrived as failed', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => new Response('down', { status: 500 })),
        );

        await expect(
            createReviewNameSaver(4)(request({ name: 'Morning coffee' })),
        ).resolves.toEqual({ outcome: 'failed' });
    });
});

describe('saving the description', () => {
    it('sends the description apart from the name', async () => {
        const fetch = vi.fn(async () =>
            jsonResponse({ id: 4, version: 0, review: summary() }),
        );
        vi.stubGlobal('fetch', fetch);

        await createReviewDescriptionSaver(4)(
            request({ description: 'Words to revisit' }),
        );

        const [, init] = fetch.mock.calls[0] as unknown as [
            string,
            RequestInit,
        ];
        expect(JSON.parse(init.body as string)).toEqual({
            description: 'Words to revisit',
        });
    });

    it('passes a validation mistake back field by field', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () =>
                jsonResponse(
                    {
                        errors: {
                            description: [
                                'Keep the description to 500 characters or fewer.',
                            ],
                        },
                    },
                    422,
                ),
            ),
        );

        await expect(
            createReviewDescriptionSaver(4)(
                request({ description: 'x'.repeat(501) }),
            ),
        ).resolves.toEqual({
            outcome: 'invalid',
            errors: {
                description: 'Keep the description to 500 characters or fewer.',
            },
        });
    });
});

describe('moving the review between draft and finished', () => {
    it('answers with the review the server moved', async () => {
        const moved = summary({ status: 'finished', status_label: 'Finished' });
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => jsonResponse({ review: moved })),
        );

        await expect(updateReviewStatus(4, 'finished')).resolves.toEqual(moved);
    });

    it('says why the server refused the move', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () =>
                jsonResponse(
                    {
                        errors: {
                            status: [
                                'Add items before marking this review finished.',
                            ],
                        },
                    },
                    422,
                ),
            ),
        );

        await expect(updateReviewStatus(4, 'finished')).rejects.toThrow(
            'Add items before marking this review finished.',
        );
    });

    it('keeps a calm line when the move did not arrive', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => new Response(null, { status: 500 })),
        );

        await expect(updateReviewStatus(4, 'draft')).rejects.toThrow(
            'This review could not be moved just now.',
        );
    });
});
