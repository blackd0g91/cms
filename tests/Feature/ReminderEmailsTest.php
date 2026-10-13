<?php

use App\Mail\ReminderEmail;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Date::setTestNow('2026-10-12 15:00:00');
    Mail::fake();
    $this->member = User::factory()->member()->create(['timezone' => 'America/Sao_Paulo']);
});

function dueReminder(User $user, array $attributes = []): Reminder
{
    return Reminder::factory()->for($user)->create(['due_at' => now()->subMinute(), 'email' => true, ...$attributes]);
}

function emailLink(Reminder $reminder, string $action): string
{
    return URL::temporarySignedRoute('reminders.email', now()->addWeek(), ['reminder' => $reminder->id, 'action' => $action]);
}

test('emailing a reminder is chosen when adding it, and off by default', function () {
    $this->actingAs($this->member);

    $this->post(route('reminders.store'), ['text' => 'Quiet', 'due' => '2026-10-13T09:00']);
    $this->post(route('reminders.store'), ['text' => 'Loud', 'due' => '2026-10-13T09:00', 'email' => '1']);

    expect(Reminder::query()->pluck('email', 'text')->all())->toBe(['Quiet' => false, 'Loud' => true]);

    $this->get(route('reminders.index'))->assertSee("Email me when it's due", false)->assertSee('up to 10 a day');
});

test('due reminders are emailed once, to those who asked', function () {
    $due = dueReminder($this->member, ['text' => 'Call the dentist']);
    dueReminder($this->member, ['email' => false]);
    dueReminder($this->member, ['due_at' => now()->addMinute()]);
    dueReminder($this->member, ['completed_at' => now()]);
    dueReminder(User::factory()->member()->unverified()->create());

    $this->artisan('app:send-reminders')->assertSuccessful();
    $this->artisan('app:send-reminders')->assertSuccessful();

    Mail::assertSentCount(1);
    Mail::assertSent(ReminderEmail::class, fn (ReminderEmail $mail) => $mail->reminder->is($due) && $mail->hasTo($this->member->email));
});

test('a reminder is emailed again for its next time', function () {
    $reminder = dueReminder($this->member);
    $this->artisan('app:send-reminders');

    $reminder->update(['due_at' => now()->addHour()]);
    Date::setTestNow(now()->addHour()->addMinute());
    $this->artisan('app:send-reminders');

    Mail::assertSentCount(2);
});

test('reminders it comes to too late are not emailed', function () {
    $late = dueReminder($this->member, ['due_at' => now()->subHours(7)]);

    $this->artisan('app:send-reminders');

    Mail::assertNothingSent();
    expect($late->fresh()->emailed_at)->not->toBeNull();
});

test('each member, and the site, can only be sent so many a day', function () {
    config(['reminders.emails_per_member' => 2, 'reminders.emails_per_day' => 3]);
    $other = User::factory()->member()->create();

    foreach (range(1, 3) as $i) {
        dueReminder($this->member);
    }
    dueReminder($other);
    dueReminder(User::factory()->member()->create());

    $this->artisan('app:send-reminders');

    // Two for the member, then one more for the site.
    Mail::assertSentCount(3);
    expect(Mail::sent(ReminderEmail::class, fn (ReminderEmail $mail) => $mail->hasTo($this->member->email)))->toHaveCount(2);

    // A new day starts afresh.
    Date::setTestNow(now()->addDay());
    dueReminder($this->member);
    $this->artisan('app:send-reminders');
    Mail::assertSentCount(4);
});

test('the email has the reminder, its time and links that work without logging in', function () {
    $reminder = dueReminder($this->member, ['text' => 'Water the plants', 'due_at' => '2026-10-12 11:00:00', 'repeat' => 'daily']);

    $mail = new ReminderEmail($reminder);

    $mail->assertHasSubject('Reminder: Water the plants')
        ->assertSeeInHtml('Water the plants')
        ->assertSeeInHtml('Due Monday, October 12 at 08:00, every day.')
        ->assertSeeInHtml('Mark as done')
        ->assertSeeInHtml('Snooze for an hour')
        ->assertSeeInHtml('Stop emails for this reminder')
        ->assertSeeInHtml('signature=')
        ->assertSeeInText('Snooze for an hour: ');
});

test('an email link asks to confirm before doing anything', function () {
    $reminder = dueReminder($this->member, ['text' => 'Pay rent']);

    $this->get(emailLink($reminder, 'done'))
        ->assertOk()
        ->assertSee('Pay rent')
        ->assertSee('Mark as done');

    expect($reminder->fresh()->completed_at)->toBeNull();
});

test('the email links mark done, snooze and stop emails', function () {
    $done = dueReminder($this->member);
    $snoozed = dueReminder($this->member);
    $stopped = dueReminder($this->member);

    $this->post(emailLink($done, 'done'))->assertRedirect(route('home'))->assertSessionHas('status', 'Done.');
    $this->post(emailLink($snoozed, 'snooze'))->assertSessionHas('status');
    $this->post(emailLink($stopped, 'stop'))->assertSessionHas('status');

    expect($done->fresh()->completed_at)->not->toBeNull()
        ->and($snoozed->fresh()->due_at->toDateTimeString())->toBe('2026-10-12 16:00:00')
        ->and($stopped->fresh()->email)->toBeFalse();

    $this->post(emailLink($done, 'done'))->assertSessionHas('status', 'That reminder is already done.');
});

test('email links only work as they were sent', function () {
    $reminder = dueReminder($this->member);
    $link = emailLink($reminder, 'done');

    $this->post(str_replace('/done?', '/stop?', $link))->assertForbidden();
    $this->post(route('reminders.email.update', ['reminder' => $reminder->id, 'action' => 'done']))->assertForbidden();

    Date::setTestNow(now()->addWeek()->addMinute());
    $this->post($link)->assertForbidden();

    expect($reminder->fresh()->completed_at)->toBeNull();
});

test('the reminders are sent every minute', function () {
    Artisan::call('schedule:list');

    expect(Artisan::output())->toContain('app:send-reminders')->toContain('* * * * *');
});
