<?php

namespace Dcplibrary\Sfp\Database\Seeders;

use Dcplibrary\Sfp\Models\User;
use Illuminate\Database\Seeder;

class UsersSeeder extends Seeder
{
    /**
     * Seed an initial admin user.
     *
     * On first deploy, run:
     *   php artisan db:seed --class=Dcplibrary\\Sfp\\Database\\Seeders\\SfpDatabaseSeeder
     *
     * The entra_id will be populated automatically on first login via Entra SSO.
     * Override the email via the SFP_ADMIN_EMAIL environment variable.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => env('SFP_ADMIN_EMAIL', 'blashbrook@dcplibrary.org')],
            [
                'name'   => env('SFP_ADMIN_NAME', 'SFP Admin'),
                'role'   => 'admin',
                'active' => true,
            ]
        );
    }
}
