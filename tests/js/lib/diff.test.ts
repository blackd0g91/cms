import { describe, expect, it } from 'vite-plus/test';
import { diffLines } from '@/lib/diff';

describe('diffLines', () => {
    it('keeps lines both texts share', () => {
        expect(diffLines('a\nb', 'a\nb')).toEqual([
            { type: 'same', text: 'a' },
            { type: 'same', text: 'b' },
        ]);
    });

    it('marks lines added and removed around the shared ones', () => {
        expect(
            diffLines('title\nold line\nend', 'title\nnew line\nend\nmore'),
        ).toEqual([
            { type: 'same', text: 'title' },
            { type: 'removed', text: 'old line' },
            { type: 'added', text: 'new line' },
            { type: 'same', text: 'end' },
            { type: 'added', text: 'more' },
        ]);
    });

    it('finds the longest run of shared lines', () => {
        const types = diffLines('a\nb\nc\nd', 'b\nc\nd\ne').map(
            (line) => `${line.type}:${line.text}`,
        );

        expect(types).toEqual([
            'removed:a',
            'same:b',
            'same:c',
            'same:d',
            'added:e',
        ]);
    });

    it('shows very long texts as fully replaced, rather than comparing them', () => {
        const before = Array.from({ length: 2001 }, (_, i) => `line ${i}`).join(
            '\n',
        );
        const after = Array.from({ length: 2001 }, (_, i) => `line ${i}`).join(
            '\n',
        );
        const lines = diffLines(before, after);

        expect(lines).toHaveLength(4002);
        expect(lines.every((line) => line.type !== 'same')).toBe(true);
    });
});
