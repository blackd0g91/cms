<?php

use App\Cms\Settings;
use App\Models\Media;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests can not change settings', function () {
    $this->get(route('cp.settings.edit'))->assertRedirect(route('cp.login'));
    $this->put(route('cp.settings.update'), ['site_name' => 'Hacked'])->assertRedirect(route('cp.login'));
});

test('settings fall back to defaults', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('cp.settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('cp/Settings')
            ->where('settings.site_name', config('app.name'))
            ->where('settings.tagline', null));
});

test('settings can be updated', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('cp.settings.update'), [
            'site_name' => 'Garmr',
            'tagline' => 'Notes and recipes',
            'home_intro' => 'Hello **there**',
            'footer_text' => 'Made in {year}',
        ])
        ->assertRedirect(route('cp.settings.edit'));

    $settings = app(Settings::class);

    expect($settings->siteName())->toBe('Garmr')
        ->and($settings->get('tagline'))->toBe('Notes and recipes')
        ->and($settings->footer())->toBe('Made in '.now()->year);
});

test('the site name is required', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('cp.settings.update'), ['site_name' => ''])
        ->assertSessionHasErrors('site_name');
});

test('the control panel uses the site name', function () {
    app(Settings::class)->update(['site_name' => 'Garmr']);

    $this->actingAs(User::factory()->create())
        ->get(route('cp.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('name', 'Garmr'));
});

test('the public site shows the settings', function () {
    app(Settings::class)->update([
        'site_name' => 'Garmr',
        'tagline' => 'Notes and recipes',
        'home_intro' => 'Hello **there**',
        'footer_text' => '{site_name} since {year}',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<title>Garmr</title>', false)
        ->assertSee('Notes and recipes')
        ->assertSee('Hello <strong>there</strong>', false)
        ->assertSee('Garmr since '.now()->year);
});

test('the footer has a default', function () {
    app(Settings::class)->update(['site_name' => 'Garmr']);

    $this->get(route('home'))->assertSee('© '.now()->year.' Garmr');
});

test('a logo and site icon can be chosen', function () {
    $logo = Media::factory()->create();
    $icon = Media::factory()->create(['mime_type' => 'image/png']);

    $this->actingAs(User::factory()->create())
        ->put(route('cp.settings.update'), [
            'site_name' => 'Garmr',
            'logo_id' => $logo->id,
            'favicon_id' => $icon->id,
        ])
        ->assertSessionHasNoErrors();

    $this->get(route('cp.settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('settings.logo_id', $logo->id)
            ->where("media.{$logo->id}.url", $logo->url)
            ->where("media.{$icon->id}.url", $icon->url));

    $this->get(route('home'))
        ->assertSee('src="'.$logo->url.'"', false)
        ->assertSee('<link rel="icon" href="'.$icon->url.'" type="image/png">', false)
        ->assertSee('<link rel="apple-touch-icon" href="'.$icon->url.'">', false)
        ->assertDontSee('/favicon.ico');

    $this->get(route('cp.dashboard'))->assertSee('<link rel="icon" href="'.$icon->url.'"', false);
});

test('logo and icon must be existing media', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('cp.settings.update'), ['site_name' => 'Garmr', 'logo_id' => 999, 'favicon_id' => 999])
        ->assertSessionHasErrors(['logo_id', 'favicon_id']);
});

test('without a logo and icon the defaults are used', function () {
    app(Settings::class)->update(['site_name' => 'Garmr']);

    $this->get(route('home'))
        ->assertSee('/favicon.ico')
        ->assertSee('/apple-touch-icon.png')
        ->assertSee('rounded-full bg-ink', false);
});

test('a deleted logo falls back to the letter badge', function () {
    $logo = Media::factory()->create();
    app(Settings::class)->update(['site_name' => 'Garmr', 'logo_id' => $logo->id]);

    $this->actingAs(User::factory()->create())
        ->delete(route('cp.media.destroy', ['media' => $logo, 'force' => 1]));

    $this->get(route('home'))->assertOk()->assertDontSee($logo->url);
});

test('the logo and icon count as media usage', function () {
    $logo = Media::factory()->create();
    app(Settings::class)->update(['logo_id' => $logo->id]);

    $this->actingAs(User::factory()->create())
        ->delete(route('cp.media.destroy', $logo))
        ->assertSessionHasErrors(['media' => 'This image is still used in: Site logo.']);
});

test('an svg site icon keeps the default apple touch icon and is not used for link previews', function () {
    $icon = Media::factory()->create(['mime_type' => 'image/svg+xml', 'path' => 'media/icon.svg']);
    $logo = Media::factory()->create(['mime_type' => 'image/svg+xml', 'path' => 'media/logo.svg']);
    app(Settings::class)->update(['favicon_id' => $icon->id, 'logo_id' => $logo->id]);

    $this->get(route('home'))
        ->assertSee('<link rel="icon" href="'.$icon->url.'" type="image/svg+xml">', false)
        ->assertSee('<link rel="apple-touch-icon" href="/apple-touch-icon.png">', false)
        ->assertSee('src="'.$logo->url.'"', false)
        ->assertDontSee('og:image', false);
});
