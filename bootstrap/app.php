<?php

use App\Http\Middleware\CountReferrers;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/web.php',
            __DIR__.'/../routes/cp.php',
            __DIR__.'/../routes/site.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // To log in: on the control panel's own page for its pages, on the
        // site's for the rest.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('cp', 'cp/*') ? route('cp.login') : route('login'));
        // Already logged in, the login pages lead on: the control panel's to
        // it (or, for a member, who can not use it, to the site), the site's
        // to its home page.
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('cp', 'cp/*') && $request->user()?->isStaff() ? route('cp.dashboard') : route('me'));

        // Only counts a click (see ClickController), and is sent without a form.
        $middleware->validateCsrfTokens(except: ['clicks']);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            CountReferrers::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
