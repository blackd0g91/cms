<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

test('guests are redirected from the control panel to the login page', function () {
    $this->get(route('cp.dashboard'))->assertRedirect(route('cp.login'));
});

test('the login page can be rendered', function () {
    $this->get(route('cp.login'))->assertOk();
});

test('users can log in and are sent to the dashboard', function () {
    $user = User::factory()->create();

    $this->post(route('cp.login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('cp.dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('users can not log in with an invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('cp.login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('logins are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(strtolower($user->email).'|127.0.0.1', amount: 5);

    $this->post(route('cp.login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('authenticated users can view the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('cp.dashboard'))
        ->assertOk();
});

test('authenticated users are redirected away from the login page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('cp.login'))
        ->assertRedirect(route('cp.dashboard'));
});

test('users can log out', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('cp.logout'))
        ->assertRedirect(route('cp.login'));

    $this->assertGuest();
});
