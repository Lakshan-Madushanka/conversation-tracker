import { mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import SessionMenu from '@/components/SessionMenu.vue';

let mounted: VueWrapper | undefined;

interface MenuOptions {
    topic?: string;
    editHref?: string | null;
    archived?: boolean;
    finished?: boolean;
}

/** Opens the menu the way a learner does, then returns its portal items. */
async function openMenu(options: MenuOptions = {}): Promise<HTMLElement[]> {
    mounted = mount(SessionMenu, {
        attachTo: document.body,
        props: {
            topic: 'Ordering at a restaurant',
            editHref: '/sessions/7/edit',
            archived: false,
            finished: true,
            ...options,
        },
    });

    mounted.get('button').element.click();
    await mounted.vm.$nextTick();
    await mounted.vm.$nextTick();

    return [...document.querySelectorAll<HTMLElement>('[role="menuitem"]')];
}

function itemWith(items: HTMLElement[], text: string): HTMLElement | undefined {
    return items.find((item) => item.textContent?.includes(text));
}

afterEach(() => {
    mounted?.unmount();
    mounted = undefined;
    document.body.innerHTML = '';
});

describe('the menu on a session', () => {
    it('names itself for the session it belongs to', () => {
        const wrapper = mount(SessionMenu, {
            props: {
                topic: 'Ordering at a restaurant',
                editHref: '/sessions/7/edit',
                archived: false,
                finished: true,
            },
        });

        expect(wrapper.get('button').attributes('aria-label')).toBe(
            'Actions for Ordering at a restaurant',
        );
    });

    it('offers Edit and Archive while the session is in the Library', async () => {
        const items = await openMenu();

        expect(items.map((item) => item.textContent?.trim())).toEqual([
            'Edit',
            'Archive',
        ]);

        expect(itemWith(items, 'Edit')?.getAttribute('href')).toBe(
            '/sessions/7/edit',
        );
    });

    it('archives a finished session from the menu', async () => {
        const items = await openMenu();

        itemWith(items, 'Archive')?.click();
        await mounted?.vm.$nextTick();

        expect(mounted?.emitted('archive')).toHaveLength(1);
        expect(mounted?.emitted('restore')).toBeUndefined();
    });

    it('offers only Restore once the session is archived', async () => {
        const items = await openMenu({ archived: true, editHref: null });

        expect(items.map((item) => item.textContent?.trim())).toEqual([
            'Restore',
        ]);
        expect(itemWith(items, 'Edit')).toBeUndefined();

        itemWith(items, 'Restore')?.click();
        await mounted?.vm.$nextTick();

        expect(mounted?.emitted('restore')).toHaveLength(1);
        expect(mounted?.emitted('archive')).toBeUndefined();
    });

    it('explains why an unfinished session cannot be archived', async () => {
        const items = await openMenu({ finished: false });
        const archive = itemWith(items, 'Archive');

        expect(archive?.textContent).toContain(
            'Finish this session to archive it.',
        );
        expect(archive?.getAttribute('data-disabled')).not.toBeNull();
        expect(archive?.getAttribute('aria-disabled')).toBe('true');

        archive?.click();
        await mounted?.vm.$nextTick();

        expect(mounted?.emitted('archive')).toBeUndefined();
    });
});
