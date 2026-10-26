import { HeartIcon, LayoutDashboardIcon, SettingsIcon } from '@lucide/vue';
import type { Component } from 'vue';
import { dashboard } from '@/routes';
import { index as friendsIndex } from '@/routes/friends';
import { edit as settingsEdit } from '@/routes/settings';

export type NavItem = {
    key: string;
    href: string;
    icon: Component;
};

/**
 * List the main navigation targets of the signed-in app.
 */
export function navItems(): NavItem[] {
    return [
        {
            key: 'nav.dashboard',
            href: dashboard().url,
            icon: LayoutDashboardIcon,
        },
        { key: 'nav.friends', href: friendsIndex().url, icon: HeartIcon },
        { key: 'nav.settings', href: settingsEdit().url, icon: SettingsIcon },
    ];
}

/**
 * Tell whether a nav target is the current page or one of its children.
 */
export function isCurrent(href: string, currentUrl: string): boolean {
    const path = currentUrl.split(/[?#]/)[0];

    // Friends lives under /settings, so settings only matches itself exactly
    if (href.endsWith('/settings')) {
        return path === href || path === `${href}/`;
    }

    return path === href || path.startsWith(`${href}/`);
}
