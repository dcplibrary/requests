<?php

namespace Dcplibrary\Sfp\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the configurable form fields for Step 2 of the patron suggestion form.
 *
 * Each field has:
 *   key        — unique identifier, maps to the field's Livewire property / rendering slot
 *   label      — display name shown in the admin UI
 *   sort_order — render order on the patron form (lower = earlier)
 *   active     — whether the field appears at all (false = hidden globally, regardless of condition)
 *   condition  — JSON conditional logic; null = always show when active
 *
 * Condition format:
 * {
 *   "match": "all" | "any",
 *   "rules": [
 *     { "field": "material_type", "operator": "in",     "values": ["book", "large-print"] },
 *     { "field": "audience",      "operator": "not_in", "values": ["children"] }
 *   ]
 * }
 *
 * Supported rule fields : material_type, audience
 * Supported operators   : in, not_in
 */
class FormFieldsSeeder extends Seeder
{
    public function run(): void
    {
        $genreCondition = json_encode([
            'match' => 'all',
            'rules' => [
                [
                    'field'    => 'material_type',
                    'operator' => 'in',
                    'values'   => ['book', 'large-print', 'ebook', 'eaudiobook'],
                ],
                [
                    'field'    => 'audience',
                    'operator' => 'in',
                    'values'   => ['adult'],
                ],
            ],
        ]);

        //                                                                  active   required  include_as_token
        $fields = [
            ['key' => 'material_type', 'label' => 'Type of Material',           'sort_order' => 1,  'active' => true,  'required' => true,  'condition' => null,              'include_as_token' => false],
            ['key' => 'audience',      'label' => 'Audience',                   'sort_order' => 2,  'active' => true,  'required' => true,  'condition' => null,              'include_as_token' => false],
            ['key' => 'genre',         'label' => 'Genre',                      'sort_order' => 3,  'active' => true,  'required' => true,  'condition' => $genreCondition,   'include_as_token' => true],
            ['key' => 'title',         'label' => 'Title',                      'sort_order' => 4,  'active' => true,  'required' => true,  'condition' => null,              'include_as_token' => false],
            ['key' => 'author',        'label' => 'Author / Creator',           'sort_order' => 5,  'active' => true,  'required' => true,  'condition' => null,              'include_as_token' => false],
            ['key' => 'isbn',          'label' => 'ISBN',                       'sort_order' => 6,  'active' => false, 'required' => false, 'condition' => null,              'include_as_token' => true],
            ['key' => 'publish_date',  'label' => 'Publish / Release Date',     'sort_order' => 7,  'active' => true,  'required' => false, 'condition' => null,              'include_as_token' => true],
        ];

        foreach ($fields as $field) {
            DB::table('sfp_form_fields')->updateOrInsert(
                ['key' => $field['key']],
                array_merge($field, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
