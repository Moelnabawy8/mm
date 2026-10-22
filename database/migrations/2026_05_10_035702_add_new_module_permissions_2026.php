<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    private array $permissions = [
        // Team Support
        'manage support request',
        'create support request',
        'edit support request',
        'delete support request',

        // Quality Evaluation
        'manage quality evaluation',
        'create quality evaluation',
        'edit quality evaluation',
        'delete quality evaluation',

        // Reports Dashboard
        'manage reports dashboard',

        // SOD / EOD
        'manage sod',
        'manage eod',
    ];

    public function up(): void
    {
        foreach ($this->permissions as $name) {
            if (!Permission::where('name', $name)->where('guard_name', 'web')->exists()) {
                Permission::create(['name' => $name, 'guard_name' => 'web']);
            }
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->permissions)->where('guard_name', 'web')->delete();
    }
};
