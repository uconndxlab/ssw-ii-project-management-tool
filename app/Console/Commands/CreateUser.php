<?php

namespace App\Console\Commands;

use App\Enums\AccessProfile;
use App\Enums\PrivilegeCapability;
use App\Enums\PrivilegeScopeType;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create 
                            {--name= : The name of the user}
                            {--email= : The email address}
                            {--password= : The password}
                            {--profile=member : Access profile (admin_viewer, member, input)}
                            {--system-admin : Grant system-wide admin (implies admin_viewer)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password');
        $systemAdmin = (bool) $this->option('system-admin');
        $profile = $systemAdmin
            ? AccessProfile::AdminViewer->value
            : ($this->option('profile') ?: $this->choice('Access profile', AccessProfile::values(), AccessProfile::Member->value));

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'access_profile' => $profile,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'min:8'],
            'access_profile' => ['required', 'in:'.implode(',', AccessProfile::values())],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'access_profile' => $profile,
            'is_supervisor' => false,
        ]);

        if ($systemAdmin || $profile === AccessProfile::AdminViewer->value) {
            $user->privileges()->create([
                'capability' => PrivilegeCapability::Admin,
                'scope_type' => PrivilegeScopeType::System,
                'scope_id' => null,
            ]);
            $user->forceFill(['access_profile' => AccessProfile::AdminViewer])->save();
        }

        $this->info('User created successfully!');
        $this->table(
            ['ID', 'Name', 'Email', 'Profile'],
            [[$user->id, $user->name, $user->email, $user->fresh()->accessLabel()]]
        );

        return self::SUCCESS;
    }
}
