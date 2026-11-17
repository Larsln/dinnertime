<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('user:create
    {username? : Name used to sign in}
    {--name= : Display name, defaults to the username}
    {--email= : Optional e-mail address}
    {--password= : Password, asked for interactively when omitted}')]
#[Description('Create a household member with a local password')]
class CreateUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $username = $this->argument('username') ?? text('Username', required: true);

        $attributes = [
            'username' => $username,
            'name' => $this->option('name') ?? $username,
            'email' => $this->option('email'),
            'password' => $this->option('password') ?? password('Password', required: true),
        ];

        $validator = Validator::make($attributes, [
            'username' => ['required', 'string', 'max:50', 'unique:users'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email'],
            'password' => ['required', Password::min(8)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        User::create($attributes);
        $this->components->info("User [{$username}] created.");

        return self::SUCCESS;
    }
}
