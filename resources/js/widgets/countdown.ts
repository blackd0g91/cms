import { eachWidget } from './each';

/*
 * {{ countdown }}: kept up to date, in the reader's own time zone.
 */

/**
 * How long until (or since) a moment. Matches the wording of
 * CountdownWidget::describe(), which writes the text shown without script.
 */
export const describe = (
    target: Date,
    dateOnly: boolean,
    now = new Date(),
): string => {
    if (dateOnly) {
        const day = (d: Date) =>
            Date.UTC(d.getFullYear(), d.getMonth(), d.getDate());
        const days = Math.round((day(target) - day(now)) / 86_400_000);

        if (days === 0) return 'Today!';
        if (days === 1) return 'Tomorrow';
        if (days === -1) return 'Yesterday';

        return days > 1 ? `${days} days to go` : `${-days} days ago`;
    }

    const signed = Math.floor((target.getTime() - now.getTime()) / 60_000);
    const minutes = Math.abs(signed);
    const days = Math.floor(minutes / 1440);
    const hours = Math.floor((minutes % 1440) / 60);
    const rest = minutes % 60;
    const span =
        days >= 2
            ? `${days} days`
            : days === 1
              ? `1 day ${hours} h`
              : hours > 0
                ? `${hours} h ${rest} min`
                : minutes > 0
                  ? `${minutes} min`
                  : null;

    return span === null
        ? 'Now!'
        : signed >= 0
          ? `${span} to go`
          : `${span} ago`;
};

export function startCountdowns(root: ParentNode): () => void {
    const widgets: HTMLElement[] = [];
    const stop = eachWidget<HTMLElement>(
        root,
        '.widget-countdown[data-countdown]',
        (widget) => void widgets.push(widget),
    );

    if (!widgets.length) {
        return stop;
    }

    const update = () =>
        widgets.forEach((widget) => {
            const [date, time = '00:00'] = (
                widget.dataset.countdown ?? ''
            ).split('T');
            const [year, month, day] = date.split('-').map(Number);
            const [hour, minute] = time.split(':').map(Number);
            const value = widget.querySelector('.widget-countdown-value');

            if (value) {
                value.textContent = describe(
                    new Date(year, month - 1, day, hour, minute),
                    widget.hasAttribute('data-date-only'),
                );
            }
        });

    update();
    const interval = window.setInterval(update, 30_000);

    return () => {
        window.clearInterval(interval);
        stop();
    };
}
