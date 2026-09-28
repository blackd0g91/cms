<?php

use App\Cms\Settings;
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
