<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Stage;
use App\Models\User;

class DealStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Get all company users (creators)
        $companies = User::where('type', 'company')->get();

        foreach ($companies as $company) {
            // Check if stages already exist for this company
            $existingStages = Stage::where('created_by', $company->id)->count();

            if ($existingStages == 0) {
                // Create new stages if none exist
                $stages = [
                    ['name' => 'Contacted', 'order' => 1],
                    ['name' => 'Needs Analysis', 'order' => 2],
                    ['name' => 'Negotiation', 'order' => 3],
                    ['name' => 'Price Offer', 'order' => 4],
                    ['name' => 'Decision', 'order' => 5],
                    ['name' => 'Postponed', 'order' => 6],
                    ['name' => 'Won', 'order' => 7],
                    ['name' => 'Lost', 'order' => 8],
                ];

                foreach ($stages as $stage) {
                    Stage::create([
                        'name' => $stage['name'],
                        'order' => $stage['order'],
                        'created_by' => $company->id,
                    ]);
                }
            } else {
                // Update existing stages to match the new structure
                // Delete old stages first
                Stage::where('created_by', $company->id)->delete();

                // Create new stages
                $stages = [
                    ['name' => 'Contacted', 'order' => 1],
                    ['name' => 'Needs Analysis', 'order' => 2],
                    ['name' => 'Negotiation', 'order' => 3],
                    ['name' => 'Price Offer', 'order' => 4],
                    ['name' => 'Decision', 'order' => 5],
                    ['name' => 'Postponed', 'order' => 6],
                    ['name' => 'Won', 'order' => 7],
                    ['name' => 'Lost', 'order' => 8],
                ];

                foreach ($stages as $stage) {
                    Stage::create([
                        'name' => $stage['name'],
                        'order' => $stage['order'],
                        'created_by' => $company->id,
                    ]);
                }
            }
        }
    }
}
