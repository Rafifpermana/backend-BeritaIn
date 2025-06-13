<?php

// database/seeders/UserSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin User
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@beritain.com',
            'password' => Hash::make('password'), // Ganti dengan password yang aman
            'role' => 'admin',
            'points' => 1000,
        ]);

        // Regular User
        User::create([
            'name' => 'Rafif Permana',
            'email' => 'rafif@beritain.com',
            'password' => Hash::make('password'), // Ganti dengan password yang aman
            'role' => 'user',
            'points' => 75,
        ]);
    }
}
