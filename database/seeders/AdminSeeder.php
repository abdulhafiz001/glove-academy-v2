<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * This seeder creates only the admin user for production setup.
     */
    public function run(): void
    {
        // Create admin user
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Admin User',
                'email' => 'admin@gloveacademy.edu.ng',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone' => '+234 801 234 5678',
                'address' => '123 Admin Street, Lagos',
                'is_active' => true,
            ]
        );

        $this->command->info('Admin user created successfully!');
        $this->command->info('Username: admin');
        $this->command->info('Password: password');
        $this->command->warn('Please change the default password after first login!');
    }
}
