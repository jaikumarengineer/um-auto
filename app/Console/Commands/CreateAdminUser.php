<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create-user
                            {--name= : Admin full first name}
                            {--last-name= : Admin last name}
                            {--email= : Admin email address}
                            {--password= : Admin password}
                            {--type=admin : Admin role (admin, sub_admin, staff)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an admin panel user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $lastName = $this->option('last-name') ?: $this->ask('Last name (optional)', '');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password');
        $type = $this->option('type') ?: 'admin';

        $validator = Validator::make(
            [
                'name' => $name,
                'last_name' => $lastName,
                'email' => $email,
                'password' => $password,
                'user_type' => $type,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'last_name' => ['nullable', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
                'password' => ['required', 'string', Password::defaults()],
                'user_type' => ['required', 'string', Rule::in(array_keys(User::ADMIN_ROLES))],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $fullName = trim($name.' '.($lastName ?: ''));

        $user = User::create([
            'name' => $name,
            'last_name' => $lastName !== '' ? $lastName : null,
            'email' => $email,
            'username' => generateUserName($fullName),
            'password' => $password,
            'user_type' => $type,
            'is_active' => true,
        ]);

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        $this->info('Admin user created successfully.');
        $this->table(
            ['ID', 'Name', 'Email', 'Username', 'Type'],
            [[
                $user->id,
                $user->full_name,
                $user->email,
                $user->username,
                $user->user_type,
            ]]
        );

        return self::SUCCESS;
    }
}
