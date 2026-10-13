<?php

use App\Mail\TestEmail;
use Illuminate\Support\Facades\Mail;

test('a test email can be sent to check the mail settings', function () {
    Mail::fake();

    $this->artisan('app:test-email', ['to' => 'me@example.com'])->assertSuccessful();

    Mail::assertSent(TestEmail::class, fn (TestEmail $mail) => $mail->hasTo('me@example.com'));
});

test('the test email says what sent it', function () {
    config(['mail.default' => 'smtp', 'app.url' => 'https://example.com']);

    $mail = new TestEmail;

    $mail->assertSeeInHtml('the site can send email')
        ->assertSeeInHtml('through the smtp mailer, from https://example.com')
        ->assertSeeInText('the site can send email');
});

test('an address that is not one is refused', function () {
    Mail::fake();

    $this->artisan('app:test-email', ['to' => 'nope'])
        ->expectsOutputToContain('is not an email address')
        ->assertFailed();

    Mail::assertNothingSent();
});

test('a failure to send says what to check', function () {
    Mail::shouldReceive('to->send')->andThrow(new RuntimeException('Authentication failed'));

    $this->artisan('app:test-email', ['to' => 'me@example.com'])
        ->expectsOutputToContain('Authentication failed')
        ->expectsOutputToContain('config:cache')
        ->assertFailed();
});
