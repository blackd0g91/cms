/**
 * Small enhancements for the public site. Everything works without them.
 */

// Press "/" anywhere to jump to the search box.
document.addEventListener('keydown', (event) => {
    const target = event.target as HTMLElement;
    const typing =
        target.isContentEditable ||
        ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName);

    if (event.key === '/' && !typing && !event.ctrlKey && !event.metaKey) {
        event.preventDefault();
        document.querySelector<HTMLInputElement>('#site-search')?.focus();
    }
});

// A copy button on every code block, for cheatsheets.
document.querySelectorAll<HTMLPreElement>('.prose pre').forEach((pre) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = 'Copy';
    button.className =
        'absolute top-2 right-2 rounded-md border border-line bg-card px-2 py-0.5 font-mono text-xs text-muted opacity-0 transition group-hover:opacity-100 focus:opacity-100 hover:text-ink';

    button.addEventListener('click', async () => {
        const code = pre.querySelector('code')?.innerText ?? pre.innerText;

        try {
            await navigator.clipboard.writeText(code);
            button.textContent = 'Copied';
        } catch {
            button.textContent = 'Press Ctrl+C';
        }

        setTimeout(() => (button.textContent = 'Copy'), 1500);
    });

    pre.classList.add('group');
    pre.append(button);
});

// Highlight the table of contents entry for the section being read.
const tocLinks = Array.from(
    document.querySelectorAll<HTMLAnchorElement>('[data-toc] a[href^="#"]'),
);

const tocTargets = [...new Set(tocLinks.map((link) => link.hash.slice(1)))]
    .map((id) => document.getElementById(id))
    .filter((heading): heading is HTMLElement => heading !== null);

if (tocTargets.length > 0) {
    const setActive = (id: string) =>
        tocLinks.forEach((link) =>
            link.toggleAttribute('data-active', link.hash === `#${id}`),
        );

    const update = () => {
        // The last heading above the top quarter of the screen is "current".
        const line = window.innerHeight * 0.25;
        const current =
            tocTargets
                .filter((h) => h.getBoundingClientRect().top <= line)
                .pop() ?? tocTargets[0];

        setActive(current.id);
    };

    window.addEventListener('scroll', update, { passive: true });
    update();
}
