<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BootstrapInitialAdmin extends Command
{
    protected $signature = 'app:bootstrap-initial-admin';

    protected $description = 'Create the first administrator from private deployment environment variables';

    public function handle(): int
    {
        if (User::where('role', 'admin')->exists()) {
            $this->info('An administrator already exists; initial-admin bootstrap skipped.');

            return self::SUCCESS;
        }

        $name = trim((string) env('INITIAL_ADMIN_NAME'));
        $email = Str::lower(trim((string) env('INITIAL_ADMIN_EMAIL')));
        $password = (string) env('INITIAL_ADMIN_PASSWORD');

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            $this->error('Initial administrator was not created. Set INITIAL_ADMIN_NAME, INITIAL_ADMIN_EMAIL, and a 12+ character INITIAL_ADMIN_PASSWORD.');

            return self::FAILURE;
        }

        $username = Str::upper(trim((string) env('INITIAL_ADMIN_USERNAME')));
        if ($username === '') {
            $username = Str::upper(Str::before($email, '@'));
        }
        $username = Str::limit($username, 10, '');

        User::create([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make($password),
            'employee_id' => $username,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->info('Initial administrator created.');

        return self::SUCCESS;
    }
}
