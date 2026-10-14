<?php

use App\Models\Label;
use App\Models\Note;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Support\Facades\Date;

beforeEach(function () {
    Date::setTestNow('2026-10-14 15:00:00');
    $this->member = User::factory()->member()->create(['timezone' => 'America/Sao_Paulo']);
});

function label(User $user, string $name = 'Work', string $color = '#2563eb'): Label
{
    return $user->labels()->create(['name' => $name, 'color' => $color]);
}

test('labels are made with a name and a color, and the dialog opens again', function () {
    $this->actingAs($this->member)
        ->from(route('notes.index'))
        ->post(route('labels.store'), ['name' => '  Side   project ', 'color' => '#16A34A'])
        ->assertRedirect(route('notes.index'))
        ->assertSessionHas('status', 'Label added.')
        ->assertSessionHas('dialog', 'labels');

    expect($this->member->labels()->sole()->only(['name', 'color']))->toBe(['name' => 'Side project', 'color' => '#16a34a']);

    $this->get(route('notes.index'))
        ->assertSee('data-dialog-open-now', false)
        ->assertSee('value="#16a34a"', false);
});

test('a label needs a name of its own and a color', function () {
    label($this->member, 'Work');
    $this->actingAs($this->member);

    $this->post(route('labels.store'), ['name' => 'work', 'color' => '#ffffff'])->assertSessionHasErrors('name', errorBag: 'label');
    $this->post(route('labels.store'), ['name' => '', 'color' => 'red'])->assertSessionHasErrors(['name', 'color'], errorBag: 'label');

    // Someone else can have the same name.
    label(User::factory()->member()->create(), 'Home');
    $this->post(route('labels.store'), ['name' => 'Home', 'color' => '#ffffff'])->assertSessionHasNoErrors();

    expect($this->member->labels()->count())->toBe(2);
});

test('a member can only keep so many labels', function () {
    foreach (range(1, Label::MAX) as $i) {
        label($this->member, "Label {$i}");
    }

    $this->actingAs($this->member)
        ->post(route('labels.store'), ['name' => 'One more', 'color' => '#ffffff'])
        ->assertSessionHasErrors('name', errorBag: 'label');
});

test('labels can be renamed, recolored and deleted, which takes them off everything', function () {
    $label = label($this->member);
    $note = $this->member->notes()->create(['body' => 'x']);
    $note->labels()->attach($label);
    $this->actingAs($this->member);

    $this->put(route('labels.update', $label), ['name' => 'Office', 'color' => '#dc2626'])->assertSessionHas('status', 'Label saved.');
    expect($label->fresh()->only(['name', 'color']))->toBe(['name' => 'Office', 'color' => '#dc2626']);

    // Keeping its own name is fine.
    $this->put(route('labels.update', $label), ['name' => 'Office', 'color' => '#000000'])->assertSessionHasNoErrors();

    $this->delete(route('labels.destroy', $label))->assertSessionHas('status', 'Label deleted.');
    expect(Label::query()->count())->toBe(0)
        ->and($note->fresh()->labels)->toHaveCount(0)
        ->and(Note::query()->count())->toBe(1);
});

test('nobody can see or touch someone else\'s labels', function () {
    $theirs = label(User::factory()->member()->create(), 'Their secret');
    $this->actingAs($this->member);

    $this->get(route('notes.index'))->assertDontSee('Their secret');
    $this->put(route('labels.update', $theirs), ['name' => 'Mine', 'color' => '#000000'])->assertNotFound();
    $this->delete(route('labels.destroy', $theirs))->assertNotFound();

    // Nor put them on their own notes.
    $this->post(route('notes.store'), ['body' => 'x', 'with_labels' => '1', 'labels' => [$theirs->id]])
        ->assertSessionHasErrors('labels.0', errorBag: 'note');
    expect(Note::query()->count())->toBe(0);
});

