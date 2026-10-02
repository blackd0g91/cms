<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('the create user command creates an admin', function () {
    $this->artisan('app:create-user')
        ->expectsQuestion('Name', 'Jane')
        ->expectsQuestion('Email', 'jane@example.com')
        ->expectsQuestion('Password', 'secret-password')
        ->assertSuccessful();

    $user = User::where('email', 'jane@example.com')->sole();

    expect($user->name)->toBe('Jane')
        ->and($user->role)->toBe(UserRole::Admin)
        ->and(Hash::check('secret-password', (string) $user->password))->toBeTrue();
});
