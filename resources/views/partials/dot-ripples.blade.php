{{--
    The error pages' background: a grid of dots that ripples now and then
    like water (and where clicked), lighting up near the pointer. The status
    code, marked with data-dot-code, is redrawn in the grid's dots, as on a
    dot-matrix display.

    Self-contained, with the script inline, as server error pages can not rely
    on the built assets (see errors/layouts/standalone.blade.php). Expects
    $colors, CSS colors for the ripples. Visitors who prefer less motion get
    a still grid.
--}}
<canvas
    data-dot-ripples
    data-colors="{{ json_encode($colors) }}"
    aria-hidden="true"
    style="position: absolute; inset: 0; z-index: -1; pointer-events: none"
></canvas>
<script>
    // Started once the page is parsed, as the canvas comes before it.
    const startDotRipples = () => {
        const canvas = document.querySelector('[data-dot-ripples]');
        const context = canvas.getContext('2d');
        const colors = JSON.parse(canvas.dataset.colors);
        const stillMedia = matchMedia('(prefers-reduced-motion: reduce)');
        const GAP = 16;
        const rings = [];
        const pointer = { x: -999, y: -999, inside: false, strength: 0 };
        let width = 0;
        let height = 0;
        let code = null;
        let nextRing = 0;
        let last = -Infinity;
        let frame = 0;

        // Digits on a 5 by 7 dot matrix, like an LED display: each row as
        // five bits, the leftmost dot first.
        const DIGITS = {
            0: [14, 17, 19, 21, 25, 17, 14],
            1: [4, 12, 4, 4, 4, 4, 14],
            2: [14, 17, 1, 2, 4, 8, 31],
            3: [31, 2, 4, 2, 1, 17, 14],
            4: [2, 6, 10, 18, 31, 2, 2],
            5: [31, 16, 30, 1, 1, 17, 14],
            6: [6, 8, 16, 30, 17, 17, 14],
            7: [31, 1, 2, 4, 8, 8, 8],
            8: [14, 17, 17, 14, 17, 17, 14],
            9: [14, 17, 17, 15, 1, 2, 12],
        };

        // The status code as grid dots, where the page shows it, in dots
        // of two by two grid dots when there is room.
        const traceCode = () => {
            const element = document.querySelector('[data-dot-code]');
            const digits = element?.textContent.trim().split('').filter((digit) => digit in DIGITS) ?? [];

            if (!digits.length) {
                return null;
            }

            element.style.color = '';
            const color = getComputedStyle(element).color;
            const box = element.getBoundingClientRect();
            const scale = box.height >= 10 * GAP ? 2 : 1;
            const column = Math.round((box.left + scrollX) / GAP);
            const row = Math.round((box.top + scrollY + (box.height - 7 * scale * GAP) / 2) / GAP);
            const dots = new Set();

            digits.forEach((digit, index) => {
                DIGITS[digit].forEach((bits, y) => {
                    for (let x = 0; x < 5; x++) {
                        if (bits & (16 >> x)) {
                            for (let sy = 0; sy < scale; sy++) {
                                for (let sx = 0; sx < scale; sx++) {
                                    const gx = column + (index * 6 + x) * scale + sx;
                                    const gy = row + y * scale + sy;
                                    dots.add(`${gx * GAP + GAP / 2},${gy * GAP + GAP / 2}`);
                                }
                            }
                        }
                    }
                });
            });

            // The dots stand in for the text, which stays as the fallback.
            element.style.color = 'transparent';

            return {
                dots,
                color,
                x: (column + (digits.length * 6 * scale) / 2) * GAP,
                y: (row + 3.5 * scale) * GAP,
            };
        };

        const resize = () => {
            redraw();

            const ratio = Math.min(2, window.devicePixelRatio || 1);

            canvas.style.height = '0';
            width = document.documentElement.scrollWidth;
            height = Math.max(document.documentElement.scrollHeight, window.innerHeight);
            canvas.style.width = `${width}px`;
            canvas.style.height = `${height}px`;
            canvas.width = Math.round(width * ratio);
            canvas.height = Math.round(height * ratio);
            context.setTransform(ratio, 0, 0, ratio, 0, 0);
            code = traceCode();
        };

        const ripple = (x, y, time) => rings.push({ x, y, start: time, color: colors[rings.length % colors.length] });

        const draw = (time) => {
            const still = stillMedia.matches;
            const muted = getComputedStyle(document.documentElement).getPropertyValue('--muted').trim();

            // Standing still, the light follows the pointer without easing in.
            pointer.strength = still
                ? Number(pointer.inside)
                : pointer.strength + (Number(pointer.inside) - pointer.strength) * 0.15;

            if (!still && time > nextRing) {
                ripple(Math.random() * width, scrollY + Math.random() * window.innerHeight, time);
                nextRing = time + 3500 + Math.random() * 2500;
            }

            while (rings.length && time - rings[0].start > 3200) {
                rings.shift();
            }

            context.clearRect(0, 0, width, height);

            for (let y = GAP / 2; y < height; y += GAP) {
                for (let x = GAP / 2; x < width; x += GAP) {
                    let lit = Math.max(0, 1 - Math.hypot(pointer.x - x, pointer.y - y) / 130) * pointer.strength;
                    let color = colors[Math.min(colors.length - 1, Math.floor((x / width) * colors.length))];
                    let dx = 0;
                    let dy = 0;

                    if (!still) {
                        for (const ring of rings) {
                            const radius = (time - ring.start) * 0.22;
                            const distance = Math.hypot(x - ring.x, y - ring.y) || 1;
                            const wave = Math.exp(-(((distance - radius) / 16) ** 2)) * Math.max(0, 1 - radius / 700);

                            if (wave > lit) {
                                lit = wave;
                                color = ring.color;
                            }

                            dx += ((x - ring.x) / distance) * wave * 5;
                            dy += ((y - ring.y) / distance) * wave * 5;
                        }
                    }

                    const inCode = code?.dots.has(`${x},${y}`);

                    context.fillStyle = inCode ? code.color : lit > 0.03 ? color : muted;
                    context.globalAlpha = inCode ? 0.95 : lit > 0.03 ? 0.25 + Math.min(1, lit) * 0.7 : 0.3;
                    context.beginPath();
                    context.arc(x + dx, y + dy, inCode ? 2.6 + lit * 1.2 : 1.1 + Math.min(1, lit) * 1.4, 0, Math.PI * 2);
                    context.fill();
                }
            }

            context.globalAlpha = 1;
        };

        // Animated, the grid is redrawn about 30 times a second. Standing
        // still, only when something changes (see redraw).
        const loop = (time) => {
            if (time - last >= 33) {
                last = time;
                draw(time);
            }

            frame = stillMedia.matches ? 0 : requestAnimationFrame(loop);
        };

        const redraw = () => {
            if (!frame) {
                frame = requestAnimationFrame((time) => {
                    frame = 0;
                    draw(time);
                });
            }
        };

        const restart = () => {
            cancelAnimationFrame(frame);
            frame = 0;

            if (stillMedia.matches) {
                redraw();
            } else {
                frame = requestAnimationFrame(loop);
            }
        };

        document.addEventListener('pointermove', (event) => {
            if (event.pointerType === 'mouse') {
                pointer.x = event.pageX;
                pointer.y = event.pageY;
                pointer.inside = true;
                redraw();
            }
        });
        document.documentElement.addEventListener('pointerleave', () => {
            pointer.inside = false;
            redraw();
        });

        // A click anywhere but on a link or a field starts a ripple there.
        document.addEventListener('pointerdown', (event) => {
            if (!stillMedia.matches && !event.target.closest('a, button, input, summary, label')) {
                ripple(event.pageX, event.pageY, performance.now());
            }
        });

        window.addEventListener('resize', resize);
        stillMedia.addEventListener('change', restart);
        // The idle dots take the theme's muted color.
        new MutationObserver(redraw).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
        matchMedia('(prefers-color-scheme: dark)').addEventListener('change', redraw);
        new ResizeObserver(resize).observe(document.body);
        // Once the fonts are in, the code may have moved, so it is traced again.
        document.fonts?.ready.then(resize);

        resize();

        // The first ripple spreads from the code.
        if (code && !stillMedia.matches) {
            ripple(code.x, code.y, performance.now());
            nextRing = performance.now() + 3000;
        }

        restart();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startDotRipples);
    } else {
        startDotRipples();
    }
</script>
