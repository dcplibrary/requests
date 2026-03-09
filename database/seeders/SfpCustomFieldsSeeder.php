<?php

namespace Dcplibrary\Sfp\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds default custom fields for the SFP (Suggest for Purchase) form.
 * Run after ConsolesSeeder so console options can be copied from sfp_consoles.
 */
class SfpCustomFieldsSeeder extends Seeder
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
            DB::table('sfp_custom_fields')->updateOrInsert(
                ['key' => $field['key']],
                array_merge($field, ['created_at' => $now, 'updated_at' => $now])
            );
        }

        $this->seedConsoleOptions();
    }

    private function seedConsoleOptions(): void
    {
        $consoleFieldId = DB::table('sfp_custom_fields')->where('key', 'console')->value('id');
        if (! $consoleFieldId) {
            return;
        }

        $consoles = DB::table('sfp_consoles')->orderBy('sort_order')->get();
        foreach ($consoles as $i => $row) {
            DB::table('sfp_custom_field_options')->updateOrInsert(
                [
                    'custom_field_id' => $consoleFieldId,
                    'slug' => $row->slug,
                ],
                [
                    'name' => $row->name,
                    'sort_order' => $i + 1,
                    'active' => (bool) $row->active,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
