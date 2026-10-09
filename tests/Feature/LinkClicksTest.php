<?php

use App\Cms\Settings;
use App\Enums\ProfileSite;
use App\Models\Link;
use App\Models\Post;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->link = Link::query()->create(['label' => 'Budget app', 'url' => 'https://budget.example', 'emoji' => '💸', 'position' => 0]);
    app(Settings::class)->update(['profiles' => ['github' => 'https://github.com/me']]);
});

function clicksOn(string $target): int
{
    return (int) DB::table('link_clicks')->where('target', $target)->sum('clicks');
}

function clickOn(string $target)
{
    return test()->post(route('clicks'), ['target' => $target]);
}

test('the site marks its links and profiles to count their clicks', function () {
    $this->get(route('home'))
        ->assertSee('data-click="link:'.$this->link->id.'"', false)
        ->assertSee('data-click="profile:github"', false);
});

test('a click counts once per visitor per link per day', function () {
    clickOn("link:{$this->link->id}")->assertNoContent();
    clickOn("link:{$this->link->id}");
    clickOn('profile:github');

    expect(clicksOn("link:{$this->link->id}"))->toBe(1)
        ->and(clicksOn('profile:github'))->toBe(1);

    // A different visitor (a fresh session) counts again.
    $this->flushSession();
    clickOn("link:{$this->link->id}");

    expect(clicksOn("link:{$this->link->id}"))->toBe(2);

    // The same visitor counts again on another day.
    Date::setTestNow(now()->addDay());
    clickOn("link:{$this->link->id}");

    expect(clicksOn("link:{$this->link->id}"))->toBe(3);
});

test('clicks on links the site does not show are not counted', function () {
    $draft = Post::factory()->for(Template::factory())->create();
    $toDraft = Link::query()->create(['post_id' => $draft->id, 'position' => 1]);

    foreach (['link:9999', "link:{$toDraft->id}", 'profile:x', 'profile:nope', 'anything'] as $target) {
        clickOn($target)->assertNoContent();
    }
    $this->post(route('clicks'))->assertNoContent();

    expect(DB::table('link_clicks')->count())->toBe(0);
});

test('clicks by logged in users and bots are not counted', function () {
    $this->withHeader('User-Agent', 'Googlebot/2.1')->post(route('clicks'), ['target' => 'profile:github']);
    $this->actingAs(User::factory()->create());
    clickOn('profile:github');

    expect(DB::table('link_clicks')->count())->toBe(0);
});

test('the dashboard lists every link and profile, most clicked first', function () {
    $other = Link::query()->create(['label' => 'Blog', 'url' => 'https://blog.example', 'position' => 1]);
    DB::table('link_clicks')->insert([
        ['date' => now()->toDateString(), 'target' => 'profile:github', 'clicks' => 5],
        ['date' => now()->subDays(29)->toDateString(), 'target' => "link:{$this->link->id}", 'clicks' => 2],
        // Too long ago, and from a link that was removed.
        ['date' => now()->subDays(30)->toDateString(), 'target' => "link:{$this->link->id}", 'clicks' => 50],
        ['date' => now()->toDateString(), 'target' => 'link:9999', 'clicks' => 8],
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('cp.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('clicks.total', 7)
            ->has('clicks.links', 3)
            ->where('clicks.links.0', ['kind' => 'profile', 'label' => 'GitHub', 'emoji' => null, 'icon' => ProfileSite::GitHub->icon(), 'href' => 'https://github.com/me', 'target' => 'profile:github', 'clicks' => 5])
            ->where('clicks.links.1.label', 'Budget app')
            ->where('clicks.links.1.emoji', '💸')
            ->where('clicks.links.1.clicks', 2)
            // Never clicked, still listed.
            ->where('clicks.links.2.target', "link:{$other->id}")
            ->where('clicks.links.2.clicks', 0));
});
