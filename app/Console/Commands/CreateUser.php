<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-user')]
#[Description('Create an admin that can log in to the control panel (others can then be invited from the users page)')]
class CreateUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = text('Name', required: true);

        $email = text(
            'Email',
            required: true,
            validate: ['email' => ['email', 'unique:users,email']],
        );

        $password = password(
            'Password',
            required: true,
            validate: ['password' => ['min:8']],
        );

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Admin,
        ]);

        $this->components->info("Admin [{$email}] created.");

        return self::SUCCESS;
    }
}
