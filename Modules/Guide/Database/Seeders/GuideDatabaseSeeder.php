<?php

namespace Modules\Guide\Database\Seeders;

use Illuminate\Database\Seeder;

class GuideDatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            GuideSeeder::class,
        ]);
    }
}
