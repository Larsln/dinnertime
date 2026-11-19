<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user:link-oidc
    {username : The local account the provider account may take over}')]
#[Description('Let the next identity provider sign-in with the same username take over a local account')]
class LinkOidcUser extends Command
{
    /**
     * Execute the console command.
     *
     * An account that is linked already is released again, for example
     * after the provider was set up anew and issues other subjects.
     */
    public function handle(): int
    {
        $user = User::firstWhere('username', $this->argument('username'));

        if ($user === null) {
            $this->components->error("There is no user [{$this->argument('username')}].");

            return self::FAILURE;
        }

        $user->update(['oidc_sub' => null, 'oidc_link_allowed' => true]);
        $this->components->info("The next sign-in of [{$user->username}] at the identity provider takes over this account.");

        return self::SUCCESS;
    }
}
