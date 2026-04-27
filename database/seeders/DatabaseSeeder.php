<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    private const COMPANY_NAME = 'QS Smart Data Center';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'superadmin@qs.local'],
            [
                'name' => 'System Superadmin',
                'username' => 'superadmin',
                'password' => Hash::make('superadmin1234'),
                'company_name' => self::COMPANY_NAME,
                'role' => User::ROLE_SUPERADMIN,
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@qs.local'],
            [
                'name' => 'System Admin',
                'username' => 'admin',
                'password' => Hash::make('admin1234'),
                'company_name' => self::COMPANY_NAME,
                'role' => User::ROLE_ADMIN,
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff@qs.local'],
            [
                'name' => 'Staff User',
                'username' => 'staff',
                'password' => Hash::make('staff1234'),
                'company_name' => self::COMPANY_NAME,
                'role' => User::ROLE_STAFF,
            ]
        );
    }
}
