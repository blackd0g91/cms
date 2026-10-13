<?php

use App\Models\Post;
use App\Models\Template;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('the account page needs an account', function () {
    $this->get(route('account.edit'))->assertRedirect(route('login'));
});

test('the account page shows your details', function () {
    $this->actingAs(User::factory()->member()->create(['name' => 'Ana Souza', 'email' => 'ana@example.com']))
        ->get(route('account.edit'))
        ->assertOk()
        ->assertSee('value="Ana Souza"', false)
        ->assertSee('value="ana@example.com"', false)
        ->assertSee('Delete my account');
});

test('the name can be changed', function () {
    $user = User::factory()->member()->create();

    $this->actingAs($user)
        ->put(route('account.update'), ['name' => '  Ana   Souza ', 'email' => $user->email])
        ->assertRedirect(route('account.edit'))
        ->assertSessionHas('status');

    expect($user->fresh()->name)->toBe('Ana Souza')
        ->and($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('a new address has to be confirmed again', function () {
    Notification::fake();
    $user = User::factory()->member()->create();

    $this->actingAs($user)
        ->put(route('account.update'), ['name' => $user->name, 'email' => ' New@Example.com '])
        ->assertRedirect(route('verification.notice'));

    expect($user->fresh()->email)->toBe('new@example.com')
        ->and($user->fresh()->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('an address someone else has can not be taken', function () {
    User::factory()->member()->create(['email' => 'taken@example.com']);
    $user = User::factory()->member()->create();

    $this->actingAs($user)
        ->put(route('account.update'), ['name' => $user->name, 'email' => 'taken@example.com'])
        ->assertSessionHasErrorsIn('profile', 'email');
});

test('the password can be changed with the current one', function () {
    $user = User::factory()->member()->create();
    $this->actingAs($user);

    $this->put(route('account.password'), ['current_password' => 'wrong', 'password' => 'a-new-long-password', 'password_confirmation' => 'a-new-long-password'])
        ->assertSessionHasErrorsIn('password', 'current_password');

    $this->put(route('account.password'), ['current_password' => 'password', 'password' => 'a-new-long-password', 'password_confirmation' => 'a-new-long-password'])
        ->assertRedirect(route('account.edit'));

    expect(Hash::check('a-new-long-password', $user->fresh()->password))->toBeTrue();
});

test('members can delete their account, with their password', function () {
    $user = User::factory()->member()->create();
    $other = User::factory()->member()->create();
    Password::createToken($user);
    DB::table('sessions')->insert([
        ['id' => 'elsewhere', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
        ['id' => 'someone-else', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()],
    ]);
    config(['session.driver' => 'database']);
    $this->actingAs($user);

    $this->delete(route('account.destroy'), ['password' => 'wrong'])->assertSessionHasErrorsIn('deletion', 'password');
    expect($user->fresh())->not->toBeNull();

    $this->delete(route('account.destroy'), ['password' => 'password'])->assertRedirect(route('home'));

    $this->assertGuest();
    expect(User::query()->find($user->id))->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $other->id)->exists())->toBeTrue()
        ->and(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
});

test('staff can not delete their account from the site', function () {
    $editor = User::factory()->editor()->create();
    Post::factory()->for(Template::factory())->create(['author_id' => $editor->id]);

    $this->actingAs($editor)->get(route('account.edit'))->assertDontSee('Delete my account');
    $this->delete(route('account.destroy'), ['password' => 'password'])->assertForbidden();

    expect($editor->fresh())->not->toBeNull();
});

test('the privacy note is linked from the footer and sign up', function () {
    $this->get(route('privacy'))->assertOk()->assertSee('Privacy')->assertSee('delete your account');
    $this->get(route('home'))->assertSee('href="'.route('privacy').'"', false);
    $this->get(route('register'))->assertSee('href="'.route('privacy').'"', false);
});

test('the account menu links to the account page', function () {
    $this->actingAs(User::factory()->member()->create())
        ->get(route('home'))
        ->assertSee('href="'.route('account.edit').'"', false);
});
