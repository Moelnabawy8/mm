<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Interest;
use App\Models\User;

class InterestSeeder extends Seeder
{
    public function run()
    {
        $creator = User::where('type', 'company')->first();

        if (!$creator) {
            $creator = User::first();
        }

        if (!$creator) {
            return;
        }

        $interests = [
            'Real Estate',
            'Investment',
            'Technology',
            'Education',
            'Healthcare',
            'Finance',
            'Marketing',
            'Sales',
            'Consulting',
            'E-commerce',
            'Software Development',
            'Mobile Apps',
            'Web Design',
            'Digital Marketing',
            'Business Services',
        ];

        foreach ($interests as $interest) {
            Interest::firstOrCreate(
                [
                    'name' => $interest,
                    'created_by' => $creator->id,
                ],
                [
                    'name' => $interest,
                    'created_by' => $creator->id,
                ]
            );
        }
    }
}
