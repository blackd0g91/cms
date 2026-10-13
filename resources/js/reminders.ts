/**
 * Reminders that come due while a page of the site is open: the home page's
 * panel and the reminders page list them with their time (see
 * site/reminders/item.blade.php). When one comes due, a notice shows in the
 * corner with Done and Snooze, the reminder is marked as due in the list,
 * and the tab's title counts the notices waiting, so they show from other
 * tabs too. Where the member turned them on, a system notification shows as
 * well while the tab is out of sight (see startNotifyToggle).
 */

// Whether system notifications are on in this browser: the member chose so
// here, and the browser allows them.
const NOTIFY_KEY = 'site.reminders.notify';

const supported = () => typeof Notification !== 'undefined';

export const notifyOn = () => {
    try {
        return (
            supported() &&
            Notification.permission === 'granted' &&
            localStorage.getItem(NOTIFY_KEY) === '1'
        );
    } catch {
        return false;
    }
};

const setNotify = (on: boolean) => {
    try {
        if (on) {
            localStorage.setItem(NOTIFY_KEY, '1');
        } else {
            localStorage.removeItem(NOTIFY_KEY);
        }
    } catch {
        // Not remembered (private windows): on for this visit only.
    }
};

/** A system notification for a reminder, which brings the tab back when clicked. */
const notifySystem = (text: string, tag: string) => {
    // The site's logo as a square (see AppIcon), as push notifications have.
    const icon = document.querySelector<HTMLLinkElement>(
        'link[rel="apple-touch-icon"]',
    )?.href;
    const notification = new Notification('Reminder', {
        body: text,
        tag,
        icon,
    });

    notification.addEventListener('click', () => {
        window.focus();
        notification.close();
    });
};

type Due = {
    id: string;
    due: number;
    text: string;
    doneUrl: string;
    snoozeUrl: string;
    items: HTMLElement[];
};

// How often to look: the page may have slept, so each look catches up.
const EVERY = 5_000;

/** The reminders on the page, once each, with every place they show. */
export function readReminders(root: ParentNode = document): Due[] {
    const byId = new Map<string, Due>();

    root.querySelectorAll<HTMLElement>('[data-reminder]').forEach((item) => {
        const id = item.dataset.reminder ?? '';
        const known = byId.get(id);

        if (known) {
            known.items.push(item);

            return;
        }

        byId.set(id, {
            id,
            due: Date.parse(item.dataset.reminderDue ?? ''),
            text: item.dataset.reminderText ?? '',
            doneUrl: item.dataset.reminderDone ?? '',
            snoozeUrl: item.dataset.reminderSnooze ?? '',
            items: [item],
        });
    });

    return [...byId.values()].filter((reminder) => !Number.isNaN(reminder.due));
}

/** A form with one button that posts to the site, as the list's own buttons do. */
const postForm = (
    action: string,
    label: string,
    fields: Record<string, string> = {},
) => {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = action;

    Object.entries({
        _token: document.body.dataset.csrfToken ?? '',
        ...fields,
    }).forEach(([name, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.append(input);
    });

    const button = document.createElement('button');
    button.type = 'submit';
    button.textContent = label;
    form.append(button);

    return form;
};

export function startReminders(root: ParentNode = document) {
    // Only those still to come: the overdue ones are already in the list.
    const waiting = readReminders(root).filter(
        (reminder) => reminder.due > Date.now(),
    );

    if (waiting.length === 0) {
        return;
    }

    const title = document.title;
    let shown = 0;

    const region = document.createElement('div');
    region.className = 'reminder-notices';
    region.setAttribute('role', 'status');
    region.setAttribute('aria-live', 'polite');
    document.body.append(region);

    const count = (change: number) => {
        shown += change;
        document.title = shown > 0 ? `(${shown}) ${title}` : title;
    };

    const notice = (reminder: Due) => {
        const box = document.createElement('div');
        box.className = 'reminder-notice';

        const heading = document.createElement('p');
        heading.className = 'reminder-notice-label';
        heading.textContent = 'Reminder';

        const text = document.createElement('p');
        text.className = 'reminder-notice-text';
        text.textContent = reminder.text;

        const actions = document.createElement('div');
        actions.className = 'reminder-notice-actions';

        const done = postForm(reminder.doneUrl, 'Done');
        done.querySelector('button')!.className = 'reminder-notice-primary';

        const snooze = postForm(reminder.snoozeUrl, 'Snooze an hour', {
            until: 'hour',
        });

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'reminder-notice-close';
        close.setAttribute('aria-label', 'Close');
        close.textContent = '×';
        close.addEventListener('click', () => {
            box.remove();
            count(-1);
        });

        actions.append(done, snooze);
        box.append(close, heading, text, actions);
        region.append(box);
        count(1);

        // Out of sight, the notice alone would go unseen.
        if (document.visibilityState !== 'visible' && notifyOn()) {
            notifySystem(reminder.text, `reminder-${reminder.id}`);
        }
    };

    const check = () => {
        const now = Date.now();

        for (const reminder of waiting.filter((each) => each.due <= now)) {
            waiting.splice(waiting.indexOf(reminder), 1);
            notice(reminder);

            // Due now in the list too.
            reminder.items.forEach((item) => {
                item.dataset.reminderNow = '';
                const when = item.querySelector('[data-reminder-when]');

                if (when) {
                    when.textContent = 'Now';
                }
            });
        }

        if (waiting.length === 0) {
            window.clearInterval(timer);
        }
    };

    const timer = window.setInterval(check, EVERY);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            check();
        }
    });
}

