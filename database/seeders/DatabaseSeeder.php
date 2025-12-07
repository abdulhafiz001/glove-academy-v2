<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * 
     * This seeder can be configured for different environments:
     * - Production: Use `php artisan db:seed-production` command
     * - Development: Run AdminSeeder + DevelopmentSeeder
     * - Testing: Run specific seeders as needed
     */
    public function run(): void
    {
        // Always create admin user first
        $this->call([
            AdminSeeder::class,
        ]);

        // Check if we should run development data
        // You can control this via environment variable or command line option
        if (app()->environment('local') || $this->command->option('with-dev-data')) {
            $this->call([
                DevelopmentSeeder::class,
            ]);
        }
    }
}
