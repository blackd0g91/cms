<?php

use App\Models\QuickLink;
use App\Models\User;

beforeEach(function () {
    $this->member = User::factory()->member()->create();
});

function quickLink(User $user, array $attributes = []): QuickLink
{
    return $user->quickLinks()->create(['label' => 'Mail', 'url' => 'https://mail.example', 'position' => 0, ...$attributes]);
}

test('quick links need a confirmed account', function () {
    $this->post(route('quick-links.store'), ['label' => 'Mail', 'url' => 'https://mail.example'])->assertRedirect(route('login'));

    $this->actingAs(User::factory()->member()->unverified()->create())
        ->post(route('quick-links.store'), ['label' => 'Mail', 'url' => 'https://mail.example'])
        ->assertRedirect(route('verification.notice'));
});

test('a link is added at the end, and the dialog opens again', function () {
    quickLink($this->member, ['position' => 3]);

    $this->actingAs($this->member)
        ->from(route('me'))
        ->post(route('quick-links.store'), ['label' => '  My   code ', 'url' => 'github.com/me', 'emoji' => ' 🐙 '])
        ->assertRedirect(route('me'))
        ->assertSessionHas('status', 'Link added.')
        ->assertSessionHas('dialog', 'quick-links');

    $link = $this->member->quickLinks()->latest('id')->first();
    expect($link->only(['label', 'url', 'emoji', 'position']))->toBe(['label' => 'My code', 'url' => 'https://github.com/me', 'emoji' => '🐙', 'position' => 4]);

    $this->get(route('me'))
        ->assertSee('data-dialog-open-now', false)
        ->assertSeeInOrder(['Quick links', 'href="https://mail.example"', 'href="https://github.com/me"', '🐙', 'My code'], false)
        ->assertSee('target="_blank"', false);
});

test('links need a name and a web address', function () {
    $this->actingAs($this->member)->from(route('me'));

    $this->post(route('quick-links.store'), ['label' => '', 'url' => 'javascript:alert(1)'])
        ->assertSessionHasErrors(['label', 'url'], errorBag: 'quickLink');
    $this->post(route('quick-links.store'), ['label' => 'Inside', 'url' => '/notes'])
        ->assertSessionHasErrors('url', errorBag: 'quickLink');

    expect(QuickLink::query()->count())->toBe(0);
});

test('the dialog opens again with the error', function () {
    $this->actingAs($this->member)
        ->from(route('me'))
        ->post(route('quick-links.store'), ['label' => 'Inside', 'url' => '/notes']);

    $this->get(route('me'))->assertSee('data-dialog-open-now', false)->assertSee('Use a web address');
});

test('a member can only keep so many links', function () {
    foreach (range(1, QuickLink::MAX) as $i) {
        quickLink($this->member, ['position' => $i]);
    }

    $this->actingAs($this->member)
        ->post(route('quick-links.store'), ['label' => 'One more', 'url' => 'https://more.example'])
        ->assertSessionHasErrors('url', errorBag: 'quickLink');
});

test('links can be changed, moved and deleted', function () {
    $first = quickLink($this->member, ['label' => 'First', 'position' => 0]);
    $second = quickLink($this->member, ['label' => 'Second', 'position' => 1]);
    $third = quickLink($this->member, ['label' => 'Third', 'position' => 2]);
    $this->actingAs($this->member);

    $this->put(route('quick-links.update', $second), ['label' => 'Second, renamed', 'url' => 'https://second.example', 'emoji' => ''])
        ->assertSessionHas('status', 'Link saved.');
    expect($second->fresh()->only(['label', 'url', 'emoji']))->toBe(['label' => 'Second, renamed', 'url' => 'https://second.example', 'emoji' => null]);

    $this->post(route('quick-links.move', $third), ['direction' => 'up'])->assertSessionHas('dialog', 'quick-links');
    $this->post(route('quick-links.move', $first), ['direction' => 'up']);
    expect($this->member->quickLinks()->orderBy('position')->pluck('label')->all())->toBe(['First', 'Third', 'Second, renamed']);

    $this->post(route('quick-links.move', $first), ['direction' => 'down']);
    expect($this->member->quickLinks()->orderBy('position')->pluck('label')->all())->toBe(['Third', 'First', 'Second, renamed']);

    $this->post(route('quick-links.move', $first), ['direction' => 'sideways'])->assertSessionHasErrors('direction');

    $this->delete(route('quick-links.destroy', $third))->assertSessionHas('status', 'Link deleted.');
    expect($this->member->quickLinks()->count())->toBe(2);
});

test('nobody can see or touch someone else\'s links', function () {
    $theirs = quickLink(User::factory()->member()->create(), ['label' => 'Their bank', 'url' => 'https://their-bank.example']);
    $this->actingAs($this->member);

    $this->get(route('me'))->assertDontSee('Their bank')->assertDontSee('their-bank.example');
    $this->put(route('quick-links.update', $theirs), ['label' => 'Mine', 'url' => 'https://x.example'])->assertNotFound();
    $this->post(route('quick-links.move', $theirs), ['direction' => 'up'])->assertNotFound();
    $this->delete(route('quick-links.destroy', $theirs))->assertNotFound();

    expect($theirs->fresh()->label)->toBe('Their bank');
});

test('without links the page offers to add one, and the first letter stands in for an emoji', function () {
    $this->actingAs($this->member)->get(route('me'))->assertSee('Add a link')->assertDontSee('data-dialog-open-now', false);

    quickLink($this->member, ['label' => 'calendar']);

    $this->get(route('me'))->assertSee('aria-hidden="true">C</span>', false);
});

test('deleting the account deletes its links', function () {
    quickLink($this->member);

    $this->member->delete();

    expect(QuickLink::query()->count())->toBe(0);
});
