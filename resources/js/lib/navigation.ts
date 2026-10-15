import type { Component } from 'vue';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    ChartLineIcon,
    DownloadIcon,
    GraduationCapIcon,
    HouseIcon,
    LayersIcon,
    LibraryIcon,
    SettingsIcon,
    SquarePenIcon,
    UploadIcon,
} from '@lucide/vue';
import { home } from '@/routes';
import { index as collectionsIndex } from '@/routes/collections';
import { index as progressIndex } from '@/routes/progress';
import { index as reviewIndex } from '@/routes/review';
import {
    create as sessionsCreate,
    index as sessionsIndex,
} from '@/routes/sessions';
import {
    exportMethod as settingsExport,
    importMethod as settingsImport,
    index as settingsIndex,
} from '@/routes/settings';

export interface NavItem {
    label: string;
    /** Shorter wording used by the mobile bottom bar, where width is tight. */
    shortLabel?: string;
    href: string;
    icon: Component;
}

/** Order shown in the desktop / tablet sidebar. */
export const sidebarNav: NavItem[] = [
    { label: 'Home', href: home.url(), icon: HouseIcon },
    {
        label: 'New session',
        shortLabel: 'New',
        href: sessionsCreate.url(),
        icon: SquarePenIcon,
    },
    { label: 'Library', href: sessionsIndex.url(), icon: LibraryIcon },
    {
        label: 'Collections',
        href: collectionsIndex.url(),
        icon: LayersIcon,
    },
    { label: 'Review', href: reviewIndex.url(), icon: GraduationCapIcon },
    { label: 'Progress', href: progressIndex.url(), icon: ChartLineIcon },
    { label: 'Settings', href: settingsIndex.url(), icon: SettingsIcon },
];

/** The four destinations always visible on the mobile bottom bar. */
export const mobilePrimaryNav: NavItem[] = [
    sidebarNav[0],
    sidebarNav[1],
    sidebarNav[4],
    sidebarNav[2],
];

/** The destinations tucked behind the mobile "More" sheet. */
export const mobileMoreNav: NavItem[] = [
    sidebarNav[3],
    sidebarNav[5],
    sidebarNav[6],
];

export const settingsNav: NavItem[] = [
    { label: 'Preferences', href: settingsIndex.url(), icon: SettingsIcon },
    { label: 'Import', href: settingsImport.url(), icon: UploadIcon },
    { label: 'Export', href: settingsExport.url(), icon: DownloadIcon },
];

function pathMatches(href: string, path: string): boolean {
    if (href === '/') {
        return path === '/';
    }

    return path === href || path.startsWith(`${href}/`);
}

/**
 * Finds the most specific nav item matching the current URL, scoped to a
 * single nav's hrefs.
 *
 * Scoping keeps each nav independent: on `/sessions/create` the sidebar
 * highlights "New session" rather than "Library", and on `/settings/import`
 * the sidebar highlights "Settings" while the settings nav highlights
 * "Import".
 */
export function useActiveHref() {
    const page = usePage();
    const path = computed(() => page.url.split('?')[0] ?? '/');

    function activeWithin(hrefs: readonly string[]): string {
        const matches = hrefs.filter((href) => pathMatches(href, path.value));

        return matches.sort((a, b) => b.length - a.length)[0] ?? '';
    }

    return { activeWithin };
}
