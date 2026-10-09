import { afterEach, describe, expect, it } from 'vite-plus/test';
import { applyTheme, currentTheme, nextTheme, themeTitle } from '@/lib/theme';

afterEach(() => {
    delete document.documentElement.dataset.theme;
    localStorage.clear();
});

describe('theme', () => {
    it('steps through system, light and dark', () => {
        expect(nextTheme('system')).toBe('light');
        expect(nextTheme('light')).toBe('dark');
        expect(nextTheme('dark')).toBe('system');
    });

    it('says what the button does next', () => {
        expect(themeTitle('light')).toBe('Theme: light. Click for dark.');
    });

    it('applies and remembers a choice', () => {
        applyTheme('dark');

        expect(document.documentElement.dataset.theme).toBe('dark');
        expect(currentTheme()).toBe('dark');
        expect(localStorage.getItem('site.theme')).toBe('dark');
    });

    it('follows the system by leaving data-theme out', () => {
        applyTheme('light');
        applyTheme('system');

        expect(document.documentElement.dataset.theme).toBeUndefined();
        expect(currentTheme()).toBe('system');
        expect(localStorage.getItem('site.theme')).toBe('system');
    });

    it('treats an unknown data-theme as following the system', () => {
        document.documentElement.dataset.theme = 'sepia';

        expect(currentTheme()).toBe('system');
    });
});
