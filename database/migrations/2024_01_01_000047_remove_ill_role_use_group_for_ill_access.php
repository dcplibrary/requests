<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remove the 'ill' role from sfp_users. ILL access is group-based: the
 * selector group identified by ill_selector_group_id grants ILL queue access
 * regardless of the group's name. Users who had role 'ill' are set to
 * 'selector'; admins must assign them to the ILL group if they should
 * retain ILL access.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('sfp_users')->where('role', 'ill')->update(['role' => 'selector']);

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->alterSqlite();
        } else {
            DB::statement("ALTER TABLE sfp_users MODIFY COLUMN role ENUM('admin','selector') NOT NULL DEFAULT 'selector'");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->alterSqliteDown();
        } else {
            DB::statement("ALTER TABLE sfp_users MODIFY COLUMN role ENUM('admin','selector','ill') NOT NULL DEFAULT 'selector'");
        }
    }

    private function alterSqlite(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement("
            CREATE TABLE sfp_users_new (
                id         INTEGER  PRIMARY KEY AUTOINCREMENT NOT NULL,
                name       VARCHAR  NOT NULL,
                email      VARCHAR  NOT NULL,
                entra_id   VARCHAR  NULL,
                role       VARCHAR  NOT NULL DEFAULT 'selector'
                               CHECK (role IN ('admin','selector')),
                active     TINYINT  NOT NULL DEFAULT 1,
                last_login_at DATETIME NULL,
                remember_token VARCHAR NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                CONSTRAINT sfp_users_email_unique UNIQUE (email),
                CONSTRAINT sfp_users_entra_id_unique UNIQUE (entra_id)
            )
        ");
        DB::statement('INSERT INTO sfp_users_new SELECT id, name, email, entra_id, role, active, last_login_at, remember_token, created_at, updated_at FROM sfp_users');
        DB::statement('DROP TABLE sfp_users');
        DB::statement('ALTER TABLE sfp_users_new RENAME TO sfp_users');
        DB::statement('PRAGMA foreign_keys = ON');
    }

    private function alterSqliteDown(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        DB::statement("
            CREATE TABLE sfp_users_new (
                id         INTEGER  PRIMARY KEY AUTOINCREMENT NOT NULL,
                name       VARCHAR  NOT NULL,
                email      VARCHAR  NOT NULL,
                entra_id   VARCHAR  NULL,
                role       VARCHAR  NOT NULL DEFAULT 'selector'
                               CHECK (role IN ('admin','selector','ill')),
                active     TINYINT  NOT NULL DEFAULT 1,
                last_login_at DATETIME NULL,
                remember_token VARCHAR NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                CONSTRAINT sfp_users_email_unique UNIQUE (email),
                CONSTRAINT sfp_users_entra_id_unique UNIQUE (entra_id)
            )
        ");
        DB::statement('INSERT INTO sfp_users_new SELECT * FROM sfp_users');
        DB::statement('DROP TABLE sfp_users');
        DB::statement('ALTER TABLE sfp_users_new RENAME TO sfp_users');
        DB::statement('PRAGMA foreign_keys = ON');
    }
};
