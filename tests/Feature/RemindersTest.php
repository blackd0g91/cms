<?php

use App\Enums\ReminderRepeat;
use App\Models\Reminder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

beforeEach(function () {
    // A Monday, 15:00 UTC: 12:00 in São Paulo.
    Date::setTestNow('2026-10-12 15:00:00');
    $this->member = User::factory()->member()->create(['timezone' => 'America/Sao_Paulo']);
});

function remind(User $user, string $text, string $due, ?ReminderRepeat $repeat = null): Reminder
{
    return $user->reminders()->create([
        'text' => $text,
        'due_at' => CarbonImmutable::parse($due, $user->localTimezone())->utc(),
        'repeat' => $repeat,
    ]);
}

test('reminders need a confirmed account', function () {
    $this->get(route('reminders.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->member()->unverified()->create())
        ->get(route('reminders.index'))
        ->assertRedirect(route('verification.notice'));
});

test('a reminder is added in the member\'s time zone, which the browser sends', function () {
    $member = User::factory()->member()->create(['timezone' => null]);

    $this->actingAs($member)
        ->post(route('reminders.store'), ['text' => '  Call the dentist ', 'due' => '2026-10-13T09:00', 'repeat' => '', 'timezone' => 'America/Sao_Paulo'])
        ->assertRedirect()
        ->assertSessionHas('status', 'Reminder added.');

    $reminder = $member->reminders()->sole();

    expect($reminder->text)->toBe('Call the dentist')
        ->and($reminder->due_at->toDateTimeString())->toBe('2026-10-13 12:00:00')
        ->and($reminder->repeat)->toBeNull()
        ->and($member->fresh()->timezone)->toBe('America/Sao_Paulo');
});

test('a reminder needs text and a time from now on', function () {
    $this->actingAs($this->member);

    $this->post(route('reminders.store'), ['text' => '', 'due' => '2026-10-13T09:00'])->assertSessionHasErrors('text');
    $this->post(route('reminders.store'), ['text' => 'Late', 'due' => 'tomorrow'])->assertSessionHasErrors('due');
    $this->post(route('reminders.store'), ['text' => 'Late', 'due' => '2026-10-12T11:00'])->assertSessionHasErrors(['due' => 'Pick a time from now on.']);
    $this->post(route('reminders.store'), ['text' => 'Odd', 'due' => '2026-10-13T09:00', 'timezone' => 'Mars/Olympus'])->assertSessionHasErrors('timezone');

    expect(Reminder::query()->count())->toBe(0);
});

test('a member can only have so many reminders waiting', function () {
    Reminder::factory()->count(Reminder::MAX_ACTIVE)->for($this->member)->create();

    $this->actingAs($this->member)
        ->post(route('reminders.store'), ['text' => 'One more', 'due' => '2026-10-13T09:00'])
        ->assertSessionHasErrors('text');
});

test('the reminders page groups them by when they are due', function () {
    remind($this->member, 'Missed it', '2026-10-12 08:00');
    remind($this->member, 'This afternoon', '2026-10-12 16:30');
    remind($this->member, 'Tomorrow morning', '2026-10-13 09:00', ReminderRepeat::Weekly);
    remind($this->member, 'On Thursday', '2026-10-15 10:00');
    remind($this->member, 'Next month', '2026-11-20 10:00');
    remind($this->member, 'Done already', '2026-10-12 17:00')->update(['completed_at' => now()]);
    remind(User::factory()->member()->create(), 'Someone else\'s', '2026-10-12 16:00');

    $this->actingAs($this->member)
        ->get(route('reminders.index'))
        ->assertOk()
        ->assertSeeInOrder(['Overdue', 'Missed it', 'Oct 12, 08:00', 'Today', 'This afternoon', '16:30', 'Tomorrow', 'Tomorrow morning', '09:00', 'every week', 'This week', 'On Thursday', 'Thu 10:00', 'Later', 'Next month', 'Nov 20, 10:00'])
        ->assertDontSee('Done already')
        ->assertDontSee('Someone else');
});

test('done hides a one-off reminder, and moves a repeating one to its next time', function () {
    $once = remind($this->member, 'Once', '2026-10-12 08:00');
    $daily = remind($this->member, 'Pills', '2026-10-09 08:00', ReminderRepeat::Daily);
    $monthly = remind($this->member, 'Rent', '2026-08-31 10:00', ReminderRepeat::Monthly);
    $this->actingAs($this->member);

    $this->post(route('reminders.complete', $once))->assertSessionHas('status', 'Done.');
    $this->post(route('reminders.complete', $daily))->assertSessionHas('status', 'Done. It comes back 08:00.');
    $this->post(route('reminders.complete', $monthly));

    expect($once->fresh()->completed_at)->not->toBeNull()
        // The next 8:00 in São Paulo that is still to come: tomorrow's.
        ->and($daily->fresh()->due_at->setTimezone('America/Sao_Paulo')->toDateTimeString())->toBe('2026-10-13 08:00:00')
        ->and($daily->fresh()->completed_at)->toBeNull()
        // The 31st falls back to the month's last day.
        ->and($monthly->fresh()->due_at->setTimezone('America/Sao_Paulo')->toDateTimeString())->toBe('2026-10-30 10:00:00');
});

test('a reminder can be snoozed for an hour, or until tomorrow morning', function () {
    $reminder = remind($this->member, 'Later', '2026-10-12 11:00');
    $this->actingAs($this->member);

    $this->post(route('reminders.snooze', $reminder), ['until' => 'hour'])->assertSessionHas('status', 'Snoozed until 13:00.');
    expect($reminder->fresh()->due_at->toDateTimeString())->toBe('2026-10-12 16:00:00');

    $this->post(route('reminders.snooze', $reminder), ['until' => 'tomorrow']);
    expect($reminder->fresh()->due_at->setTimezone('America/Sao_Paulo')->toDateTimeString())->toBe('2026-10-13 09:00:00');

    $this->post(route('reminders.snooze', $reminder), ['until' => 'next year'])->assertSessionHasErrors('until');
});

test('a reminder can be changed and deleted', function () {
    $reminder = remind($this->member, 'Typo', '2026-10-13 09:00');
    $this->actingAs($this->member);

    $this->put(route('reminders.update', $reminder), ['text' => 'Fixed', 'due' => '2026-10-14T10:30', 'repeat' => 'yearly'])->assertRedirect();

    expect($reminder->fresh())
        ->text->toBe('Fixed')
        ->repeat->toBe(ReminderRepeat::Yearly)
        ->and($reminder->fresh()->due_at->toDateTimeString())->toBe('2026-10-14 13:30:00');

    $this->delete(route('reminders.destroy', $reminder))->assertRedirect();
    expect($reminder->fresh())->toBeNull();
});

test('nobody can touch someone else\'s reminders', function () {
    $reminder = remind(User::factory()->member()->create(), 'Mine', '2026-10-13 09:00');
    $this->actingAs($this->member);

    $this->put(route('reminders.update', $reminder), ['text' => 'Yours now', 'due' => '2026-10-14T10:30'])->assertNotFound();
    $this->post(route('reminders.complete', $reminder))->assertNotFound();
    $this->post(route('reminders.snooze', $reminder), ['until' => 'hour'])->assertNotFound();
    $this->delete(route('reminders.destroy', $reminder))->assertNotFound();

    expect($reminder->fresh()->text)->toBe('Mine');
});

test('a member\'s page shows what is due soon, with a form to add one', function () {
    $today = remind($this->member, 'Due today', '2026-10-12 18:00');
    remind($this->member, 'Due on Saturday', '2026-10-17 18:00');
    remind($this->member, 'Due next week', '2026-10-20 18:00');

    $this->actingAs($this->member)
        ->get(route('me'))
        ->assertOk()
        ->assertSee('Due today')
        ->assertSee('Due on Saturday')
        ->assertDontSee('Due next week')
        // The title leads to the reminders page, and + opens the form in a dialog.
        ->assertSee('<a href="'.route('reminders.index').'" class="decoration-2', false)
        ->assertSee('data-dialog-open="new-reminder"', false)
        ->assertSee('id="new-reminder"', false)
        ->assertDontSee('data-dialog-open-now', false)
        ->assertSee('action="'.route('reminders.store').'"', false)
        // Each can be snoozed, changed and deleted from there too.
        ->assertSee('action="'.route('reminders.snooze', $today).'"', false)
        // The forms are complete: Blade directives all compiled.
        ->assertSee('name="_token"', false)
        ->assertDontSee('@csrf', false);

    $this->get(route('reminders.index'))->assertSee('name="_token"', false)->assertDontSee('@csrf', false);
});

test('the home page is the same for everyone, without reminders', function () {
    remind($this->member, 'Due today', '2026-10-12 18:00');

    // Guests sign up from the account icon in the header instead.
    $this->get(route('home'))
        ->assertDontSee('Create an account')
        ->assertSee('title="Log in"', false);

    $this->actingAs($this->member)->get(route('home'))
        ->assertDontSee('Due today')
        ->assertDontSee('data-reminder=', false);
});

test('a member\'s page needs a confirmed address, and greets them in their time', function () {
    $this->get(route('me'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->member()->unverified()->create())->get(route('me'))->assertRedirect(route('verification.notice'));

    // 15:00 in UTC is noon in São Paulo.
    $this->member->update(['name' => 'Ana Maria Souza']);
    $this->actingAs($this->member)->get(route('me'))
        ->assertSee('Monday, October 12')
        ->assertSee('Good afternoon, Ana');
});

test('the account menu leads to the member\'s page', function () {
    $this->actingAs($this->member)->get(route('home'))->assertSee('href="'.route('me').'"', false);
});

test('deleting the account deletes its reminders', function () {
    remind($this->member, 'Mine', '2026-10-13 09:00');

    $this->actingAs($this->member)->delete(route('account.destroy'), ['password' => 'password']);

    expect(Reminder::query()->count())->toBe(0);
});

test('signing up and logging in keep the browser\'s time zone', function () {
    $this->post(route('register.store'), [
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'password' => 'a-long-password',
        'password_confirmation' => 'a-long-password',
        'timezone' => 'Europe/Lisbon',
    ]);

    expect(User::query()->where('email', 'ana@example.com')->value('timezone'))->toBe('Europe/Lisbon');

    auth()->logout();
    $this->post(route('login.store'), ['email' => $this->member->email, 'password' => 'password', 'timezone' => 'Asia/Tokyo']);
    expect($this->member->fresh()->timezone)->toBe('Asia/Tokyo');

    auth()->logout();
    $this->post(route('login.store'), ['email' => $this->member->email, 'password' => 'password', 'timezone' => 'Nowhere/Land']);
    expect($this->member->fresh()->timezone)->toBe('Asia/Tokyo');
});

test('the lists tell the page when each reminder comes due', function () {
    $reminder = remind($this->member, 'Call the dentist', '2026-10-12 12:30');

    $this->actingAs($this->member)
        ->get(route('me'))
        ->assertSee('data-reminder="'.$reminder->id.'"', false)
        ->assertSee('data-reminder-due="2026-10-12T15:30:00+00:00"', false)
        ->assertSee('data-reminder-done="'.route('reminders.complete', $reminder).'"', false)
        ->assertSee('data-csrf-token="', false);

    auth()->logout();
    $this->get(route('home'))->assertDontSee('data-csrf-token', false);
});

test('the panel and the reminders page offer system notifications', function () {
    $this->actingAs($this->member);

    $this->get(route('me'))->assertSee('data-reminder-notify', false)->assertSee('Notify me in this browser');
    $this->get(route('reminders.index'))->assertSee('data-reminder-notify', false);
});

test('the form on a member\'s page opens again with what was typed when it has an error', function () {
    $this->actingAs($this->member)
        ->from(route('me'))
        ->post(route('reminders.store'), ['text' => 'Too late', 'due' => '2026-10-12T08:00', 'repeat' => 'weekly', 'email' => '1'])
        ->assertRedirect(route('me'));

    $this->get(route('me'))
        ->assertSee('data-dialog-open-now', false)
        ->assertSee('Pick a time from now on.')
        ->assertSee('value="Too late"', false)
        ->assertSee('value="2026-10-12T08:00"', false)
        ->assertSee('<option value="weekly" selected>', false);
});

test('the sidebar has tiles for the home page and the member\'s page, when logged in', function () {
    $this->get(route('home'))->assertDontSee('title="Your page"', false);

    $this->actingAs($this->member)->get(route('me'))
        ->assertSeeInOrder(['title="Home page"', 'href="'.route('me').'"', 'title="Your page"', 'aria-current="page"'], false);

    $this->get(route('notes.index'))->assertSee('title="Your page"', false);
});

test('a reminder is changed in its own dialog, from a button beside snooze and delete', function () {
    $reminder = remind($this->member, 'Call the dentist', '2026-10-13 10:00');
    $this->actingAs($this->member);

    $this->get(route('reminders.index'))
        ->assertSeeInOrder(['data-dialog-open="edit-reminder-'.$reminder->id.'"', 'title="Snooze"', 'title="Delete"'], false)
        ->assertSee('id="edit-reminder-'.$reminder->id.'"', false)
        ->assertSee('name="editing" value="reminder-'.$reminder->id.'"', false)
        ->assertDontSee('<details class="mt-1.5">', false);

    // Sent back with an error: that dialog opens again with what was typed,
    // and the form for a new one stays empty.
    $this->from(route('reminders.index'))
        ->put(route('reminders.update', $reminder), ['editing' => 'reminder-'.$reminder->id, 'text' => 'Call the vet', 'due' => '2020-01-01T10:00']);

    $page = $this->get(route('reminders.index'));
    $page->assertSee('value="Call the vet"', false)->assertSee('Pick a time from now on.');
    expect(substr_count($page->getContent(), 'value="Call the vet"'))->toBe(1)
        ->and(substr_count($page->getContent(), 'data-dialog-open-now'))->toBe(1);
});
