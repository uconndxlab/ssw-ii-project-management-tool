<?php

namespace Database\Seeders;

use App\Enums\AccessProfile;
use App\Enums\PrivilegeCapability;
use App\Enums\PrivilegeScopeType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Sarah Johnson',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'access_profile' => AccessProfile::AdminViewer,
            'is_supervisor' => true,
        ]);

        $admin->privileges()->create([
            'capability' => PrivilegeCapability::Admin,
            'scope_type' => PrivilegeScopeType::System,
            'scope_id' => null,
        ]);

        User::factory()->create([
            'name' => 'Michael Chen',
            'email' => 'staff1@example.com',
            'password' => Hash::make('password'),
            'access_profile' => AccessProfile::Member,
        ]);

        User::factory()->create([
            'name' => 'Jennifer Martinez',
            'email' => 'staff2@example.com',
            'password' => Hash::make('password'),
            'access_profile' => AccessProfile::Member,
        ]);

        User::factory()->create([
            'name' => 'David Thompson',
            'email' => 'consultant1@example.com',
            'password' => Hash::make('password'),
            'access_profile' => AccessProfile::Member,
        ]);

        User::factory()->create([
            'name' => 'Emily Rodriguez',
            'email' => 'consultant2@example.com',
            'password' => Hash::make('password'),
            'access_profile' => AccessProfile::Input,
        ]);
    }
}
