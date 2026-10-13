import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import {
    keyBytes,
    readReminders,
    startNotifyToggle,
    startReminders,
} from '@/reminders';

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

describe('system notifications', () => {
    const created: { title: string; options: NotificationOptions }[] = [];
    let permission: NotificationPermission = 'default';
    const requestPermission = vi.fn(async () => permission);

    class FakeNotification {
        static get permission() {
            return permission;
        }

        static requestPermission = requestPermission;

        constructor(title: string, options: NotificationOptions) {
            created.push({ title, options });
        }

        addEventListener() {}

        close() {}
    }

    const toggleHtml = `
        <div data-reminder-notify hidden>
            <p data-notify-state="off" hidden><button data-notify-on>On</button></p>
            <p data-notify-state="on" hidden><button data-notify-off>Off</button></p>
            <p data-notify-state="blocked" hidden>Blocked</p>
        </div>`;
    const state = () =>
        document.querySelector<HTMLElement>('[data-notify-state]:not([hidden])')
            ?.dataset.notifyState;
    const setVisibility = (value: DocumentVisibilityState) =>
        Object.defineProperty(document, 'visibilityState', {
            value,
            configurable: true,
        });

    beforeEach(() => {
        vi.stubGlobal('Notification', FakeNotification);
        permission = 'default';
        created.length = 0;
        requestPermission.mockClear();
        localStorage.clear();
        document.body.innerHTML = toggleHtml;
    });

    afterEach(() => {
        setVisibility('visible');
        document.body.innerHTML = '';
        localStorage.clear();
    });

    it('asks the browser only when the switch is clicked', async () => {
        startNotifyToggle();

        expect(state()).toBe('off');
        expect(requestPermission).not.toHaveBeenCalled();

        permission = 'granted';
        document.querySelector<HTMLButtonElement>('[data-notify-on]')!.click();
        await vi.waitFor(() => expect(state()).toBe('on'));

        expect(requestPermission).toHaveBeenCalledOnce();
        expect(localStorage.getItem('site.reminders.notify')).toBe('1');

        document.querySelector<HTMLButtonElement>('[data-notify-off]')!.click();
        expect(state()).toBe('off');
        expect(localStorage.getItem('site.reminders.notify')).toBeNull();
    });

    it('works as a bell button too, with one button per mode when on', async () => {
        document.body.innerHTML = `
            <div data-reminder-notify hidden>
                <button data-notify-state="off" data-notify-on hidden>Bell</button>
                <span data-notify-state="on" hidden>
                    <button data-notify-mode="push" data-notify-off hidden>On, closed</button>
                    <button data-notify-mode="page" data-notify-off>On, open</button>
                </span>
                <button data-notify-state="blocked" hidden>Blocked</button>
            </div>`;
        permission = 'granted';
        startNotifyToggle();

        document.querySelector<HTMLButtonElement>('[data-notify-on]')!.click();
        await vi.waitFor(() => expect(state()).toBe('on'));

        const page = document.querySelector<HTMLButtonElement>(
            '[data-notify-mode="page"]',
        )!;
        expect(page.hidden).toBe(false);

        page.click();
        await vi.waitFor(() => expect(state()).toBe('off'));
    });

    it('says when the browser blocks them', () => {
        permission = 'denied';
        startNotifyToggle();

        expect(state()).toBe('blocked');
    });

    it('stays hidden in browsers without notifications', () => {
        vi.stubGlobal('Notification', undefined);
        startNotifyToggle();

        expect(
            document.querySelector<HTMLElement>('[data-reminder-notify]')!
                .hidden,
        ).toBe(true);
    });

    describe('when a reminder comes due', () => {
        const now = new Date('2026-10-12T15:00:00Z');

        beforeEach(() => {
            vi.useFakeTimers();
            vi.setSystemTime(now);
            document.body.innerHTML = `<ul>${item(1, new Date(now.getTime() + 60_000), 'Call the dentist')}</ul>`;
        });

        afterEach(() => vi.useRealTimers());

        it('notifies while the tab is out of sight, when turned on', () => {
            permission = 'granted';
            localStorage.setItem('site.reminders.notify', '1');
            setVisibility('hidden');
            startReminders();
            vi.advanceTimersByTime(65_000);

            expect(created).toEqual([
                {
                    title: 'Reminder',
                    options: expect.objectContaining({
                        body: 'Call the dentist',
                        tag: 'reminder-1',
                    }),
                },
            ]);
        });

        it('does not notify while the tab is in sight, where the notice shows', () => {
            permission = 'granted';
            localStorage.setItem('site.reminders.notify', '1');
            startReminders();
            vi.advanceTimersByTime(65_000);

            expect(created).toHaveLength(0);
            expect(document.querySelector('.reminder-notice')).not.toBeNull();
        });

        it('does not notify unless turned on in this browser', () => {
            permission = 'granted';
            setVisibility('hidden');
            startReminders();
            vi.advanceTimersByTime(65_000);

            expect(created).toHaveLength(0);
        });
    });
});

