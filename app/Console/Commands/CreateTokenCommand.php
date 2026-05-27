<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'user:create-token',
    description: 'Create a new API token for a user',
)]
class CreateTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-token {email?} {--name=Personal Access Token}';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email') ?? $this->ask('User email');

        /** @var User|null $admin */
        $user = User::where('email', $email)->first();
        if ($user === null) {
            $this->error("User with email [{$email}] not found.");

            return static::FAILURE;
        }

        $token = $user->createToken($this->option('name'))->plainTextToken;

        $this->info("API token created for admin [{$user->name}] ({$email}):");
        $this->newLine();
        $this->line("<comment>{$token}</comment>");
        $this->newLine();
        $this->info('Make sure to save this token securely. It will not be shown again.');

        return static::SUCCESS;
    }
}
