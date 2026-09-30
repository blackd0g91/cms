import type { InjectionKey } from 'vue';

/**
 * Opens the control panel's sidebar, which is hidden on small screens.
 * Provided by CpLayout, for the menu button in PageHeader.
 */
export const openSidebarKey: InjectionKey<() => void> = Symbol('openSidebar');
