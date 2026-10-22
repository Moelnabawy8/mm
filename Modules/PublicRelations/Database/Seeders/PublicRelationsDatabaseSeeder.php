<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Database\Seeders;

use Illuminate\Database\Seeder;

class PublicRelationsDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PrPermissionSeeder::class,
        ]);
    }
}