describe('push notifications', () => {
    const fetchCalls: { url: string; init: RequestInit }[] = [];
    const unsubscribe = vi.fn(async () => true);
    const subscription = {
        endpoint: 'https://push.example/abc',
        toJSON: () => ({
            endpoint: 'https://push.example/abc',
            keys: { p256dh: 'p', auth: 'a' },
        }),
        unsubscribe,
    };
    const subscribe = vi.fn(async (_options: unknown) => subscription);
    let current: typeof subscription | null = null;
    const registration = {
        pushManager: {
            getSubscription: async () => current,
            subscribe: async (options: unknown) => {
                current = await subscribe(options);

                return current;
            },
        },
    };

    beforeEach(() => {
        current = null;
        fetchCalls.length = 0;
        subscribe.mockClear();
        unsubscribe.mockClear();
        localStorage.clear();
        vi.stubGlobal('Notification', {
            permission: 'default',
            requestPermission: async () => 'granted',
        });
        vi.stubGlobal('PushManager', {
            supportedContentEncodings: ['aes128gcm'],
        });
        Object.defineProperty(navigator, 'serviceWorker', {
            configurable: true,
            value: {
                register: vi.fn(async () => registration),
                ready: Promise.resolve(registration),
                getRegistration: async () => registration,
            },
        });
        vi.stubGlobal('fetch', async (url: string, init: RequestInit) => {
            fetchCalls.push({ url, init });

            return { ok: true };
        });
        document.body.dataset.csrfToken = 'the-token';
        document.body.innerHTML = `
            <div data-reminder-notify data-push-key="AQID" data-push-url="/me/push-subscriptions" hidden>
                <p data-notify-state="off" hidden><button data-notify-on>On</button></p>
                <p data-notify-state="on" hidden>
                    <span data-notify-mode="push" hidden>closed</span><span data-notify-mode="page">open</span>
                    <button data-notify-off>Off</button>
                </p>
                <p data-notify-state="blocked" hidden>Blocked</p>
            </div>`;
    });

    afterEach(() => {
        Reflect.deleteProperty(navigator, 'serviceWorker');
        document.body.innerHTML = '';
        localStorage.clear();
    });

    const mode = () =>
        document.querySelector<HTMLElement>('[data-notify-mode]:not([hidden])')
            ?.dataset.notifyMode;

    it("decodes the site's public key for the browser", () => {
        expect([...keyBytes('AQID')]).toEqual([1, 2, 3]);
        expect([...keyBytes('-_8')]).toEqual([251, 255]);
    });

    it('signs the browser up, and tells the site where to push', async () => {
        startNotifyToggle();
        document.querySelector<HTMLButtonElement>('[data-notify-on]')!.click();

        await vi.waitFor(() => expect(fetchCalls).toHaveLength(1));
        await vi.waitFor(() => expect(mode()).toBe('push'));

        expect(subscribe).toHaveBeenCalledWith({
            userVisibleOnly: true,
            applicationServerKey: new Uint8Array([1, 2, 3]),
        });
        expect(fetchCalls[0].url).toBe('/me/push-subscriptions');
        expect(fetchCalls[0].init.method).toBe('POST');
        expect(JSON.parse(fetchCalls[0].init.body as string)).toEqual({
            endpoint: 'https://push.example/abc',
            keys: { p256dh: 'p', auth: 'a' },
            contentEncoding: 'aes128gcm',
        });
    });

    it('takes the browser off again when turned off', async () => {
        localStorage.setItem('site.reminders.notify', '1');
        localStorage.setItem('site.reminders.push', '1');
        current = subscription;
        vi.stubGlobal('Notification', { permission: 'granted' });
        startNotifyToggle();

        document.querySelector<HTMLButtonElement>('[data-notify-off]')!.click();

        await vi.waitFor(() => expect(unsubscribe).toHaveBeenCalledOnce());
        expect(fetchCalls[0].init.method).toBe('DELETE');
        expect(localStorage.getItem('site.reminders.push')).toBeNull();
    });

    it('notifies only while a page is open where the site has no push keys', async () => {
        document
            .querySelector<HTMLElement>('[data-reminder-notify]')!
            .setAttribute('data-push-key', '');
        startNotifyToggle();
        document.querySelector<HTMLButtonElement>('[data-notify-on]')!.click();

        await vi.waitFor(() =>
            expect(localStorage.getItem('site.reminders.notify')).toBe('1'),
        );
        expect(fetchCalls).toHaveLength(0);
        expect(mode()).toBe('page');
    });
});
