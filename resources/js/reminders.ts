/**
 * Reminders that come due while a page of the site is open: the home page's
 * panel and the reminders page list them with their time (see
 * site/reminders/item.blade.php). When one comes due, a notice shows in the
 * corner with Done and Snooze, the reminder is marked as due in the list,
 * and the tab's title counts the notices waiting, so they show from other
 * tabs too.
 */

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
