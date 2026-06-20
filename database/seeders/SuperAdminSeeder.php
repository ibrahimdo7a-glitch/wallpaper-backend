<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL', 'superadmin@wallpaper.com');
        $password = env('SUPER_ADMIN_PASSWORD', 'SuperAdmin@2024!');

        $superAdmin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'username' => 'superadmin',
                'email' => $email,
                'password' => Hash::make($password),
                'is_active' => true,
                'auto_publish' => true,
                'can_upload_without_watermark' => true,
                'daily_upload_limit' => 9999,
                'max_file_size_mb' => 100,
            ]
        );

        $superAdmin->assignRole('super_admin');

        $this->command->info("Super Admin created: {$email}");
    }
}
