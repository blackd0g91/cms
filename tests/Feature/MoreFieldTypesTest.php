<?php

use App\Cms\LayoutRenderer;
use App\Cms\LinkedPosts;
use App\Models\Media;
use App\Models\Post;
use App\Models\Template;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->template = Template::factory()->create([
        'name' => 'Apps',
        'handle' => 'apps',
        'fields' => [
            ['handle' => 'website', 'label' => 'Website', 'type' => 'url', 'required' => false, 'options' => []],
            ['handle' => 'screenshots', 'label' => 'Screenshots', 'type' => 'gallery', 'required' => false, 'options' => []],
            ['handle' => 'related', 'label' => 'Related', 'type' => 'posts', 'required' => false, 'options' => []],
        ],
        'layout' => '{{ screenshots }}',
    ]);
});

function storeApp(array $data): TestResponse
{
    return test()->post(route('cp.templates.posts.store', test()->template), ['title' => 'Timer', 'status' => 'published', 'data' => $data]);
}

test('web address fields only take web addresses', function (string $address, bool $valid) {
    $response = storeApp(['website' => $address]);

    $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('data.website');
})->with([
    'https' => ['https://timer.example.com/start?x=1', true],
    'http' => ['http://example.com', true],
    'not an address' => ['timer dot com', false],
    'script' => ['javascript:alert(1)', false],
    'other kinds' => ['ftp://example.com', false],
]);

test('web address fields can be used as links in layouts, and anything else is left out', function () {
    $this->template->update(['layout' => '<a href="{{ website }}">Open</a>{{^ website }}none{{/ website }}']);
    Post::factory()->published()->for($this->template)->create(['slug' => 'good', 'data' => ['website' => 'https://example.com/?a=1&b=2']]);
    // Saved some other way, like an older version of a field.
    Post::factory()->published()->for($this->template)->create(['slug' => 'bad', 'data' => ['website' => 'javascript:alert(1)']]);

    $this->get('/apps/good')->assertSee('<a href="https://example.com/?a=1&amp;b=2">Open</a>', false);
    $this->get('/apps/bad')->assertDontSee('javascript:', false)->assertSee('none');
});

test('gallery fields keep their images in order, and only existing ones', function () {
    [$first, $second] = Media::factory()->count(2)->create();

    storeApp(['screenshots' => [999]])->assertSessionHasErrors('data.screenshots.0');
    storeApp(['screenshots' => [$first->id, $first->id]])->assertSessionHasErrors('data.screenshots.0');
    storeApp(['screenshots' => [(string) $second->id, $first->id]])->assertSessionHasNoErrors();

    expect(Post::sole()->data['screenshots'])->toBe([$second->id, $first->id]);
});

test('gallery fields show their images in a row, or one at a time', function () {
    $first = Media::factory()->create(['alt' => 'Start screen']);
    $second = Media::factory()->create(['alt' => 'Running', 'width' => 400, 'height' => 800]);
    $trashed = Media::factory()->create(['alt' => 'Old']);
    $trashed->delete();

    $this->template->update(['layout' => '{{ screenshots }}|{{# screenshots }}<i>{{ alt }}</i>{{/ screenshots }}']);
    Post::factory()->published()->for($this->template)->create([
        'slug' => 'timer',
        'data' => ['screenshots' => [$second->id, $trashed->id, $first->id]],
    ]);

    $this->get('/apps/timer')
        ->assertOk()
        ->assertSee('<div class="gallery"><img src="'.$second->url.'" alt="Running"', false)
        ->assertSeeInOrder([$second->url, $first->url])
        ->assertSee('|<i>Running</i><i>Start screen</i>', false)
        ->assertDontSee('Old');
});

test('a gallery without images can be left out with an inverted section', function () {
    $this->template->update(['layout' => '{{# screenshots }}never{{/ screenshots }}{{^ screenshots }}No screenshots yet{{/ screenshots }}']);
    Post::factory()->published()->for($this->template)->create(['slug' => 'timer', 'data' => ['screenshots' => []]]);

    $this->get('/apps/timer')->assertSee('No screenshots yet')->assertDontSee('never');
});

