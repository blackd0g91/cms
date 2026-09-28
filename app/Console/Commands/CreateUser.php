<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-user')]
#[Description('Create a user that can log in to the control panel')]
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
        ]);

        $this->components->info("User [{$email}] created.");

        return self::SUCCESS;
    }
}
