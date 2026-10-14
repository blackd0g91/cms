<?php

use App\Models\Media;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function saveProjects(array $projects)
{
    return test()->put(route('cp.projects.update'), ['projects' => $projects]);
}

function project(array $attributes = []): Project
{
    return Project::query()->create(['name' => 'Silver', 'url' => 'https://silver.example', 'position' => 0, ...$attributes]);
}

test('the projects page lists them in order, with their images', function () {
    $logo = Media::factory()->create();
    project(['name' => 'Second', 'position' => 1]);
    project(['name' => 'First', 'position' => 0, 'logo_id' => $logo->id]);

    $this->actingAs(User::factory()->create())
        ->get(route('cp.projects.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('cp/Projects')
            ->where('projects.0.name', 'First')
            ->where('projects.0.logo.url', $logo->url)
            ->where('projects.0.background', null)
            ->where('projects.1.name', 'Second'));
});

test('only admins can see or change the projects', function () {
    $this->get(route('cp.projects.edit'))->assertRedirect(route('cp.login'));
    saveProjects([])->assertRedirect(route('cp.login'));

    $this->actingAs(User::factory()->editor()->create());
    $this->get(route('cp.projects.edit'))->assertForbidden();
    saveProjects([])->assertForbidden();
});

test('the list is saved as sent: projects are updated, added, reordered and removed', function () {
    $this->actingAs(User::factory()->create());
    $logo = Media::factory()->create();
    $background = Media::factory()->create();
    $kept = project(['name' => 'Old name', 'position' => 0]);
    project(['name' => 'Gone', 'position' => 1]);

    saveProjects([
        ['id' => null, 'name' => ' Groceries ', 'url' => 'groceries.example.com', 'description' => '  A shared   shopping list. ', 'logo_id' => $logo->id, 'background_id' => $background->id],
        ['id' => $kept->id, 'name' => 'Silver', 'url' => 'https://silver.example/app', 'description' => '', 'logo_id' => null, 'background_id' => null],
    ])->assertSessionHasNoErrors();

    $projects = Project::query()->orderBy('position')->get();

    expect($projects)->toHaveCount(2)
        ->and($projects[0]->only(['name', 'url', 'description', 'logo_id', 'background_id', 'position']))
        ->toBe(['name' => 'Groceries', 'url' => 'https://groceries.example.com', 'description' => 'A shared shopping list.', 'logo_id' => $logo->id, 'background_id' => $background->id, 'position' => 0])
        ->and($projects[1]->id)->toBe($kept->id)
        ->and($projects[1]->only(['name', 'url', 'description', 'position']))
        ->toBe(['name' => 'Silver', 'url' => 'https://silver.example/app', 'description' => null, 'position' => 1]);
});

test('projects need a name and a web address, a short description and images from the library', function () {
    $this->actingAs(User::factory()->create());
    $trashed = Media::factory()->create();
    $trashed->delete();

    saveProjects([
        ['name' => '', 'url' => 'javascript:alert(1)'],
        ['name' => 'Mail', 'url' => 'mailto:me@example.com'],
        ['name' => 'Inside', 'url' => '/recipes'],
        ['name' => 'Long', 'url' => 'https://a.example', 'description' => str_repeat('a', 161)],
        ['name' => 'Images', 'url' => 'https://b.example', 'logo_id' => $trashed->id, 'background_id' => 999],
    ])->assertSessionHasErrors([
        'projects.0.name', 'projects.0.url', 'projects.1.url', 'projects.2.url',
        'projects.3.description', 'projects.4.logo_id', 'projects.4.background_id',
    ]);

    expect(Project::query()->count())->toBe(0);
});

test('the home page shows the projects as cards, in order, before the posts', function () {
    $logo = Media::factory()->create(['path' => 'media/silver-logo.png']);
    $background = Media::factory()->create(['path' => 'media/silver-screen.png']);
    project(['name' => 'Notes', 'url' => 'https://notes.example', 'position' => 1]);
    project(['description' => 'Where my money goes.', 'logo_id' => $logo->id, 'background_id' => $background->id]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['Projects', 'href="https://silver.example"', $logo->url, 'Silver', 'Where my money goes.', 'href="https://notes.example"', 'Notes'], false)
        ->assertSee('src="'.$background->url.'"', false)
        ->assertSee('data-click="project:', false);
});

test('without images a card still works, and without projects there is no section', function () {
    $this->get(route('home'))->assertDontSee('projects-title', false);

    project(['name' => 'groceries']);

    $this->get(route('home'))
        ->assertSee('projects-title', false)
        // The first letter stands in for the logo, and no background is shown.
        ->assertSee('aria-hidden="true">G</span>', false)
        ->assertDontSee('project-card-background', false);
});

test('images in the trash are left off the cards', function () {
    $logo = Media::factory()->create(['path' => 'media/trashed-logo.png']);
    project(['logo_id' => $logo->id]);
    $logo->delete();

    $this->get(route('home'))->assertOk()->assertDontSee('trashed-logo.png', false);
});

test('the media library knows where project images are used', function () {
    $logo = Media::factory()->create();
    project(['logo_id' => $logo->id, 'background_id' => $logo->id]);

    $this->actingAs(User::factory()->create())
        ->delete(route('cp.media.destroy', $logo))
        ->assertSessionHasErrors(['media' => 'This image is still used in: Silver (project logo), Silver (project background).']);
});

test('clicks on the project cards are counted', function () {
    $project = project();

    $this->post(route('clicks'), ['target' => "project:{$project->id}"])->assertNoContent();

    expect((int) DB::table('link_clicks')->where('target', "project:{$project->id}")->sum('clicks'))->toBe(1);
});
