import { HeartIcon, SettingsIcon } from '@lucide/vue';
import type { Component } from 'vue';
import { index as friendsIndex } from '@/routes/friends';
import { edit as settingsEdit } from '@/routes/settings';

export type NavItem = {
    key: string;
    href: string;
    icon: Component;
};

/**
 * List the account menu targets of the signed-in app.
 */
export function navItems(): NavItem[] {
    // The logo leads to the dashboard, so the menu holds no dashboard entry (requirements section 6)
    return [
        { key: 'nav.settings', href: settingsEdit().url, icon: SettingsIcon },
        { key: 'nav.friends', href: friendsIndex().url, icon: HeartIcon },
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
