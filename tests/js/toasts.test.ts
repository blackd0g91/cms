import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { startToasts, toast } from '@/toasts';

describe('toasts', () => {
    afterEach(() => {
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
});
