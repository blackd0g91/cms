<?php

use App\Cms\FreeGames;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

// A giveaway as GamerPower lists it, trimmed to the fields used.
function giveaway(array $overrides = []): array
{
    return [
        'title' => 'Pony Island (Steam) Giveaway',
        'worth' => '$4.99',
        'thumbnail' => 'https://www.gamerpower.com/offers/1/pony.jpg',
        'description' => 'A puzzle game about a haunted arcade cabinet.',
        'open_giveaway_url' => 'https://www.gamerpower.com/open/pony-island-steam-giveaway',
        'type' => 'Game',
        'platforms' => 'PC, Steam',
        'end_date' => '2026-10-15 23:59:00',
        'users' => 12000,
        'status' => 'Active',
        ...$overrides,
    ];
}

beforeEach(function () {
    Date::setTestNow('2026-10-11 15:00:00');
});

test('the home page shows the free games, opening into a dialog', function () {
    Http::fake(['www.gamerpower.com/*' => Http::response([
        giveaway(),
        giveaway(['title' => 'The Big Con (Mobile) Giveaway', 'platforms' => 'Android, iOS', 'worth' => 'N/A', 'end_date' => 'N/A']),
        giveaway(['title' => 'Dwarven Realms (Steam) Key Giveaway', 'end_date' => '2026-10-12 12:00:00']),
    ])]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-dialog-open="free-games"', false)
        ->assertSee('3 games free to keep')
        // Those ending soonest first.
        ->assertSee('Dwarven Realms, Pony Island and more')
        // The game, the store giving it away, and the kind of device.
        ->assertSee('Steam · PC')
        ->assertSee('Mobile')
        ->assertSee('$4.99')
        ->assertSee('Ends in 4 days')
        ->assertSee('Ends tomorrow')
        ->assertSee('No end date')
        ->assertSee('href="https://www.gamerpower.com/open/pony-island-steam-giveaway"', false)
        // Filters only for the devices there are games for.
        ->assertSee('data-filter="PC"', false)
        ->assertSee('data-filter="Mobile"', false)
        ->assertDontSee('data-filter="Xbox"', false)
        ->assertSee('data-filter-tags="Mobile"', false);

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://www.gamerpower.com/api/giveaways?type=game'));
});

test('giveaways are read into games', function () {
    Http::fake(['www.gamerpower.com/*' => Http::response([
        giveaway(['title' => 'Dwarven Realms (Steam) Key Giveaway']),
        giveaway(['title' => 'Castlevania Giveaway', 'platforms' => 'PS4, PS5, Xbox Series X|S, Nintendo Switch']),
        giveaway(['title' => 'Old one', 'status' => 'Expired']),
        ['title' => 'Broken'],
    ])]);

    $games = app(FreeGames::class)->current();

    expect($games)->toHaveCount(2)
        ->and($games[0])->toMatchArray([
            'title' => 'Dwarven Realms',
            'store' => 'Steam',
            'platforms' => ['PC', 'Steam'],
            'devices' => ['PC'],
            'worth' => '$4.99',
            'ends_at' => '2026-10-15T23:59:00+00:00',
        ])
        ->and($games[1])->toMatchArray([
            'title' => 'Castlevania',
            'store' => null,
            'devices' => ['PlayStation', 'Xbox', 'Switch'],
        ]);
});

test('they are fetched once an hour, leaving out games that ended since', function () {
    Http::fake(['www.gamerpower.com/*' => Http::response([
        giveaway(['end_date' => '2026-10-11 15:30:00']),
        giveaway(['title' => 'Later (Steam) Giveaway']),
    ])]);

    expect(app(FreeGames::class)->current())->toHaveCount(2);

    $this->travel(40)->minutes();

    expect(app(FreeGames::class)->current())->toHaveCount(1);
    Http::assertSentCount(1);

    $this->travel(30)->minutes();
    app(FreeGames::class)->current();

    Http::assertSentCount(2);
});

test('the page still works when GamerPower does not, and asks again later', function () {
    Http::fake(['www.gamerpower.com/*' => Http::response('Down', 500)]);

    $this->get(route('home'))->assertOk()->assertDontSee('data-dialog-open="free-games"', false);
    $this->get(route('home'));

    Http::assertSentCount(1);

    $this->travel(16)->minutes();
    $this->get(route('home'));

    Http::assertSentCount(2);
});

test('no card without giveaways', function () {
    Http::fake(['www.gamerpower.com/*' => Http::response(['status' => 0, 'status_message' => 'No active giveaways available at the moment.'], 201)]);

    $this->get(route('home'))->assertOk()->assertDontSee('data-dialog-open="free-games"', false);
});

test('when a game stops being free, for people', function () {
    $game = fn (?string $endsAt) => ['ends_at' => $endsAt];

    expect(FreeGames::ending($game(null)))->toBe('No end date')
        ->and(FreeGames::ending($game('2026-10-11T23:00:00+00:00')))->toBe('Ends today')
        ->and(FreeGames::ending($game('2026-10-12T10:00:00+00:00')))->toBe('Ends tomorrow')
        ->and(FreeGames::ending($game('2026-10-20T10:00:00+00:00')))->toBe('Ends in 9 days')
        ->and(FreeGames::endingSoon($game('2026-10-13T10:00:00+00:00')))->toBeTrue()
        ->and(FreeGames::endingSoon($game('2026-10-20T10:00:00+00:00')))->toBeFalse()
        ->and(FreeGames::endingSoon($game(null)))->toBeFalse();
});

test('games ending soonest come first, then the rest as GamerPower ranks them', function () {
    Http::fake(['www.gamerpower.com/*' => Http::response([
        giveaway(['title' => 'Popular (Steam) Giveaway', 'end_date' => 'N/A']),
        giveaway(['title' => 'Next week (Steam) Giveaway', 'end_date' => '2026-10-18 23:59:00']),
        giveaway(['title' => 'Less popular (Steam) Giveaway', 'end_date' => 'N/A']),
        giveaway(['title' => 'Tomorrow (Epic Games) Giveaway', 'end_date' => '2026-10-12 15:00:00']),
    ])]);

    expect(array_column(app(FreeGames::class)->current(), 'title'))
        ->toBe(['Tomorrow', 'Next week', 'Popular', 'Less popular']);
});
