<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@mycommunication.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('123456'),
                'role' => 'admin',
            ]
        );
    }
}
