<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreatePlatformAdmin extends Command
{
    protected $signature = 'platform:create-admin {email} {--name=Platform admin}';

    protected $description = 'Create a super-admin. The password is typed in, never passed as an argument (it would land in shell history).';

    public function handle(): int
    {
        $password = (string) $this->secret('Password (min 12 chars, mixed case, numbers)');

        $validator = Validator::make(
            ['email' => $this->argument('email'), 'password' => $password],
            ['email' => ['required', 'email'], 'password' => ['required', Password::min(12)->mixedCase()->numbers()]],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        PlatformAdmin::query()->create([
            'email' => strtolower((string) $this->argument('email')),
            'name' => (string) $this->option('name'),
            'password' => $password,
        ]);

        $this->info('Created. Two-factor setup is required at first login.');

        return self::SUCCESS;
    }
}
