<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SQLite: if `id` was created as NOT NULL without INTEGER PRIMARY KEY, inserts that omit `id`
     * fail with "NOT NULL constraint failed: form_field_option_overrides.id".
     * Recreate the table with a proper autoincrement primary key and restore rows.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        if (! Schema::hasTable('form_field_option_overrides')) {
            return;
        }

        $rows = DB::table('form_field_option_overrides')->get();

        Schema::disableForeignKeyConstraints();
        Schema::drop('form_field_option_overrides');

        Schema::create('form_field_option_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('field_id')->constrained('fields')->cascadeOnDelete();
            $table->string('option_slug');
            $table->string('label_override')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('visible')->default(true);
            $table->timestamps();

            $table->unique(['form_id', 'field_id', 'option_slug'], 'form_field_opt_override_unique');
        });

        foreach ($rows as $row) {
            DB::table('form_field_option_overrides')->insert([
                'form_id' => $row->form_id,
                'field_id' => $row->field_id,
                'option_slug' => $row->option_slug,
                'label_override' => $row->label_override ?? null,
                'sort_order' => (int) ($row->sort_order ?? 0),
                'visible' => (int) ($row->visible ?? 1),
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ]);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }
    }
};
