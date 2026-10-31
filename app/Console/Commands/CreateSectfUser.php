<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Creates a login. Public registration is off, so this is the only way in.
 * Runs non-interactively (docker compose exec -T), so a password that is not
 * passed is generated and printed once.
 */
#[Signature('sectf:user {name : Display name, shown on every finding} {email} {--password= : Leave empty to generate one} {--reset : Set a new password on an existing user}')]
#[Description('Create a SECTF Recon login (or reset its password with --reset)')]
class CreateSectfUser extends Command
{
    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $password = (string) ($this->option('password') ?: Str::password(20, symbols: false));

        $validator = Validator::make(
            ['name' => $this->argument('name'), 'email' => $email, 'password' => $password],
            ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'min:12']],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user !== null && ! $this->option('reset')) {
            $this->error("A user with email [{$email}] already exists. Pass --reset to give them a new password.");

            return self::FAILURE;
        }

        $user ??= new User(['email' => $email]);
        $user->name = (string) $this->argument('name');
        $user->password = $password;
        $user->email_verified_at ??= now();
        $created = ! $user->exists;
        $user->save();

        $this->info(($created ? 'Created' : 'Updated')." user #{$user->id} {$user->name} <{$user->email}>.");

        if (! $this->option('password')) {
            $this->line("Password: {$password}");
            $this->comment('Shown once. Change it under Settings after logging in.');
        }

        return self::SUCCESS;
    }
}
