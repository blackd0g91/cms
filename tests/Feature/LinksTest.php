<?php

use App\Cms\Settings;
use App\Models\Link;
use App\Models\Post;
use App\Models\Template;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->template = Template::factory()->create(['name' => 'Recipes', 'handle' => 'recipes', 'fields' => [], 'layout' => '']);
    $this->post = Post::factory()->published()->for($this->template)->create(['title' => 'About me', 'slug' => 'about']);
});

function saveLinks(array $links, ?string $heading = 'Links')
{
    return test()->put(route('cp.links.update'), ['heading' => $heading, 'links' => $links]);
}

test('the links page lists the links in order, with every post to pick from', function () {
    Link::query()->create(['label' => 'Second', 'url' => 'https://b.example', 'position' => 1]);
    Link::query()->create(['label' => 'First', 'url' => 'https://a.example', 'position' => 0]);
    Post::factory()->for($this->template)->create(['title' => 'A draft']);

    $this->actingAs(User::factory()->create())
        ->get(route('cp.links.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('cp/Links')
            ->where('heading', 'Links')
            ->where('links.0.label', 'First')
            ->where('links.1.label', 'Second')
            ->where('templates.0.name', 'Recipes')
            ->has('templates.0.posts', 2)
            ->where('templates.0.posts.0.title', 'A draft')
            ->where('templates.0.posts.0.status', 'draft'));
});

test('guests can not see or change the links', function () {
    $this->get(route('cp.links.edit'))->assertRedirect(route('cp.login'));
    saveLinks([])->assertRedirect(route('cp.login'));
});

test('the list is saved as sent: links are updated, added, reordered and removed', function () {
    $this->actingAs(User::factory()->create());
    $kept = Link::query()->create(['label' => 'GitHub', 'url' => 'https://github.com/old', 'position' => 0]);
    Link::query()->create(['label' => 'Gone', 'url' => 'https://gone.example', 'position' => 1]);

    saveLinks([
        ['id' => null, 'label' => '', 'url' => null, 'post_id' => $this->post->id, 'emoji' => '👋'],
        ['id' => $kept->id, 'label' => ' GitHub  profile ', 'url' => 'https://github.com/me', 'post_id' => null, 'emoji' => ''],
    ], heading: 'Elsewhere')->assertSessionHasNoErrors();

    $links = Link::query()->orderBy('position')->get();

    expect($links)->toHaveCount(2)
        ->and($links[0]->only(['label', 'url', 'post_id', 'emoji', 'position']))
        ->toBe(['label' => null, 'url' => null, 'post_id' => $this->post->id, 'emoji' => '👋', 'position' => 0])
        ->and($links[1]->id)->toBe($kept->id)
        ->and($links[1]->only(['label', 'url', 'emoji', 'position']))
        ->toBe(['label' => 'GitHub profile', 'url' => 'https://github.com/me', 'emoji' => null, 'position' => 1])
        ->and(app(Settings::class)->get('links_heading'))->toBe('Elsewhere');
});

test('the heading can be left empty', function () {
    $this->actingAs(User::factory()->create());

    saveLinks([], heading: '')->assertSessionHasNoErrors();

    expect(app(Settings::class)->get('links_heading'))->toBe('');
});

test('addresses typed the short way are completed', function (string $typed, string $saved) {
    $this->actingAs(User::factory()->create());

    saveLinks([['label' => 'Somewhere', 'url' => $typed]])->assertSessionHasNoErrors();

    expect(Link::query()->sole()->url)->toBe($saved);
})->with([
    ['github.com/me', 'https://github.com/me'],
    ['www.example.com', 'https://www.example.com'],
    ['me@example.com', 'mailto:me@example.com'],
    ['https://example.com/a?b=c', 'https://example.com/a?b=c'],
    ['/tags', '/tags'],
    ['tel:+55 11 91234-5678', 'tel:+55 11 91234-5678'],
]);

test('links need a safe address and a label, or a post', function () {
    $this->actingAs(User::factory()->create());

    saveLinks([
        ['label' => 'Bad', 'url' => 'javascript:alert(1)'],
        ['label' => '', 'url' => 'https://example.com'],
        ['label' => 'Nothing', 'url' => '', 'post_id' => null],
        ['label' => '', 'post_id' => 999],
    ])->assertSessionHasErrors(['links.0.url', 'links.1.label', 'links.2.url', 'links.3.post_id']);

    expect(Link::query()->count())->toBe(0);
});

test('the sidebar shows the links in order under their heading', function () {
    Link::query()->create(['label' => 'GitHub', 'url' => 'https://github.com/me', 'emoji' => '🐙', 'position' => 1]);
    Link::query()->create(['post_id' => $this->post->id, 'position' => 0]);
    Link::query()->create(['label' => 'All tags', 'url' => '/tags', 'position' => 2]);

    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(['Links', 'About me', '🐙', 'GitHub', '↗', 'All tags'])
        ->assertSee('href="'.url('/recipes/about').'"', false)
        ->assertSee('href="https://github.com/me"', false)
        ->assertSee('href="/tags"', false);
});

test('links to posts follow their title and slug, and wait while they are drafts', function () {
    Link::query()->create(['post_id' => $this->post->id, 'position' => 0]);

    $this->post->update(['title' => 'Who I am', 'slug' => 'who']);
    $this->get('/')->assertSee('Who I am')->assertSee('href="'.url('/recipes/who').'"', false);

    $this->post->update(['status' => 'draft']);
    $this->get('/')->assertDontSee('Who I am');
});

test('the link to the page being viewed is marked as current', function () {
    Link::query()->create(['post_id' => $this->post->id, 'position' => 0]);

    $this->get('/recipes/about')->assertSee('aria-current="page"', false);
    $this->get('/')->assertDontSee('aria-current="page"', false);
});

test('links go away with their post', function () {
    Link::query()->create(['post_id' => $this->post->id, 'position' => 0]);

    $this->post->delete();

    expect(Link::query()->count())->toBe(0);
});

test('without links there is no links section, and an empty heading shows none', function () {
    // The classes of the section and of its heading, only used there.
    $section = 'mt-auto pt-6';
    $heading = 'mb-1 px-3 font-mono';

    $this->get('/')->assertDontSee($section, false);

    Link::query()->create(['label' => 'GitHub', 'url' => 'https://github.com/me', 'position' => 0]);
    $this->get('/')->assertSee($section, false)->assertSee($heading, false);

    app(Settings::class)->update(['links_heading' => '']);
    $this->get('/')->assertSee('GitHub')->assertDontSee($heading, false);
});
