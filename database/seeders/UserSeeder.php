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
        $admin = User::firstOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Sarah Johnson',
            'password' => Hash::make('password'),
            'access_profile' => AccessProfile::AdminViewer,
            'is_supervisor' => true,
        ]);

        $admin->privileges()->firstOrCreate([
            'capability' => PrivilegeCapability::Admin,
            'scope_type' => PrivilegeScopeType::System,
            'scope_id' => null,
        ]);

        User::firstOrCreate(['email' => 'staff1@example.com'], [
            'name' => 'Michael Chen',
            'password' => Hash::make('password'),
            'access_profile' => AccessProfile::Member,
        ]);

        User::firstOrCreate(['email' => 'staff2@example.com'], [
            'name' => 'Jennifer Martinez',
            'password' => Hash::make('password'),
            'access_profile' => AccessProfile::Member,
        ]);

        User::firstOrCreate(['email' => 'consultant1@example.com'], [
            'name' => 'David Thompson',
            'password' => Hash::make('password'),
            'access_profile' => AccessProfile::Member,
        ]);

        User::firstOrCreate(['email' => 'consultant2@example.com'], [
            'name' => 'Emily Rodriguez',
            'password' => Hash::make('password'),
            'access_profile' => AccessProfile::Input,
        ]);
    }
}
