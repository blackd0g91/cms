<?php

use App\Cms\Settings;
use App\Models\Template;
use Illuminate\Foundation\Vite;
use Illuminate\Foundation\ViteException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;

test('an unknown address shows the site not found page, with its words in a search box', function () {
    // In the navigation, which the unmatched handle must not trip up.
    Template::factory()->create(['name' => 'Recipes']);

    $this->get('/no-such_thing')
        ->assertNotFound()
        ->assertSee('Page not found')
        ->assertSee('Recipes')
        ->assertSee('id="site-search"', false)
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertSee('value="no such thing"', false);
});

test('addresses deeper than any route get the not found page too', function () {
    $this->get('/a/b/old-post.html')
        ->assertNotFound()
        ->assertSee('Page not found')
        ->assertSee('value="old post"', false);
});

test('a missing post points to the other posts of its template, in its color', function () {
    $template = Template::factory()->create(['name' => 'Recipes', 'handle' => 'recipes', 'color' => '#aa3300']);

    $this->get('/recipes/chocolate-cake')
        ->assertNotFound()
        ->assertSee('All Recipes')
        ->assertSee('href="'.route('site.template', $template).'"', false)
        ->assertSee('--accent-base: #aa3300', false)
        ->assertSee('value="chocolate cake"', false);
});

test('other client errors use the site error page', function (int $status, string $title) {
    Route::get("_test/errors/{$status}", fn () => abort($status));

    $this->get("/_test/errors/{$status}")
        ->assertStatus($status)
        ->assertSee($title)
        ->assertSee('id="site-search"', false);
})->with([
    [403, 'No access'],
    [419, 'This page expired'],
    [429, 'Slow down a little'],
    [410, 'Something is off with this request'],
]);

test('server errors show a page that needs neither the database nor built assets', function () {
    config(['app.debug' => false, 'app.name' => 'Fallback name']);
    Route::get('_test/errors/crash', fn () => throw new RuntimeException('secret details'));
    $this->app->bind(Settings::class, fn () => throw new RuntimeException('the database is down'));

    $this->get('/_test/errors/crash')
        ->assertStatus(500)
        ->assertSee('Something went wrong')
        ->assertSee('Fallback name')
        ->assertDontSee('secret details')
        // Neither the development server's scripts nor the built ones.
        ->assertDontSee('site.ts', false)
        ->assertDontSee('type="module"', false)
        ->assertDontSee('rel="stylesheet"', false);
});

test('server errors use the built fonts when there are some, and the fallback fonts when not', function () {
    config(['app.debug' => false]);
    Route::get('_test/errors/crash', fn () => throw new RuntimeException('crash'));

    $vite = Mockery::mock(Vite::class)->makePartial();
    $vite->shouldReceive('fonts')->once()->with(['fraunces', 'instrument-sans'])->andReturn(new HtmlString('<style>/* The fonts */</style>'));
    $this->app->instance(Vite::class, $vite);

    $this->get('/_test/errors/crash')->assertStatus(500)->assertSee('<style>/* The fonts */</style>', false);

    // As when the build is missing, or older than the fonts.
    $vite = Mockery::mock(Vite::class)->makePartial();
    $vite->shouldReceive('fonts')->once()->andThrow(new ViteException('Font family [fraunces] not found.'));
    $this->app->instance(Vite::class, $vite);

    $this->get('/_test/errors/crash')->assertStatus(500)->assertSee('Something went wrong')->assertDontSee('/* The fonts */', false);
});

test('the maintenance page is rendered when the site goes down, the way deploy.sh does it', function () {
    // A throwaway storage folder, so the real site is never taken down.
    $storage = sys_get_temp_dir().'/cms-down-'.uniqid();
    File::makeDirectory("{$storage}/framework", recursive: true);
    $originalStorage = $this->app->storagePath();
    $this->app->useStoragePath($storage);

    try {
        app(Settings::class)->update(['site_name' => 'My Notebook']);

        $this->artisan('down', ['--retry' => 15, '--refresh' => 15, '--render' => 'errors::503'])->assertSuccessful();

        $this->get('/anything')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '15')
            ->assertHeader('Refresh', '15')
            ->assertSee('Back soon')
            ->assertSee('My Notebook')
            ->assertSee('Try again');
    } finally {
        $this->app->useStoragePath($originalStorage);
        File::deleteDirectory($storage);
    }
});