test('notes get their labels from the picker, and keep them when it is not there', function () {
    $work = label($this->member, 'Work');
    $home = label($this->member, 'Home', '#16a34a');
    $this->actingAs($this->member);

    $this->post(route('notes.store'), ['body' => 'Plan', 'with_labels' => '1', 'labels' => [$work->id, $home->id]]);
    $note = Note::query()->sole();
    expect($note->labels->pluck('name')->all())->toBe(['Home', 'Work']);

    $this->put(route('notes.update', $note), ['body' => 'Plan B', 'with_labels' => '1', 'labels' => [$home->id]]);
    expect($note->fresh()->labels->pluck('name')->all())->toBe(['Home']);

    // Every label unticked.
    $this->put(route('notes.update', $note), ['body' => 'Plan C', 'with_labels' => '1']);
    expect($note->fresh()->labels)->toHaveCount(0);

    $note->labels()->attach($work);
    // A form without the picker leaves them be.
    $this->put(route('notes.update', $note), ['body' => 'Plan D']);
    expect($note->fresh()->labels)->toHaveCount(1);
});

test('reminders get their labels from the picker too', function () {
    $work = label($this->member);
    $this->actingAs($this->member);

    $this->post(route('reminders.store'), ['text' => 'Send the report', 'due' => '2026-10-15T09:00', 'with_labels' => '1', 'labels' => [$work->id]])
        ->assertSessionHasNoErrors();
    $reminder = Reminder::query()->sole();
    expect($reminder->labels->pluck('name')->all())->toBe(['Work']);

    $this->put(route('reminders.update', $reminder), ['text' => 'Send the report', 'due' => '2026-10-15T10:00', 'with_labels' => '1']);
    expect($reminder->fresh()->labels)->toHaveCount(0);
});

test('lists show the labels, and can be narrowed to one', function () {
    $work = label($this->member, 'Work', '#2563eb');
    $home = label($this->member, 'Home', '#16a34a');
    $this->actingAs($this->member);

    $this->member->notes()->create(['title' => 'Quarterly plan', 'body' => 'x'])->labels()->attach($work);
    $this->member->notes()->create(['title' => 'Paint the fence', 'body' => 'x'])->labels()->attach($home);
    $this->member->reminders()->create(['text' => 'Standup', 'due_at' => now()->addHour()])->labels()->attach($work);
    $this->member->reminders()->create(['text' => 'Water plants', 'due_at' => now()->addHour()]);

    $this->get(route('notes.index'))
        ->assertSee('Quarterly plan')->assertSee('Paint the fence')
        ->assertSee('style="--label: #2563eb">Work</a>', false)
        ->assertSee('href="'.route('notes.index', ['label' => $work->id]).'"', false);

    $this->get(route('notes.index', ['label' => $work->id]))
        ->assertSee('Quarterly plan')->assertDontSee('Paint the fence')
        ->assertSee('aria-current="page"', false);

    $this->get(route('reminders.index', ['label' => $work->id]))
        ->assertSee('Standup')->assertDontSee('Water plants');

    $this->get(route('notes.index', ['label' => $home->id, 'q' => 'quarterly']))->assertSee('No notes labeled Home');

    // Someone else's label narrows nothing.
    $theirs = label(User::factory()->member()->create(), 'Theirs');
    $this->get(route('notes.index', ['label' => $theirs->id]))->assertSee('Quarterly plan')->assertSee('Paint the fence');

    $this->get(route('me'))->assertSee('Standup')->assertSee('--label: #2563eb', false);
});

test('the forms offer the member\'s labels to pick', function () {
    $this->actingAs($this->member);

    // None yet: no picker, and a way to make some.
    $this->get(route('notes.index'))->assertDontSee('name="with_labels"', false)->assertSee('+ Make labels');

    label($this->member, 'Work');

    $this->get(route('notes.index'))->assertSee('name="with_labels"', false)->assertSee('name="labels[]"', false)->assertSee('Edit labels');
    $this->get(route('reminders.index'))->assertSee('name="labels[]"', false);
    // Not on the quick note form.
    $this->get(route('me'))->assertSee('name="labels[]"', false)->assertDontSee('id="quick-note-label-', false);
});

test('deleting the account deletes its labels', function () {
    label($this->member);

    $this->member->delete();

    expect(Label::query()->count())->toBe(0);
});