test('gallery images count as media usage, open in the editor, and show as missing once deleted', function () {
    $kept = Media::factory()->create(['alt' => 'Kept']);
    $deleted = Media::factory()->create(['alt' => 'Deleted']);
    $post = Post::factory()->published()->for($this->template)->create([
        'title' => 'Timer',
        'thumbnail_id' => $kept->id,
        'data' => ['screenshots' => [$kept->id, $deleted->id]],
    ]);

    $this->get(route('cp.media.index'))
        ->assertInertia(fn (Assert $page) => $page->where("usages.{$deleted->id}.0.post_id", $post->id));

    $this->get(route('cp.templates.posts.edit', [$this->template, $post]))
        ->assertInertia(fn (Assert $page) => $page->has("media.{$kept->id}")->has("media.{$deleted->id}"));

    $deleted->forceDelete();

    $this->get(route('cp.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('checkup.3.key', 'broken-images')
            ->where('checkup.3.items.0.title', 'Timer')
            ->where('checkup.3.items.0.detail', '1 missing'));
});

test('posts fields keep the posts in order, and only ones that exist', function () {
    [$first, $second] = Post::factory()->count(2)->published()->create();
    $trashed = Post::factory()->published()->create();
    $trashed->delete();

    storeApp(['related' => [999]])->assertSessionHasErrors('data.related.0');
    storeApp(['related' => [$trashed->id]])->assertSessionHasErrors('data.related.0');
    storeApp(['related' => [$second->id, $first->id]])->assertSessionHasNoErrors();

    expect(Post::query()->where('title', 'Timer')->sole()->data['related'])->toBe([$second->id, $first->id]);
});

test('posts fields show the published posts as cards, or one at a time', function () {
    $notes = Template::factory()->create(['name' => 'Notes', 'handle' => 'notes']);
    $first = Post::factory()->published()->for($notes)->create(['title' => 'Release <notes>', 'slug' => 'release']);
    $draft = Post::factory()->for($notes)->create(['title' => 'Unfinished']);
    $second = Post::factory()->published()->for($notes)->create(['title' => 'Roadmap', 'slug' => 'roadmap', 'summary' => 'What comes next.']);

    $this->template->update(['layout' => '{{ related }}|{{# related }}<b>{{ title }} in {{ template.name }}: {{ summary }}</b>{{ . }}{{/ related }}']);
    Post::factory()->published()->for($this->template)->create([
        'slug' => 'timer',
        'data' => ['related' => [$second->id, $draft->id, $first->id]],
    ]);

    $this->post(route('cp.logout'));

    $this->get('/apps/timer')
        ->assertOk()
        ->assertSee('data-card-light', false)
        ->assertSee('What comes next.')
        ->assertSeeInOrder(['Roadmap', 'Release &lt;notes&gt;'], false)
        ->assertSee('<b>Roadmap in Notes: What comes next.</b><a href="'.url('/notes/roadmap').'">Roadmap</a>', false)
        ->assertSee('<b>Release &lt;notes&gt; in Notes: </b><a href="'.url('/notes/release').'">Release &lt;notes&gt;</a>', false)
        ->assertDontSee('Unfinished');
});

test('cards inside a post stay out of its text styles and table of contents', function () {
    $post = Post::factory()->published()->create(['title' => 'Roadmap']);

    $html = LinkedPosts::fromIds([$post->id])->toHtml();

    expect($html)->toContain('not-prose')->toContain('Roadmap')->not->toContain('<h2');
});

test('posts fields with no published posts can be left out with an inverted section', function () {
    $draft = Post::factory()->create();
    $this->template->update(['layout' => '{{# related }}never{{/ related }}{{^ related }}Nothing related{{/ related }}']);
    Post::factory()->published()->for($this->template)->create(['slug' => 'timer', 'data' => ['related' => [$draft->id]]]);

    $this->get('/apps/timer')->assertSee('Nothing related')->assertDontSee('never');
});

test('the editor gets every post to pick from, only for templates with a posts field', function () {
    $notes = Template::factory()->create(['name' => 'Notes', 'fields' => []]);
    $note = Post::factory()->for($notes)->create(['title' => 'Roadmap']);

    $this->get(route('cp.templates.posts.create', $this->template))
        ->assertInertia(fn (Assert $page) => $page
            ->where('postChoices.1.name', 'Notes')
            ->where('postChoices.1.posts.0.id', $note->id)
            ->where('postChoices.1.posts.0.status', 'draft'));

    $this->get(route('cp.templates.posts.create', $notes))
        ->assertInertia(fn (Assert $page) => $page->where('postChoices', []));
});

test('generated layouts show every new field type', function () {
    $layout = LayoutRenderer::defaultLayout($this->template->fields);

    expect($layout)
        ->toContain('{{# website }}<p><a href="{{ website }}">Website</a></p>{{/ website }}')
        ->toContain('{{ screenshots }}')
        ->toContain("<h2>Related</h2>\n  {{ related }}");
});
