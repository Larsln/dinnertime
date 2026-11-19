<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user:revoke
    {username : The account whose devices are signed out}')]
#[Description('Sign a user out on every device')]
class RevokeUserTokens extends Command
{
    /**
     * Execute the console command.
     *
     * Removing someone at the identity provider does not reach devices
     * that are signed in already; this command does.
     */
    public function handle(): int
    {
        $user = User::firstWhere('username', $this->argument('username'));

        if ($user === null) {
            $this->components->error("There is no user [{$this->argument('username')}].");

            return self::FAILURE;
        }

        $count = $user->tokens()->delete();
        $this->components->info("Signed [{$user->username}] out on {$count} device(s).");

        return self::SUCCESS;
    }
}
