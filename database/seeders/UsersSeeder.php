<?php

namespace Dcplibrary\Requests\Database\Seeders;

use Dcplibrary\Requests\Models\User;
use Illuminate\Database\Seeder;

class UsersSeeder extends Seeder
{
    /**
     * Seed an initial admin user.
     *
     * On first deploy, run:
     *   php artisan db:seed --class=Dcplibrary\\Requests\\Database\\Seeders\\RequestsDatabaseSeeder
     *
     * The entra_id will be populated automatically on first login via Entra SSO.
     * Override the email via the REQUESTS_ADMIN_EMAIL environment variable.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => env('REQUESTS_ADMIN_EMAIL', 'blashbrook@dcplibrary.org')],
            [
                'name'   => env('REQUESTS_ADMIN_NAME', 'Requests Admin'),
                'role'   => 'admin',
                'active' => true,
            ]
        );
    }
}
