<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SalesMeetingType;
use App\Models\User;

class SalesMeetingTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::where('type', 'company')->get();

        $types = [
            'Initial Consultation',
            'Follow-up Meeting',
            'Product Demo',
            'Negotiation',
            'Closing Meeting',
            'Online Meeting',
            'In-Person Meeting',
            'Phone Call',
        ];

        foreach ($users as $user) {
            foreach ($types as $type) {
                SalesMeetingType::firstOrCreate([
                    'name' => $type,
                    'created_by' => $user->id,
                ]);
            }
        }
    }
}
