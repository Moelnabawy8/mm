<?php

use Illuminate\Contracts\Console\Kernel;
use App\Models\User;
use Spatie\Permission\Models\Role;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

echo "Assigning Role to User 8...\n";

$user = User::find(8);
if ($user) {
    if (!$user->hasRole('company')) {
        $role = Role::where('name', 'company')->first();
        if ($role) {
            $user->assignRole($role);
            echo "Assigned 'company' role to user {$user->name} (ID: 8).\n";
        } else {
            echo "Role 'company' not found.\n";
        }
    } else {
        echo "User already has 'company' role.\n";
    }
} else {
    echo "User 8 not found.\n";
}

echo "Done.\n";
