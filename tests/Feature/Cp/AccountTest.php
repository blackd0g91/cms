<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('guests can not access the account page', function () {
    $this->get(route('cp.account.edit'))->assertRedirect(route('cp.login'));
});

test('the account page can be rendered', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('cp.account.edit'))
        ->assertOk();
});

test('the name and email can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('cp.account.update'), ['name' => 'Murilo', 'email' => 'new@example.com'])
        ->assertRedirect(route('cp.account.edit'));

    expect($user->fresh())
        ->name->toBe('Murilo')
        ->email->toBe('new@example.com');
});

test('the email must be valid and unique', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('cp.account.update'), ['name' => 'A', 'email' => 'taken@example.com'])
        ->assertSessionHasErrors('email');

    $this->actingAs($user)
        ->put(route('cp.account.update'), ['name' => 'A', 'email' => 'not-an-email'])
        ->assertSessionHasErrors('email');
});

test('keeping the same email is allowed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('cp.account.update'), ['name' => 'Renamed', 'email' => $user->email])
        ->assertSessionHasNoErrors();
});

test('the password can be changed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('cp.account.password'), [
            'current_password' => 'password',
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('cp.account.edit'));

    expect(Hash::check('a-new-password', $user->fresh()->password))->toBeTrue();
});

test('the current password must be correct', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('cp.account.password'), [
            'current_password' => 'wrong',
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('the new password must be confirmed', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('cp.account.password'), [
            'current_password' => 'password',
            'password' => 'a-new-password',
            'password_confirmation' => 'something-else',
        ])
        ->assertSessionHasErrors('password');
});
