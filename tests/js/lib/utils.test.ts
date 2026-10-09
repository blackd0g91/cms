import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { daysAgo, initials, slugify, timeAgo } from '@/lib/utils';

describe('slugify', () => {
    it('makes an address part from a title', () => {
        expect(slugify('Pão de Queijo, the Best!')).toBe(
            'pao-de-queijo-the-best',
        );
    });

    it('takes another separator', () => {
        expect(slugify('Main course', '_')).toBe('main_course');
    });

    it('leaves nothing for a title without letters or digits', () => {
        expect(slugify('¿¡!?')).toBe('');
    });
});

describe('initials', () => {
    it('takes up to two capital letters', () => {
        expect(initials('ana maria souza')).toBe('AM');
        expect(initials('  Ana  ')).toBe('A');
    });

    it('shows a question mark for an empty name', () => {
        expect(initials('')).toBe('?');
    });
});

describe('timeAgo and daysAgo', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date(2026, 9, 9, 12, 0));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('says how long ago briefly', () => {
        expect(timeAgo(new Date(2026, 9, 9, 11, 59, 50).toISOString())).toBe(
            'just now',
        );
        expect(timeAgo(new Date(2026, 9, 9, 11, 55).toISOString())).toBe(
            '5 min ago',
        );
        expect(timeAgo(new Date(2026, 9, 9, 9, 0).toISOString())).toBe(
            '3 h ago',
        );
        expect(timeAgo(new Date(2026, 9, 1).toISOString())).not.toMatch(/ago/);
    });

    it('counts calendar days, not hours', () => {
        expect(daysAgo(new Date(2026, 9, 9, 0, 5).toISOString())).toBe('today');
        expect(daysAgo(new Date(2026, 9, 8, 23, 55).toISOString())).toBe(
            'yesterday',
        );
        expect(daysAgo(new Date(2026, 8, 27).toISOString())).toBe(
            '12 days ago',
        );
    });
});
