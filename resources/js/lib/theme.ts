/**
 * Light, dark, or following the system. The site and the control panel
 * share the choice, kept in this browser. It is applied before the page
 * draws (see resources/views/partials/theme.blade.php), as data-theme on
 * <html>; these read and change it afterwards.
 */
export type Theme = 'system' | 'light' | 'dark';

const KEY = 'site.theme';

export const currentTheme = (): Theme => {
    const theme = document.documentElement.dataset.theme;

    return theme === 'light' || theme === 'dark' ? theme : 'system';
};

export const applyTheme = (theme: Theme) => {
    // Switch every color at once, instead of some elements fading over.
    document.documentElement.classList.add('theme-switching');
    requestAnimationFrame(() =>
        requestAnimationFrame(() =>
            document.documentElement.classList.remove('theme-switching'),
        ),
    );

    if (theme === 'system') {
        delete document.documentElement.dataset.theme;
    } else {
        document.documentElement.dataset.theme = theme;
    }

    try {
        localStorage.setItem(KEY, theme);
    } catch {
        // Not remembering the choice is fine.
    }
};

// The theme buttons step through these in turn.
const order: Theme[] = ['system', 'light', 'dark'];

const labels: Record<Theme, string> = {
    system: 'Theme: follows your system',
    light: 'Theme: light',
    dark: 'Theme: dark',
};

export const nextTheme = (theme: Theme): Theme =>
    order[(order.indexOf(theme) + 1) % order.length];

/** What a theme button says it does, like "Theme: light. Click for dark." */
export const themeTitle = (theme: Theme) =>
    `${labels[theme]}. Click for ${nextTheme(theme)}.`;
