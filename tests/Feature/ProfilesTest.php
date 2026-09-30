<?php

use App\Cms\Settings;
use App\Enums\ProfileSite;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function saveProfiles(array $profiles)
{
    return test()->put(route('cp.settings.update'), ['site_name' => 'Garmr', 'profiles' => $profiles]);
}

test('the settings page lists every site, with what is filled in', function () {
    app(Settings::class)->update(['profiles' => ['github' => 'https://github.com/me']]);

    $this->actingAs(User::factory()->create())
        ->get(route('cp.settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('profileSites', count(ProfileSite::cases()))
            ->where('profileSites.0.key', 'github')
            ->where('profileSites.0.label', 'GitHub')
            ->where('settings.profiles', ['github' => 'https://github.com/me']));
});

test('usernames and short addresses are saved as full addresses', function (string $site, string $typed, string $saved) {
    $this->actingAs(User::factory()->create());

    saveProfiles([$site => $typed])->assertSessionHasNoErrors();

    expect(app(Settings::class)->get('profiles'))->toBe([$site => $saved]);
})->with([
    ['github', 'murilo', 'https://github.com/murilo'],
    ['github', 'github.com/murilo', 'https://github.com/murilo'],
    ['linkedin', 'murilo-motta', 'https://www.linkedin.com/in/murilo-motta'],
    ['x', '@murilo', 'https://x.com/murilo'],
    ['x', 'https://twitter.com/murilo', 'https://twitter.com/murilo'],
    ['bluesky', 'me.bsky.social', 'https://bsky.app/profile/me.bsky.social'],
    ['mastodon', '@me@mastodon.social', 'https://mastodon.social/@me'],
    ['mastodon', 'https://fosstodon.org/@me', 'https://fosstodon.org/@me'],
    ['instagram', 'murilo.motta', 'https://www.instagram.com/murilo.motta'],
    ['youtube', '@murilo', 'https://www.youtube.com/@murilo'],
    ['whatsapp', '+55 11 91234-5678', 'https://wa.me/5511912345678'],
    ['email', 'mailto:me@example.com', 'me@example.com'],
]);

test('profiles on other sites, bare domains and unsafe addresses are refused', function () {
    $this->actingAs(User::factory()->create());
    app(Settings::class)->update(['profiles' => ['github' => 'https://github.com/kept']]);

    saveProfiles([
        'github' => 'https://gitlab.com/me',
        'x' => 'https://x.com',
        'instagram' => 'javascript:alert(1)',
        'mastodon' => 'me',
        'email' => 'not an email',
    ])->assertSessionHasErrors(['profiles.github', 'profiles.x', 'profiles.instagram', 'profiles.mastodon', 'profiles.email']);

    expect(app(Settings::class)->get('profiles'))->toBe(['github' => 'https://github.com/kept']);
});

test('clearing a field removes the profile, and saving other settings keeps them', function () {
    $this->actingAs(User::factory()->create());
    saveProfiles(['github' => 'me', 'x' => 'me'])->assertSessionHasNoErrors();

    $this->put(route('cp.settings.update'), ['site_name' => 'Renamed'])->assertSessionHasNoErrors();
    expect(app(Settings::class)->get('profiles'))->toHaveKeys(['github', 'x']);

    saveProfiles(['github' => '', 'x' => 'me'])->assertSessionHasNoErrors();
    expect(app(Settings::class)->get('profiles'))->toBe(['x' => 'https://x.com/me']);
});

test('the header shows an icon for each profile filled in, in a fixed order', function () {
    app(Settings::class)->update(['profiles' => [
        'email' => 'me@example.com',
        'github' => 'https://github.com/me',
    ]]);

    $response = $this->get('/')->assertOk();

    $response->assertSeeInOrder(['href="https://github.com/me"', 'href="mailto:me@example.com"'], false)
        ->assertSee('rel="me"', false)
        ->assertSee('<span class="sr-only">GitHub</span>', false)
        ->assertDontSee('<span class="sr-only">LinkedIn</span>', false);

    // Once beside the search box, and once in the menu for phones.
    expect(substr_count($response->getContent(), 'href="https://github.com/me"'))->toBe(2);
});

test('without profiles the header has no icons for them', function () {
    $this->get('/')->assertOk()->assertDontSee('aria-label="Profiles"', false);
});
