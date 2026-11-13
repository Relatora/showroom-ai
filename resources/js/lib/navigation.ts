import { LayoutGrid } from '@lucide/vue';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

/**
 * The app's main sections, in one place.
 *
 * The sidebar and the ⌘K command palette both read from this list, so a new section only has
 * to be added once and can't end up in one but not the other.
 */
export const mainNavigation: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];
