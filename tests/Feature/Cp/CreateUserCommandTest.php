<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('the create user command creates a user', function () {
    $this->artisan('app:create-user')
        ->expectsQuestion('Name', 'Jane')
        ->expectsQuestion('Email', 'jane@example.com')
        ->expectsQuestion('Password', 'secret-password')
        ->assertSuccessful();

    $user = User::where('email', 'jane@example.com')->sole();

    expect($user->name)->toBe('Jane')
        ->and(Hash::check('secret-password', $user->password))->toBeTrue();
});
