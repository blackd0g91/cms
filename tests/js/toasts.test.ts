import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { startToasts, toast, TOAST_SECONDS } from '@/toasts';

describe('toasts', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
        document.body.innerHTML = '';
    });

    const toasts = () => [...document.querySelectorAll('.toast')];

    it('closes the ones the page came with', () => {
        document.body.innerHTML = `
            <div class="toasts" data-toasts>
                <div class="toast" role="status">
                    <button type="button" class="toast-close" data-toast-close>×</button>
                    <p class="toast-text">Done.</p>
                </div>
            </div>`;
        startToasts();

        document.querySelector<HTMLButtonElement>('.toast-close')!.click();

        expect(toasts()).toHaveLength(0);
    });

    it('adds new ones to the same corner, with their label and actions', () => {
        document.body.innerHTML = '<div class="toasts" data-toasts></div>';
        const action = document.createElement('button');
        action.textContent = 'Done';

        toast({
            label: 'Reminder',
            text: 'Call the dentist',
            actions: [action],
        });

        expect(document.querySelectorAll('[data-toasts]')).toHaveLength(1);
        expect(toasts()).toHaveLength(1);
        expect(toasts()[0].querySelector('.toast-label')!.textContent).toBe(
            'Reminder',
        );
        expect(toasts()[0].querySelector('.toast-text')!.textContent).toBe(
            'Call the dentist',
        );
        expect(
            toasts()[0].querySelector('.toast-actions')!.contains(action),
        ).toBe(true);
    });

    it('makes the corner where the page has none, and says when closed', () => {
        const onClose = vi.fn();

        toast({ text: 'Saved.', onClose });

        expect(document.querySelector('[data-toasts]')).not.toBeNull();
        expect(toasts()[0].querySelector('.toast-label')).toBeNull();

        toasts()[0].querySelector<HTMLButtonElement>('.toast-close')!.click();

        expect(toasts()).toHaveLength(0);
        expect(onClose).toHaveBeenCalledOnce();
    });

    const later = (seconds: number) => vi.advanceTimersByTime(seconds * 1000);

    it('closes by itself after a few seconds, the ones the page came with too', () => {
        document.body.innerHTML = `
            <div class="toasts" data-toasts>
                <div class="toast"><button data-toast-close>×</button><p>Done.</p></div>
            </div>`;
        startToasts();
        toast({ text: 'Saved.' });

        later(TOAST_SECONDS - 1);
        expect(toasts()).toHaveLength(2);

        later(1);
        expect(
            toasts().every((each) => each.classList.contains('toast-leaving')),
        ).toBe(true);

        later(1);
        expect(toasts()).toHaveLength(0);
    });

    it('waits while the pointer is on it', () => {
        const box = toast({ text: 'Saved.' });

        box.dispatchEvent(new Event('pointerenter'));
        later(TOAST_SECONDS * 3);
        expect(toasts()).toHaveLength(1);

        box.dispatchEvent(new Event('pointerleave'));
        later(TOAST_SECONDS + 1);
        expect(toasts()).toHaveLength(0);
    });

    it('stays until closed, when it asks to', () => {
        toast({ label: 'Reminder', text: 'Call the dentist', stay: true });

        later(TOAST_SECONDS * 10);

        expect(toasts()).toHaveLength(1);
    });
});
