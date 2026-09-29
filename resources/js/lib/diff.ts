export type DiffLine = {
    type: 'same' | 'added' | 'removed';
    text: string;
};

/**
 * A line-by-line diff of two texts (longest common subsequence). Very long
 * texts that would be slow to compare show as fully replaced instead.
 */
export function diffLines(before: string, after: string): DiffLine[] {
    const a = before.split('\n');
    const b = after.split('\n');

    if (a.length * b.length > 4_000_000) {
        return [
            ...a.map((text) => ({ type: 'removed' as const, text })),
            ...b.map((text) => ({ type: 'added' as const, text })),
        ];
    }

    // lengths[i][j]: common lines between a[i..] and b[j..].
    const lengths = Array.from({ length: a.length + 1 }, () =>
        Array.from({ length: b.length + 1 }, () => 0),
    );

    for (let i = a.length - 1; i >= 0; i--) {
        for (let j = b.length - 1; j >= 0; j--) {
            lengths[i][j] =
                a[i] === b[j]
                    ? lengths[i + 1][j + 1] + 1
                    : Math.max(lengths[i + 1][j], lengths[i][j + 1]);
        }
    }

    const lines: DiffLine[] = [];
    let i = 0;
    let j = 0;

    while (i < a.length && j < b.length) {
        if (a[i] === b[j]) {
            lines.push({ type: 'same', text: a[i] });
            i++;
            j++;
        } else if (lengths[i + 1][j] >= lengths[i][j + 1]) {
            lines.push({ type: 'removed', text: a[i++] });
        } else {
            lines.push({ type: 'added', text: b[j++] });
        }
    }

    a.slice(i).forEach((text) => lines.push({ type: 'removed', text }));
    b.slice(j).forEach((text) => lines.push({ type: 'added', text }));

    return lines;
}
