<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CreateSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-superadmin 
                            {name : The name of the user}
                            {email : The email of the user}
                            {password : The password of the user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new superadmin user with company role permissions';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $name = $this->argument('name');
        $email = $this->argument('email');
        $password = $this->argument('password');

        // Check if email already exists
        if (User::where('email', $email)->exists()) {
            $this->error("User with email {$email} already exists!");
            return 1;
        }

        // Find the company role (this is the superadmin role)
        $companyRole = Role::where('name', 'company')->first();

        if (!$companyRole) {
            $this->error("Company role not found! Please run database seeders first.");
            return 1;
        }

        // Create the user
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'type' => 'company',
            'default_pipeline' => 1,
            'plan' => 1,
            'lang' => 'en',
            'avatar' => '',
            'created_by' => 1,
            'email_verified_at' => now(),
            'referral_code' => rand(100000, 999999),
        ]);

        // Assign company role (superadmin)
        $user->assignRole($companyRole);

        $this->info("✅ Superadmin user created successfully!");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📧 Email: {$email}");
        $this->info("🔑 Password: {$password}");
        $this->info("👤 Name: {$name}");
        $this->info("🎭 Role: Company (Superadmin)");
        $this->info("🆔 User ID: {$user->id}");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("You can now login with these credentials.");

        return 0;
    }
}
