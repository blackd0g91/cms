import { currentTheme } from './lib/theme';

/**
 * Contour lines behind every page of the site, like a topographic map that
 * slowly shifts (see site/partials/backdrop.blade.php). Every third line is
 * in one of the templates' colors. For visitors who prefer less motion, the
 * lines stand still.
 */

// Grid cell for tracing the lines, in CSS pixels, and how many lines.
const CELL = 16;
const LEVELS = 10;

const stillMedia = matchMedia('(prefers-reduced-motion: reduce)');
const coarseMedia = matchMedia('(pointer: coarse)');
const darkMedia = matchMedia('(prefers-color-scheme: dark)');

// The landscape the lines trace: a few slow waves added together.
const landscape = (x: number, y: number, t: number) =>
    Math.sin(x * 0.0065 + t * 0.6) * Math.cos(y * 0.008 - t * 0.4) +
    0.6 * Math.sin((x + y) * 0.004 + t * 0.35) +
    0.4 * Math.cos(Math.hypot(x - 300, y - 200) * 0.011 - t * 0.5);

type Point = [number, number];

// For each corner pattern of a cell (marching squares), which of its edges
// a line joins: 0 top, 1 right, 2 bottom, 3 left.
const SEGMENTS: Record<number, [number, number][]> = {
    1: [[3, 2]],
    2: [[2, 1]],
    3: [[3, 1]],
    4: [[0, 1]],
    5: [
        [3, 0],
        [2, 1],
    ],
    6: [[0, 2]],
    7: [[3, 0]],
    8: [[3, 0]],
    9: [[0, 2]],
    10: [
        [3, 2],
        [0, 1],
    ],
    11: [[0, 1]],
    12: [[3, 1]],
    13: [[2, 1]],
    14: [[3, 2]],
};

export function startContours(canvas: HTMLCanvasElement) {
    const context = canvas.getContext('2d');

    if (!context) {
        return;
    }

    const colors = JSON.parse(canvas.dataset.colors ?? '[]') as string[];
    let width = 0;
    let height = 0;
    let last = -Infinity;
    let frame = 0;

    const resize = () => {
        const ratio = Math.min(2, window.devicePixelRatio || 1);
        width = window.innerWidth;
        height = window.innerHeight;
        canvas.width = Math.round(width * ratio);
        canvas.height = Math.round(height * ratio);
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
    };

    const draw = (time: number) => {
        const t = stillMedia.matches ? 4 : time / 9000;
        const style = getComputedStyle(document.documentElement);
        const muted = style.getPropertyValue('--muted').trim();
        const theme = currentTheme();
        const dark = theme === 'system' ? darkMedia.matches : theme === 'dark';
        const cols = Math.ceil(width / CELL) + 1;
        const rows = Math.ceil(height / CELL) + 1;
        const values = new Float32Array(cols * rows);

        for (let j = 0; j < rows; j++) {
            for (let i = 0; i < cols; i++) {
                values[j * cols + i] = landscape(i * CELL, j * CELL, t);
            }
        }

        context.clearRect(0, 0, width, height);
        context.lineWidth = 1;

        for (let level = 0; level < LEVELS; level++) {
            const threshold = -1.6 + (level / (LEVELS - 1)) * 3.2;
            const tinted = level % 3 === 1 && colors.length > 0;

            context.strokeStyle = tinted
                ? colors[Math.floor(level / 3) % colors.length]
                : muted;
            context.globalAlpha = tinted
                ? dark
                    ? 0.5
                    : 0.45
                : dark
                  ? 0.24
                  : 0.22;
            context.beginPath();

            for (let j = 0; j < rows - 1; j++) {
                for (let i = 0; i < cols - 1; i++) {
                    const a = values[j * cols + i];
                    const b = values[j * cols + i + 1];
                    const c = values[(j + 1) * cols + i + 1];
                    const d = values[(j + 1) * cols + i];
                    const pattern =
                        (a > threshold ? 8 : 0) |
                        (b > threshold ? 4 : 0) |
                        (c > threshold ? 2 : 0) |
                        (d > threshold ? 1 : 0);
                    const segments = SEGMENTS[pattern];

                    if (!segments) {
                        continue;
                    }

                    const x = i * CELL;
                    const y = j * CELL;
                    // Where the line crosses each edge of the cell.
                    const along = (from: number, to: number) =>
                        (threshold - from) / (to - from);
                    const edges: Point[] = [
                        [x + along(a, b) * CELL, y],
                        [x + CELL, y + along(b, c) * CELL],
                        [x + along(d, c) * CELL, y + CELL],
                        [x, y + along(a, d) * CELL],
                    ];

                    for (const [from, to] of segments) {
                        context.moveTo(...edges[from]);
                        context.lineTo(...edges[to]);
                    }
                }
            }

            context.stroke();
        }

        context.globalAlpha = 1;
    };

    // The lines move slowly, so a few frames a second is plenty: fewer on
    // phones, to save their batteries.
    const loop = (time: number) => {
        if (time - last >= (coarseMedia.matches ? 100 : 50)) {
            last = time;
            draw(time);
        }

        if (!stillMedia.matches) {
            frame = requestAnimationFrame(loop);
        }
    };

    const restart = () => {
        cancelAnimationFrame(frame);
        resize();
        draw(performance.now());

        if (!stillMedia.matches) {
            frame = requestAnimationFrame(loop);
        }
    };

    window.addEventListener('resize', () => {
        resize();
        draw(performance.now());
    });
    stillMedia.addEventListener('change', restart);

    // Standing still, the lines are only redrawn when the theme changes.
    new MutationObserver(() => draw(performance.now())).observe(
        document.documentElement,
        {
            attributes: true,
            attributeFilter: ['data-theme'],
        },
    );
    darkMedia.addEventListener('change', () => draw(performance.now()));

    restart();
}
