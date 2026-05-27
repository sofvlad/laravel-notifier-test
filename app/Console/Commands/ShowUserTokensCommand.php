<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'user:api-tokens',
    description: 'Show all API tokens for a user',
)]
class ShowUserTokensCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:api-tokens {email?}';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email') ?? $this->ask('User email');

        /** @var User|null $user */
        $user = User::where('email', $email)->first();
        if ($user === null) {
            $this->error("User with email [{$email}] not found.");

            return static::FAILURE;
        }

        $tokens = $user->tokens()->orderBy('created_at', 'desc')->get();
        if ($tokens->isEmpty()) {
            $this->info("No tokens found for user [{$user->name}] ({$email}).");

            return static::SUCCESS;
        }

        $this->newLine();
        $this->info("Tokens for user [{$user->name}] ({$email}):");
        $this->newLine();

        $this->table(
            ['#', 'Name', 'Created', 'Last Used', 'Scopes', 'Revoked'],
            $tokens->map(fn($token, $index) => [
                $index + 1,
                $token->name,
                $token->created_at->format('Y-m-d H:i:s'),
                $token->last_used_at?->format('Y-m-d H:i:s') ?? 'Never',
                implode(', ', $token->abilities ?? []),
                $token->revoked ? 'Yes' : 'No',
            ])->toArray()
        );

        $this->newLine();
        $this->info("Total tokens: {$tokens->count()}");

        return static::SUCCESS;
    }
}
