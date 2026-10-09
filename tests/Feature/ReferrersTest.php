<?php

use App\Models\Post;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->template = Template::factory()->create(['handle' => 'recipes', 'fields' => []]);
    $this->post = Post::factory()->published()->for($this->template)->create(['slug' => 'soup']);
});

function visitsFrom(string $source): int
{
    return (int) DB::table('referrers')->where('source', $source)->sum('visits');
}

function arriveFrom(?string $referrer, string $path = '/')
{
    return test()->withHeaders(['Referer' => (string) $referrer])->get($path);
}

test('visits count by the site that linked here, once per visitor per day', function () {
    arriveFrom('https://www.google.com/search?q=soup')->assertOk();
    arriveFrom('https://google.com/', '/recipes/soup');
    arriveFrom('https://news.ycombinator.com/item?id=1');

    expect(visitsFrom('google.com'))->toBe(1)
        ->and(visitsFrom('news.ycombinator.com'))->toBe(1);

    // A different visitor (a fresh session) counts again.
    $this->flushSession();
    arriveFrom('https://m.google.com/');

    expect(visitsFrom('google.com'))->toBe(2);

    // The same visitor counts again on another day.
    Date::setTestNow(now()->addDay());
    arriveFrom('https://www.google.com/');

    expect(visitsFrom('google.com'))->toBe(3);
});

test('a name in the link counts instead of the site it is on', function () {
    arriveFrom('https://budget.example/', '/?ref=Budget%20App');
    $this->flushSession();
    arriveFrom(null, '/recipes/soup?utm_source=newsletter');

    expect(visitsFrom('budget app'))->toBe(1)
        ->and(visitsFrom('newsletter'))->toBe(1)
        ->and(visitsFrom('budget.example'))->toBe(0);
});

test('visits without a referrer count as direct, and moving around the site does not count', function () {
    arriveFrom(null);
    arriveFrom('http://localhost/', '/recipes/soup');
    arriveFrom(url('/recipes'), '/recipes/soup');

    expect(DB::table('referrers')->pluck('visits', 'source')->all())->toBe(['' => 1]);
});

test('only pages of the public site seen by visitors count', function () {
    arriveFrom('https://google.com/', '/feed.xml');
    arriveFrom('https://google.com/', '/no-such-page')->assertNotFound();
    arriveFrom('https://google.com/', '/cp/login');
    $this->withHeaders(['Referer' => 'https://google.com/', 'User-Agent' => 'Googlebot/2.1'])->get('/');
    $this->actingAs(User::factory()->create());
    arriveFrom('https://google.com/');

    expect(DB::table('referrers')->count())->toBe(0);
});

test('the dashboard shows where visitors came from most', function () {
    DB::table('referrers')->insert([
        ['date' => now()->toDateString(), 'source' => 'google.com', 'visits' => 5],
        ['date' => now()->subDays(29)->toDateString(), 'source' => 'google.com', 'visits' => 1],
        ['date' => now()->toDateString(), 'source' => '', 'visits' => 3],
        ['date' => now()->toDateString(), 'source' => 'budget app', 'visits' => 3],
        // Too long ago.
        ['date' => now()->subDays(30)->toDateString(), 'source' => 'github.com', 'visits' => 50],
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('cp.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('referrers.total', 12)
            ->where('referrers.sources', [
                ['source' => 'google.com', 'visits' => 6],
                ['source' => '', 'visits' => 3],
                ['source' => 'budget app', 'visits' => 3],
            ]));
});

test('names in the link are kept to plain labels, which never look like a site', function () {
    arriveFrom(null, '/?ref='.urlencode('evil.example/<b>Hi</b>!'));

    expect(DB::table('referrers')->pluck('source')->all())->toBe(['evil example b hi b']);
});

test('one address can not add up visits by dropping its session', function () {
    foreach (range(1, 8) as $i) {
        $this->flushSession();
        arriveFrom('https://google.com/');
    }

    expect(visitsFrom('google.com'))->toBe(5);

    // A different address still counts.
    $this->flushSession();
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2']);
    arriveFrom('https://google.com/');

    expect(visitsFrom('google.com'))->toBe(6);
});

test('one address can only add a few sources a day', function () {
    foreach (range(1, 15) as $i) {
        $this->flushSession();
        arriveFrom(null, "/?ref=spam{$i}");
    }

    expect(DB::table('referrers')->count())->toBe(10);

    // The next day it can again.
    Date::setTestNow(now()->addDay());
    $this->flushSession();
    arriveFrom(null, '/?ref=spam99');

    expect(visitsFrom('spam99'))->toBe(1);
});
