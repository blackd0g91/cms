<?php

use App\Models\Note;
use App\Models\User;
use Illuminate\Support\Facades\Date;

beforeEach(function () {
    Date::setTestNow('2026-10-14 15:00:00');
    $this->member = User::factory()->member()->create(['timezone' => 'America/Sao_Paulo']);
});

function note(User $user, array $attributes = []): Note
{
    return $user->notes()->create(['body' => 'Buy milk', ...$attributes]);
}

test('notes need a confirmed account', function () {
    $this->get(route('notes.index'))->assertRedirect(route('login'));
    $this->post(route('notes.store'), ['body' => 'Hi'])->assertRedirect(route('login'));

    $this->actingAs(User::factory()->member()->unverified()->create())
        ->get(route('notes.index'))
        ->assertRedirect(route('verification.notice'));
});

test('a note is written in markdown, with an optional title', function () {
    $this->actingAs($this->member)
        ->from(route('notes.index'))
        ->post(route('notes.store'), ['title' => '  Trip   ideas ', 'body' => "- **Lisbon**\n- Porto\n\n"])
        ->assertRedirect(route('notes.index'))
        ->assertSessionHas('status', 'Note saved.');

    $note = $this->member->notes()->sole();
    expect($note->title)->toBe('Trip ideas')
        ->and($note->body)->toBe("- **Lisbon**\n- Porto");

    $this->get(route('notes.index'))
        ->assertOk()
        ->assertSee('Trip ideas')
        ->assertSee('<strong>Lisbon</strong>', false);

    $this->post(route('notes.store'), ['body' => 'No title needed']);
    expect($this->member->notes()->count())->toBe(2);
});

test('markdown in notes can not add html', function () {
    note($this->member, ['body' => '<script>alert(1)</script> [x](javascript:alert(1))']);

    $this->actingAs($this->member)->get(route('notes.index'))
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('href="javascript:', false);
});

test('a note needs text, and can not be too long', function () {
    $this->actingAs($this->member);

    $this->post(route('notes.store'), ['body' => ''])->assertSessionHasErrors('body', errorBag: 'note');
    $this->post(route('notes.store'), ['body' => str_repeat('a', Note::MAX_LENGTH + 1)])->assertSessionHasErrors('body', errorBag: 'note');
    $this->post(route('notes.store'), ['title' => str_repeat('a', 121), 'body' => 'x'])->assertSessionHasErrors('title', errorBag: 'note');

    expect(Note::query()->count())->toBe(0);
});

test('a member can only keep so many notes', function () {
    Note::query()->insert(array_fill(0, Note::MAX, ['user_id' => $this->member->id, 'body' => 'x', 'created_at' => now(), 'updated_at' => now()]));

    $this->actingAs($this->member)
        ->post(route('notes.store'), ['body' => 'One more'])
        ->assertSessionHasErrors('body', errorBag: 'note');
});

test('notes can be changed, pinned to the top and deleted', function () {
    $older = note($this->member, ['body' => 'Older']);
    Date::setTestNow(now()->addMinute());
    $newer = note($this->member, ['body' => 'Newer']);
    $this->actingAs($this->member);

    $this->get(route('notes.index'))->assertSeeInOrder(['Newer', 'Older']);

    $this->post(route('notes.pin', $older))->assertSessionHas('status', 'Note pinned.');
    $this->get(route('notes.index'))->assertSeeInOrder(['Pinned', 'Older', 'Newer']);
    // Pinning does not count as a change.
    expect($older->fresh()->updated_at->toDateTimeString())->toBe('2026-10-14 15:00:00');

    $this->post(route('notes.pin', $older))->assertSessionHas('status', 'Note unpinned.');
    expect($older->fresh()->pinned_at)->toBeNull();

    $this->put(route('notes.update', $newer), ['title' => 'Now titled', 'body' => 'Changed'])->assertSessionHas('status', 'Note saved.');
    expect($newer->fresh()->only(['title', 'body']))->toBe(['title' => 'Now titled', 'body' => 'Changed']);

    $this->delete(route('notes.destroy', $newer))->assertSessionHas('status', 'Note deleted.');
    expect(Note::query()->pluck('id')->all())->toBe([$older->id]);
});

test('nobody can see or touch someone else\'s notes', function () {
    $theirs = note(User::factory()->member()->create(), ['body' => 'Their secret']);
    $this->actingAs($this->member);

    $this->get(route('notes.index'))->assertDontSee('Their secret');
    $this->put(route('notes.update', $theirs), ['body' => 'Mine now'])->assertNotFound();
    $this->post(route('notes.pin', $theirs))->assertNotFound();
    $this->delete(route('notes.destroy', $theirs))->assertNotFound();

    expect($theirs->fresh()->body)->toBe('Their secret');
});

test('notes can be searched by title and text', function () {
    note($this->member, ['title' => 'Groceries', 'body' => 'Eggs and bread']);
    note($this->member, ['title' => 'Books', 'body' => 'Dune, then Foundation']);
    $this->actingAs($this->member);

    $this->get(route('notes.index', ['q' => 'foundation']))->assertSee('Books')->assertDontSee('Groceries');
    $this->get(route('notes.index', ['q' => 'grocer']))->assertSee('Groceries')->assertDontSee('Dune');
    $this->get(route('notes.index', ['q' => '%']))->assertSee('Nothing matches');
});

test('the member\'s page has a quick note form and the latest notes, pinned first', function () {
    foreach (range(1, 6) as $i) {
        Date::setTestNow(now()->addMinute());
        note($this->member, ['body' => "Note number {$i}"]);
    }
    $pinned = note($this->member, ['title' => 'Pinned one', 'body' => 'Keep **this** close', 'pinned_at' => now()->subDay()]);

    $this->actingAs($this->member)->get(route('me'))
        ->assertSee('Jot something down')
        ->assertSeeInOrder(['Pinned one', 'Keep this close', 'Note number 6', 'Note number 5'])
        ->assertDontSee('Note number 2')
        ->assertSee('href="'.route('notes.index').'#note-'.$pinned->id.'"', false)
        ->assertSee('All notes (7)');
});

test('a note error on the member\'s page stays with the notes', function () {
    $this->actingAs($this->member)
        ->from(route('me'))
        ->post(route('notes.store'), ['body' => ''])
        ->assertRedirect(route('me'));

    $this->get(route('me'))
        ->assertSee('Write something first.')
        // The reminder dialog stays closed.
        ->assertDontSee('data-dialog-open-now', false);
});

test('deleting the account deletes its notes', function () {
    note($this->member);

    $this->member->delete();

    expect(Note::query()->count())->toBe(0);
});

test('the short preview of a note keeps its lines apart', function () {
    $note = note($this->member, ['body' => "- Eggs\n- **Bread**\n\nAnd *coffee*."]);

    expect($note->preview())->toBe('Eggs · Bread · And coffee.')
        ->and($note->preview(10))->toBe('Eggs · Bre...');
});
