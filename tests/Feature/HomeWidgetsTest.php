<?php

use App\Enums\HomeWidget;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Date::setTestNow('2026-10-11 15:00:00');

    // A picture and a game to show, trimmed to what the cards need.
    Http::fake([
        'api.wikimedia.org/*' => Http::response(['image' => [
            'title' => 'File:Lighthouse.jpg',
            'thumbnail' => ['source' => 'https://upload.wikimedia.org/lighthouse.jpg', 'width' => 640, 'height' => 480],
            'file_page' => 'https://commons.wikimedia.org/wiki/File:Lighthouse.jpg',
            'description' => ['text' => 'A lighthouse at dusk.'],
        ]]),
        'www.gamerpower.com/*' => Http::response([[
            'title' => 'Pony Island (Steam) Giveaway',
            'worth' => '$4.99',
            'thumbnail' => 'https://www.gamerpower.com/offers/1/pony.jpg',
            'description' => 'A puzzle game.',
            'open_giveaway_url' => 'https://www.gamerpower.com/open/pony-island',
            'type' => 'Game',
            'platforms' => 'PC, Steam',
            'end_date' => '2026-10-15 23:59:00',
            'users' => 12000,
            'status' => 'Active',
        ]]),
    ]);

    $this->member = User::factory()->member()->create();
});

// What marks each card on the home page.
const WEATHER_CARD = 'aria-label="Weather"';
const PICTURE_CARD = 'data-dialog-open="picture-of-the-day"';
const GAMES_CARD = 'data-dialog-open="free-games"';

test('guests, and members who have not chosen, get the weather and the picture of the day', function () {
    $this->get(route('home'))
        ->assertSeeInOrder([WEATHER_CARD, PICTURE_CARD], false)
        ->assertDontSee(GAMES_CARD, false);

    $this->actingAs($this->member)->get(route('home'))
        ->assertSeeInOrder([WEATHER_CARD, PICTURE_CARD], false)
        ->assertDontSee(GAMES_CARD, false);

    // What is not shown is not fetched.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'gamerpower'));
});

test('members choose their cards and their order', function () {
    $this->actingAs($this->member)
        ->put(route('account.widgets'), ['widgets' => ['games', 'weather']])
        ->assertRedirect(route('account.edit'))
        ->assertSessionHas('status', 'Your home page cards are saved.');

    expect($this->member->fresh()->home_widgets)->toBe(['games', 'weather']);

    $this->get(route('home'))
        ->assertSeeInOrder([GAMES_CARD, WEATHER_CARD], false)
        ->assertDontSee(PICTURE_CARD, false);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'wikimedia'));
});

test('members can have no cards at all, and go back to the usual ones', function () {
    $this->actingAs($this->member)->put(route('account.widgets'));

    expect($this->member->fresh()->home_widgets)->toBe([]);
    $this->get(route('home'))->assertDontSee(WEATHER_CARD, false)->assertDontSee(PICTURE_CARD, false);

    $this->put(route('account.widgets'), ['reset' => '1'])->assertSessionHas('status');

    expect($this->member->fresh()->home_widgets)->toBeNull();
    $this->get(route('home'))->assertSeeInOrder([WEATHER_CARD, PICTURE_CARD], false);
});

test('only the cards the site has, once each', function () {
    $this->actingAs($this->member);

    $this->put(route('account.widgets'), ['widgets' => ['weather', 'stocks']])->assertSessionHasErrors('widgets.1', errorBag: 'widgets');
    $this->put(route('account.widgets'), ['widgets' => ['weather', 'weather']])->assertSessionHasErrors('widgets.0', errorBag: 'widgets');

    expect($this->member->fresh()->home_widgets)->toBeNull();
});

test('cards the site no longer has are left out', function () {
    $this->member->update(['home_widgets' => ['stocks', 'picture']]);

    expect($this->member->homeWidgets())->toBe([HomeWidget::PictureOfTheDay]);
});

test('the account page lists the chosen cards first, ticked, then the rest', function () {
    $this->member->update(['home_widgets' => ['games']]);

    $this->actingAs($this->member)->get(route('account.edit'))
        ->assertSee('Home page cards')
        ->assertSeeInOrder(['value="games" checked', 'value="weather"', 'value="picture"'], false)
        ->assertDontSee('value="weather" checked', false)
        ->assertSee('Back to the usual');

    $this->member->update(['home_widgets' => null]);
    $this->get(route('account.edit'))
        ->assertSeeInOrder(['value="weather" checked', 'value="picture" checked', 'value="games"'], false)
        ->assertDontSee('Back to the usual');
});

test('choosing cards needs an account', function () {
    $this->put(route('account.widgets'), ['widgets' => ['games']])->assertRedirect(route('login'));
});
