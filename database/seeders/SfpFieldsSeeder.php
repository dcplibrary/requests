<?php

namespace Dcplibrary\Requests\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds default custom fields for the SFP (Suggest for Purchase) form.
 */
class SfpFieldsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $consoleCondition = json_encode([
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['video-game']],
            ],
        ]);

        $fields = [
            [
                'key' => 'where_heard',
                'label' => 'Where did you hear about this?',
                'type' => 'textarea',
                'step' => 2,
                'request_kind' => 'sfp',
                'sort_order' => 1,
                'active' => true,
                'required' => false,
                'include_as_token' => true,
                'filterable' => false,
                'condition' => null,
            ],
            [
                'key' => 'console',
                'label' => 'Console',
                'type' => 'select',
                'step' => 2,
                'request_kind' => 'sfp',
                'sort_order' => 2,
                'active' => true,
                'required' => false,
                'include_as_token' => true,
                'filterable' => false,
                'condition' => $consoleCondition,
            ],
            [
                'key' => 'ill_requested',
                'label' => 'If the library decides not to purchase this item, would you like the library to try to obtain it from another library (via interlibrary loan)?',
                'type' => 'checkbox',
                'step' => 2,
                'request_kind' => 'sfp',
                'sort_order' => 3,
                'active' => true,
                'required' => false,
                'include_as_token' => true,
                'filterable' => false,
                'condition' => null,
            ],
        ];

        foreach ($fields as $field) {
            DB::table('custom_fields')->updateOrInsert(
                ['key' => $field['key']],
                array_merge($field, ['created_at' => $now, 'updated_at' => $now])
            );
        }

        $this->seedConsoleOptions();
    }

    private function seedConsoleOptions(): void
    {
        $consoleFieldId = DB::table('custom_fields')->where('key', 'console')->value('id');
        if (! $consoleFieldId) {
            return;
        }

        $consoles = [
            ['name' => 'Nintendo Switch', 'slug' => 'switch', 'sort_order' => 1, 'active' => true],
            ['name' => 'PlayStation 5', 'slug' => 'playstation-5', 'sort_order' => 2, 'active' => true],
            ['name' => 'Xbox One', 'slug' => 'xbox-one', 'sort_order' => 3, 'active' => true],
        ];

        foreach ($consoles as $console) {
            DB::table('custom_field_options')->updateOrInsert(
                [
                    'custom_field_id' => $consoleFieldId,
                    'slug' => $console['slug'],
                ],
                [
                    'name' => $console['name'],
                    'sort_order' => $console['sort_order'],
                    'active' => $console['active'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
