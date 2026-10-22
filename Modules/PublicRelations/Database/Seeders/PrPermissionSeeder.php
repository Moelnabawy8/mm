<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PrPermissionSeeder extends Seeder
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'manage pr entity',
        'create pr entity',
        'edit pr entity',
        'delete pr entity',
        'manage pr custom field',
    ];

    /**
     * Idempotent: safe to run any number of times. Only creates the
     * Permission rows — assign them to roles via the Roles & Permissions UI.
     */
    public function run(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }
}
