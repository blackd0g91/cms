<?php

use App\Cms\SiteSearches;
use App\Models\Post;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->template = Template::factory()->create(['handle' => 'recipes', 'fields' => []]);
});

function timesSearched(string $query): int
{
    return (int) DB::table('searches')->where('query', $query)->sum('times');
}

function searchFor(string $query, array $params = [])
{
    return test()->get(route('search', ['q' => $query, ...$params]));
}

test('a search counts once per visitor per day, however it is typed', function () {
    searchFor('Pão de Queijo')->assertOk();
    searchFor('  pão   de queijo ');

    expect(timesSearched('pão de queijo'))->toBe(1);

    // A different visitor (a fresh session) counts again.
    $this->flushSession();
    searchFor('pão de queijo');

    expect(timesSearched('pão de queijo'))->toBe(2);

    // The same visitor counts again on another day.
    Date::setTestNow(now()->addDay());
    searchFor('pão de queijo');

    expect(timesSearched('pão de queijo'))->toBe(3)
        ->and(DB::table('searches')->count())->toBe(2);
});

test('nothing about the visitor is stored', function () {
    searchFor('soup');

    expect(array_keys((array) DB::table('searches')->first()))->toBe(['date', 'query', 'times']);
});

test('logged in users, bots, empty searches and later pages are not counted', function () {
    $this->withHeader('User-Agent', 'Googlebot/2.1')->get(route('search', ['q' => 'bot']));
    $this->withHeader('User-Agent', 'Mozilla/5.0');

    searchFor('');
    searchFor('   ');
    searchFor('soup', ['page' => 2]);

    $this->actingAs(User::factory()->create());
    searchFor('mine');

    expect(DB::table('searches')->count())->toBe(0);
});

test('long searches are cut short', function () {
    searchFor(str_repeat('a', 150));

    expect(DB::table('searches')->value('query'))->toBe(str_repeat('a', 100));
});

test('the dashboard shows the most searched, apart from searches that find nothing', function () {
    Post::factory()->published()->for($this->template)->create(['title' => 'Tomato soup']);
    Post::factory()->published()->for($this->template)->create(['title' => 'Onion soup']);
    // Drafts are not found by visitors.
    Post::factory()->for($this->template)->create(['title' => 'Bread']);

    DB::table('searches')->insert([
        ['date' => now()->toDateString(), 'query' => 'soup', 'times' => 3],
        ['date' => now()->subDays(5)->toDateString(), 'query' => 'soup', 'times' => 2],
        ['date' => now()->toDateString(), 'query' => 'tomato', 'times' => 1],
        ['date' => now()->toDateString(), 'query' => 'bread', 'times' => 4],
        // Too long ago.
        ['date' => now()->subDays(30)->toDateString(), 'query' => 'pasta', 'times' => 9],
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('cp.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('searches.total', 10)
            ->where('searches.found', [
                ['query' => 'soup', 'times' => 5, 'results' => 2],
                ['query' => 'tomato', 'times' => 1, 'results' => 1],
            ])
            ->where('searches.nothing', [
                ['query' => 'bread', 'times' => 4],
            ]));
});

test('a search that found nothing moves over once a post answers it', function () {
    DB::table('searches')->insert(['date' => now()->toDateString(), 'query' => 'bread', 'times' => 2]);

    expect(app(SiteSearches::class)->overview()['nothing'])->toHaveCount(1);

    Post::factory()->published()->for($this->template)->create(['title' => 'Bread']);

    expect(app(SiteSearches::class)->overview())
        ->nothing->toBe([])
        ->found->toBe([['query' => 'bread', 'times' => 2, 'results' => 1]]);
});
