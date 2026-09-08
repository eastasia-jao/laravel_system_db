<?php

namespace Database\Seeders;

use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // 1. Create your Admin User with the required 'role' field
        User::factory()->create([
            'name' => 'System Admin',
            'username' => 'admin',
            'email' => 'admin@inventory.com',
            'password' => bcrypt('password123'),
            'role' => 'admin', // This unlocks your Gate directives!
        ]);

        // 2. Create a default Store Hub
        StoreHub::create([
            'name' => 'Main Branch',
            'code' => 'MAIN-01',
        ]);
    }
}
