<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'user:create',
    description: 'Create a new user with the given name, email, and password',
)]
class CreateUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create {name?} {email?} {--password=}';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name     = $this->argument('name') ?? $this->ask('User name');
        $email    = $this->argument('email') ?? $this->ask('User email');
        $password = $this->option('password') ?? $this->secret('User password');

        $confirmPassword = $this->secret('Confirm password');

        if ($password !== $confirmPassword) {
            $this->error('Passwords do not match.');

            return static::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error("User with email [{$email}] already exists.");

            return static::FAILURE;
        }

        User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => Hash::make($password),
        ]);

        $this->info("User [{$name}] created successfully with email [{$email}].");

        return static::SUCCESS;
    }
}
