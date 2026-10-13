<?php

/*
 * Push notifications, which reach members' devices even with the site
 * closed (see App\Cms\PushNotifications). The browsers' push services only
 * accept them signed with this pair of keys, made once with
 * `php artisan app:push-keys`. Without keys, pushes are off.
 */
return [

    'public_key' => env('WEBPUSH_PUBLIC_KEY'),

    'private_key' => env('WEBPUSH_PRIVATE_KEY'),

    // Who sends them, for the push services to reach if needed: the site's
    // address, or a mailto: address.
    'subject' => env('WEBPUSH_SUBJECT', env('APP_URL')),

];
