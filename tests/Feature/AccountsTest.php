<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

function signUp(array $fields = [])
{
    return test()->post(route('register.store'), [
        'name' => 'Ana Souza',
        'email' => 'ana@example.com',
        'password' => 'a-long-password',
        'password_confirmation' => 'a-long-password',
        ...$fields,
    ]);
}

test('the account pages show for guests', function () {
    foreach (['register', 'login', 'password.request'] as $route) {
        $this->get(route($route))->assertOk()->assertSee('<meta name="robots" content="noindex">', false);
    }

    $this->get(route('password.reset', ['token' => 'abc', 'email' => 'ana@example.com']))
        ->assertOk()
        ->assertSee('value="ana@example.com"', false);
});

test('signing up makes a member, logs them in and asks them to confirm their address', function () {
    Notification::fake();

    signUp(['name' => '  Ana   Souza ', 'email' => ' Ana@Example.com '])->assertRedirect(route('verification.notice'));

    $user = User::query()->sole();

    expect($user->name)->toBe('Ana Souza')
        ->and($user->email)->toBe('ana@example.com')
        ->and($user->role)->toBe(UserRole::Member)
        ->and($user->hasVerifiedEmail())->toBeFalse();
    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);

    $this->get(route('verification.notice'))->assertOk()->assertSee('ana@example.com');
});

test('signing up can not ask for a staff role', function () {
    signUp(['role' => 'admin']);

    expect(User::query()->sole()->role)->toBe(UserRole::Member);
});

test('an address can only have one account', function () {
    User::factory()->member()->create(['email' => 'ana@example.com']);

    signUp(['email' => 'ANA@example.com'])->assertSessionHasErrors('email');

    expect(User::query()->count())->toBe(1);
});

test('bots that fill in the hidden field are turned away', function () {
    signUp(['website' => 'https://spam.example'])->assertSessionHasErrors('website');

    expect(User::query()->count())->toBe(0);
    $this->assertGuest();
});

test('the link in the email confirms the address', function () {
    $user = User::factory()->member()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

    $this->actingAs($user)->get($url)->assertRedirect(route('home'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->get(route('home'))->assertSee('Your email address is confirmed');
    $this->get(route('verification.notice'))->assertRedirect(route('home'));
});

test('the confirmation email can be sent again', function () {
    Notification::fake();
    $user = User::factory()->member()->unverified()->create();

    $this->actingAs($user)->post(route('verification.send'))->assertRedirect();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('members and staff log in on the site, and log out again', function () {
    $member = User::factory()->member()->create(['email' => 'ana@example.com']);

    $this->post(route('login.store'), ['email' => 'ana@example.com', 'password' => 'password'])
        ->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($member);

    $this->post(route('logout'))->assertRedirect(route('home'));
    $this->assertGuest();

    $editor = User::factory()->editor()->create();
    $this->post(route('login.store'), ['email' => $editor->email, 'password' => 'password']);
    $this->assertAuthenticatedAs($editor);
});

test('a wrong password does not log in', function () {
    User::factory()->member()->create(['email' => 'ana@example.com']);

    $this->post(route('login.store'), ['email' => 'ana@example.com', 'password' => 'wrong'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);
    $this->assertGuest();
});

test('pages that need an account send guests to the site login, and the control panel to its own', function () {
    $this->get(route('verification.notice'))->assertRedirect(route('login'));
    $this->get(route('cp.dashboard'))->assertRedirect(route('cp.login'));
});

test('logged in, the login pages lead on', function () {
    $this->actingAs(User::factory()->member()->create());

    $this->get(route('login'))->assertRedirect(route('home'));
    $this->get(route('register'))->assertRedirect(route('home'));
});

test('a forgotten password gets a link, without telling which addresses have an account', function () {
    Notification::fake();
    $user = User::factory()->member()->create(['email' => 'ana@example.com']);

    $this->post(route('password.email'), ['email' => 'ana@example.com'])->assertSessionHas('status');
    $this->post(route('password.email'), ['email' => 'nobody@example.com'])->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertSentTimes(ResetPassword::class, 1);
});

test('the link sets a new password and logs in, confirming the address', function () {
    $user = User::factory()->member()->unverified()->create(['email' => 'ana@example.com']);
    $token = Password::createToken($user);

    $this->post(route('password.store'), [
        'token' => $token,
        'email' => 'ana@example.com',
        'password' => 'a-new-long-password',
        'password_confirmation' => 'a-new-long-password',
    ])->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
    expect(Hash::check('a-new-long-password', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('a used or made up link does not set a password', function () {
    $user = User::factory()->member()->create(['email' => 'ana@example.com']);

    $this->post(route('password.store'), [
        'token' => 'made-up',
        'email' => 'ana@example.com',
        'password' => 'a-new-long-password',
        'password_confirmation' => 'a-new-long-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('the account emails are in the site\'s look, with their link', function () {
    $user = User::factory()->member()->create(['email' => 'ana@example.com']);

    $reset = (new ResetPassword('the-token'))->toMail($user)->render();
    $verify = (new VerifyEmail)->toMail($user)->render();

    expect((string) $reset)->toContain('Choose a new password')
        ->toContain(e(route('password.reset', ['token' => 'the-token', 'email' => 'ana@example.com'])))
        ->and((string) $verify)->toContain('Confirm my address')
        ->toContain('verify-email/'.$user->id);
});

test('the header has a log in link for guests, and a menu once logged in', function () {
    $this->get(route('home'))->assertSee('href="'.route('login').'"', false);

    $this->actingAs(User::factory()->member()->unverified()->create(['name' => 'Ana Souza']))
        ->get(route('home'))
        ->assertSee('>AS</span>', false)
        ->assertSee('Confirm your email')
        ->assertSee('action="'.route('logout').'"', false)
        ->assertDontSee('Control panel');

    $this->actingAs(User::factory()->editor()->create())
        ->get(route('home'))
        ->assertSee('Control panel');
});