// Whether push notifications are on in this browser too (see push-worker.js).
const PUSH_KEY = 'site.reminders.push';

const pushOn = () => {
    try {
        return localStorage.getItem(PUSH_KEY) === '1';
    } catch {
        return false;
    }
};

const setPush = (on: boolean) => {
    try {
        if (on) {
            localStorage.setItem(PUSH_KEY, '1');
        } else {
            localStorage.removeItem(PUSH_KEY);
        }
    } catch {
        // Not remembered: the next visit shows them as on for open pages only.
    }
};

const pushSupported = (key: string) =>
    key !== '' && 'serviceWorker' in navigator && 'PushManager' in window;

/** The site's public push key, as the browser takes it. */
export const keyBytes = (key: string) => {
    const base64 = (key + '='.repeat((4 - (key.length % 4)) % 4))
        .replaceAll('-', '+')
        .replaceAll('_', '/');

    return Uint8Array.from(atob(base64), (char) => char.charCodeAt(0));
};

const sendSubscription = (
    url: string,
    method: 'POST' | 'DELETE',
    body: unknown,
) =>
    fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.body.dataset.csrfToken ?? '',
        },
        body: JSON.stringify(body),
    });

/**
 * Sign this browser up with its push service, through the site's service
 * worker, and tell the site where to push. Whether it worked.
 */
const subscribePush = async (key: string, url: string) => {
    try {
        const registration =
            await navigator.serviceWorker.register('/push-worker.js');
        await navigator.serviceWorker.ready;

        const subscription =
            (await registration.pushManager.getSubscription()) ??
            (await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: keyBytes(key),
            }));
        const { endpoint, keys } = subscription.toJSON();
        const encodings = PushManager.supportedContentEncodings ?? [];

        const response = await sendSubscription(url, 'POST', {
            endpoint,
            keys,
            contentEncoding: encodings.includes('aes128gcm')
                ? 'aes128gcm'
                : 'aesgcm',
        });

        return response.ok;
    } catch {
        return false;
    }
};

const unsubscribePush = async (url: string) => {
    try {
        const registration =
            await navigator.serviceWorker.getRegistration('/push-worker.js');
        const subscription = await registration?.pushManager.getSubscription();

        if (subscription) {
            await sendSubscription(url, 'DELETE', {
                endpoint: subscription.endpoint,
            });
            await subscription.unsubscribe();
        }
    } catch {
        // Already gone, or the browser can not tell: the site forgets it
        // once its push service says so.
    }
};

/**
 * The switch for system notifications in this browser (see
 * site/reminders/notify.blade.php), with a state for each case: they can be
 * turned on (asking the browser, only on a click), they are on (and can be
 * turned off), the browser blocks them, or it has none. Where the site and
 * the browser can do push notifications, turning them on signs the browser
 * up for those too, so they come even with the site closed.
 */
export function startNotifyToggle(root: ParentNode = document) {
    root.querySelectorAll<HTMLElement>('[data-reminder-notify]').forEach(
        (toggle) => {
            const show = () => {
                const state = !supported()
                    ? 'unsupported'
                    : Notification.permission === 'denied'
                      ? 'blocked'
                      : notifyOn()
                        ? 'on'
                        : 'off';

                toggle
                    .querySelectorAll<HTMLElement>('[data-notify-state]')
                    .forEach((part) => {
                        part.hidden = part.dataset.notifyState !== state;
                    });
                toggle
                    .querySelectorAll<HTMLElement>('[data-notify-mode]')
                    .forEach((part) => {
                        part.hidden =
                            part.dataset.notifyMode !==
                            (pushOn() ? 'push' : 'page');
                    });
                toggle.hidden = state === 'unsupported';
            };

            const key = toggle.dataset.pushKey ?? '';
            const url = toggle.dataset.pushUrl ?? '';

            const turnOn = async () => {
                const permission = await Notification.requestPermission();

                setNotify(permission === 'granted');
                show();

                // Then for the site closed too, which takes a moment.
                if (permission === 'granted' && pushSupported(key)) {
                    setPush(await subscribePush(key, url));
                    show();
                }
            };

            const turnOff = async () => {
                setNotify(false);

                if (pushOn()) {
                    setPush(false);
                    await unsubscribePush(url);
                }

                show();
            };

            // There may be more than one of each, like one per mode.
            toggle
                .querySelectorAll('[data-notify-on]')
                .forEach((button) => button.addEventListener('click', turnOn));
            toggle
                .querySelectorAll('[data-notify-off]')
                .forEach((button) => button.addEventListener('click', turnOff));

            show();
        },
    );
}
