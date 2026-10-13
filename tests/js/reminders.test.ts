import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { readReminders, startReminders } from '../../resources/js/reminders';

const item = (id: number, due: Date, text: string) => `
    <li data-reminder="${id}" data-reminder-due="${due.toISOString()}" data-reminder-text="${text}"
        data-reminder-done="/me/reminders/${id}/done" data-reminder-snooze="/me/reminders/${id}/snooze">
        <p>${text}</p><p><span data-reminder-when>15:00</span></p>
    </li>`;

describe('reminders', () => {
    const now = new Date('2026-10-12T15:00:00Z');
    const inAMinute = new Date(now.getTime() + 60_000);
    const earlier = new Date(now.getTime() - 60_000);

    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(now);
        document.title = 'Home';
        document.body.dataset.csrfToken = 'the-token';
        // The same reminder in the panel and in a list, and one overdue.
        document.body.innerHTML = `
            <ul id="panel">${item(1, inAMinute, 'Call the dentist')}${item(2, earlier, 'Pay rent')}</ul>
            <ul id="list">${item(1, inAMinute, 'Call the dentist')}</ul>`;
    });

    afterEach(() => {
        vi.useRealTimers();
        document.body.innerHTML = '';
        delete document.body.dataset.csrfToken;
    });

    const notices = () => [...document.querySelectorAll('.reminder-notice')];

    it('reads each reminder once, with every place it shows', () => {
        const reminders = readReminders();

        expect(reminders).toHaveLength(2);
        expect(reminders[0]).toMatchObject({
            id: '1',
            text: 'Call the dentist',
            due: inAMinute.getTime(),
        });
        expect(reminders[0].items).toHaveLength(2);
    });

    it('shows a notice when one comes due, and marks it in the lists', () => {
        startReminders();

        expect(notices()).toHaveLength(0);

        vi.advanceTimersByTime(65_000);

        expect(notices()).toHaveLength(1);
        expect(notices()[0].textContent).toContain('Call the dentist');
        expect(document.title).toBe('(1) Home');
        document
            .querySelectorAll<HTMLElement>('[data-reminder="1"]')
            .forEach((each) => {
                expect(each.dataset.reminderNow).toBe('');
                expect(
                    each.querySelector('[data-reminder-when]')!.textContent,
                ).toBe('Now');
            });

        // Only once.
        vi.advanceTimersByTime(60_000);
        expect(notices()).toHaveLength(1);
    });

    it('never notices reminders that were already overdue', () => {
        startReminders();
        vi.advanceTimersByTime(120_000);

        expect(notices().map((notice) => notice.textContent)).not.toContain(
            expect.stringContaining('Pay rent'),
        );
    });

    it('has Done and Snooze, posting like the list does', () => {
        startReminders();
        vi.advanceTimersByTime(65_000);

        const [done, snooze] = [
            ...notices()[0].querySelectorAll<HTMLFormElement>('form'),
        ];

        expect(done.getAttribute('action')).toBe('/me/reminders/1/done');
        expect(done.method).toBe('post');
        expect(new FormData(done).get('_token')).toBe('the-token');
        expect(done.textContent).toBe('Done');
        expect(snooze.getAttribute('action')).toBe('/me/reminders/1/snooze');
        expect(new FormData(snooze).get('until')).toBe('hour');
    });

    it('closes, and the title stops counting it', () => {
        startReminders();
        vi.advanceTimersByTime(65_000);

        notices()[0]
            .querySelector<HTMLButtonElement>('.reminder-notice-close')!
            .click();

        expect(notices()).toHaveLength(0);
        expect(document.title).toBe('Home');
    });

    it('does nothing on pages without reminders to come', () => {
        document.body.innerHTML = `<ul>${item(2, earlier, 'Pay rent')}</ul>`;
        startReminders();

        expect(document.querySelector('.reminder-notices')).toBeNull();
    });
});
