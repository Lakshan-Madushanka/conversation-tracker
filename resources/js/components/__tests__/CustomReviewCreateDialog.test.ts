import { reactive } from 'vue';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import CustomReviewCreateDialog from '@/components/CustomReviewCreateDialog.vue';

/** Every post the dialog makes, with the data it sent. */
const posts: {
    url: string;
    data: Record<string, unknown>;
    options: Record<string, (value?: unknown) => void>;
}[] = [];

/** What the server said was wrong, set the way a refusal would. */
const errors: Record<string, string> = {};

type DialogFormData = { name: string; kinds: string[] };

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    useForm: (initial: DialogFormData) => {
        let defaults: DialogFormData = { ...initial };

        const form = reactive({
            name: initial.name,
            kinds: [...initial.kinds],
            processing: false,
            errors,
            defaults: (values: DialogFormData) => {
                defaults = { ...values, kinds: [...values.kinds] };
            },
            reset: () => {
                Object.assign(form, {
                    ...defaults,
                    kinds: [...defaults.kinds],
                });
            },
            clearErrors: () => {
                for (const key of Object.keys(errors)) {
                    delete errors[key];
                }
            },
            post: (
                url: string,
                options: Record<string, (value?: unknown) => void>,
            ) => {
                posts.push({
                    url,
                    data: { name: form.name, kinds: form.kinds },
                    options,
                });
            },
        });

        return form;
    },
}));

let mounted: VueWrapper | undefined;

async function openDialog(
    defaultName = 'Custom Review 4',
): Promise<VueWrapper> {
    mounted = mount(CustomReviewCreateDialog, {
        attachTo: document.body,
        props: { open: true, defaultName },
    });

    await flushPromises();

    return mounted;
}

function buttonWith(text: string): HTMLButtonElement | undefined {
    return [...document.querySelectorAll<HTMLButtonElement>('button')].find(
        (button) => button.textContent?.trim() === text,
    );
}

function nameInput(): HTMLInputElement {
    const input = document.querySelector<HTMLInputElement>('#new-review-name');

    if (input === null) {
        throw new Error('The name field is not on the page.');
    }

    return input;
}

afterEach(() => {
    mounted?.unmount();
    mounted = undefined;
    posts.length = 0;

    for (const key of Object.keys(errors)) {
        delete errors[key];
    }

    document.body.innerHTML = '';
});

describe('the dialog that starts a review', () => {
    it('offers the name the listing gave and every kind, and sends both', async () => {
        await openDialog();

        expect(nameInput().value).toBe('Custom Review 4');

        buttonWith('Create review')?.click();
        await flushPromises();

        expect(posts).toHaveLength(1);
        expect(posts[0].url).toBe('/custom-reviews');
        expect(posts[0].data).toEqual({
            name: 'Custom Review 4',
            kinds: [
                'vocabulary',
                'phrase',
                'grammar_note',
                'mistake',
                'question',
            ],
        });
    });

    it('sends the corrected name and the narrower choice', async () => {
        await openDialog();

        nameInput().value = 'Café words';
        nameInput().dispatchEvent(new Event('input'));
        await flushPromises();

        buttonWith('Grammar notes')?.click();
        await flushPromises();

        buttonWith('Create review')?.click();
        await flushPromises();

        expect(posts[0].data).toEqual({
            name: 'Café words',
            kinds: ['vocabulary', 'phrase', 'mistake', 'question'],
        });
    });

    it('starts a fresh review each time it opens', async () => {
        const wrapper = await openDialog();

        nameInput().value = 'Half typed';
        nameInput().dispatchEvent(new Event('input'));
        await flushPromises();

        await wrapper.setProps({ open: false });
        await wrapper.setProps({ open: true });
        await flushPromises();

        expect(nameInput().value).toBe('Custom Review 4');
    });

    it('says what the server refused about the name', async () => {
        errors.name = 'Give this review a name.';

        await openDialog();

        expect(document.body.textContent).toContain('Give this review a name.');
    });
});
