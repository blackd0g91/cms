/*
 * The site's service worker, for push notifications: it shows the reminders
 * the server pushes (see app/Cms/PushNotifications.php), even with the site
 * closed, and opens the site when one is clicked. Served as it is, not built,
 * as it has to live at the site's root.
 */

self.addEventListener('push', (event) => {
    const message = event.data ? event.data.json() : {};

    event.waitUntil(
        self.registration.showNotification(message.title || 'Reminder', {
            body: message.body || '',
            // The same reminder notified by an open page as well shows once.
            tag: message.tag,
            icon: '/apple-touch-icon.png',
            data: { url: message.url || '/' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = new URL(event.notification.data.url, self.location.origin).href;

    // A tab of the site that is open already, or a new one.
    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((windows) => {
                const open = windows.find(
                    (each) => new URL(each.url).origin === self.location.origin,
                );

                if (open) {
                    return open
                        .focus()
                        .then((client) =>
                            client.url === url ? client : client.navigate(url),
                        );
                }

                return self.clients.openWindow(url);
            }),
    );
});
