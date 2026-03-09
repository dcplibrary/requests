<?php

namespace Dcplibrary\Sfp\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds default custom fields for the patron-facing ILL form.
 *
 * Fields that exist in sfp_form_fields (title, author, publish_date, isbn)
 * are not seeded here; they are consolidated in form fields. where_heard is a custom field for SFP only. This seeder also deletes
 * any existing custom field rows for those keys. The ILL form should source those
 * from form fields (or form_custom_fields) when rendering step 2.
 */
class IllCustomFieldsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $fields = [
            [
                'key' => 'date_needed_by',
                'label' => 'Date needed by',
                'type' => 'date',
                'step' => 2,
                'request_kind' => 'ill',
                'sort_order' => 2,
                'active' => true,
                'required' => false,
                'include_as_token' => true,
                'filterable' => false,
                'condition' => null,
            ],
            [
                'key' => 'will_pay_up_to',
                'label' => 'Will pay charges up to',
                'type' => 'number',
                'step' => 2,
                'request_kind' => 'ill',
                'sort_order' => 3,
                'active' => true,
                'required' => false,
                'include_as_token' => true,
                'filterable' => false,
                'condition' => null,
            ],
            [
                'key' => 'prefer_email',
                'label' => 'I prefer to be contacted by email',
                'type' => 'checkbox',
                'step' => 2,
                'request_kind' => 'ill',
                'sort_order' => 4,
                'active' => true,
                'required' => false,
                'include_as_token' => true,
                'filterable' => false,
                'condition' => null,
            ],
            [
                'key' => 'other_specify',
                'label' => 'Details (please describe what you need)',
                'type' => 'textarea',
                'step' => 2,
                'request_kind' => 'ill',
                'sort_order' => 5,
                'active' => true,
                'required' => false,
                'include_as_token' => true,
                'filterable' => false,
                'condition' => json_encode([
                    'match' => 'any',
                    'rules' => [
                        ['field' => 'material_type', 'operator' => 'in', 'values' => ['other']],
                    ],
                ]),
            ],
            // title, author, publish_date, isbn, where_heard are consolidated in sfp_form_fields — do not seed here
            [
                'key' => 'publisher',
                'label' => 'Publisher',
                'type' => 'text',
                'step' => 2,
                'request_kind' => 'ill',
                'sort_order' => 13,
                'active' => true,
                'required' => false,
                'include_as_token' => true,
                'filterable' => false,
                'condition' => json_encode([
                    'match' => 'any',
                    'rules' => [
                        ['field' => 'material_type', 'operator' => 'in', 'values' => ['book']],
                    ],
                ]),
            ],
            [
                'key' => 'periodical_title',
                'label' => 'Periodical/Newspaper Title',
                'type' => 'text',
                'step' => 2,
                'request_kind' => 'ill',
                'sort_order' => 20,
                'active' => true,
                'required' => false,
                'include_as_token' => true,
                'filterable' => false,
                'condition' => json_encode([
                    'match' => 'any',
                    'rules' => [
                        ['field' => 'material_type', 'operator' => 'in', 'values' => ['magazine-article', 'newspaper-microfilm']],
                    ],
                ]),
            ],
            [
                'key' => 'article_author',
                'label' => 'Author of Article',
                'type' => 'text',
                'step' => 2,
                'request_kind' => 'ill',
                'sort_order' => 21,
                'active' => true,
                'required' => false,
                'include_as_token' => true,
                'filterable' => false,
                'condition' => json_encode([
                    'match' => 'any',
                    'rules' => [
                        ['field' => 'material_type', 'operator' => 'in', 'values' => ['magazine-article', 'newspaper-microfilm']],
                    ],
                ]),
            ],
            [
                'key' => 'article_title',
                'label' => 'Title of Article',
                'type' => 'text',
                'step' => 2,
                'request_kind' => 'ill',
                'sort_order' => 22,
                'active' => true,
                'required' => false,
                'include_as_token' => true,
                'filterable' => false,
                'condition' => json_encode([
                    'match' => 'any',
                    'rules' => [
                        ['field' => 'material_type', 'operator' => 'in', 'values' => ['magazine-article', 'newspaper-microfilm']],
                    ],
                ]),
            ],
            [
                'key' => 'volume_number',
                'label' => 'Volume Number',
                'type' => 'text',
                'step' => 2,
                'request_kind' => 'ill',
                'sort_order' => 23,
                'active' => true,
                'required' => false,
                'include_as_token' => true,
                'filterable' => false,
                'condition' => json_encode([
                    'match' => 'any',
                    'rules' => [
                        ['field' => 'material_type', 'operator' => 'in', 'values' => ['magazine-article', 'newspaper-microfilm']],
                    ],
                ]),
            ],
            [
                'key' => 'page_number',
                'label' => 'Page Number',
                'type' => 'text',
                'step' => 2,
                'request_kind' => 'ill',
                'sort_order' => 24,
                'active' => true,
                'required' => false,
                'include_as_token' => true,
                'filterable' => false,
                'condition' => json_encode([
                    'match' => 'any',
                    'rules' => [
                        ['field' => 'material_type', 'operator' => 'in', 'values' => ['magazine-article', 'newspaper-microfilm']],
                    ],
                ]),
            ],
            [
                'key' => 'director',
                'label' => 'Director',
                'type' => 'text',
                'step' => 2,
                'request_kind' => 'ill',
                'sort_order' => 30,
                'active' => true,
                'required' => false,
                'include_as_token' => true,
                'filterable' => false,
                'condition' => json_encode([
                    'match' => 'any',
                    'rules' => [
                        ['field' => 'material_type', 'operator' => 'in', 'values' => ['dvd']],
                    ],
                ]),
            ],
            [
                'key' => 'cast',
                'label' => 'Cast',
                'type' => 'textarea',
                'step' => 2,
                'request_kind' => 'ill',
                'sort_order' => 31,
                'active' => true,
                'required' => false,
                'include_as_token' => true,
                'filterable' => false,
                'condition' => json_encode([
                    'match' => 'any',
                    'rules' => [
                        ['field' => 'material_type', 'operator' => 'in', 'values' => ['dvd']],
                    ],
                ]),
            ],
            [
                'key' => 'comments',
                'label' => 'Comments',
                'type' => 'textarea',
                'step' => 2,
                'request_kind' => 'ill',
                'sort_order' => 32,
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
                array_merge($field, ['updated_at' => $now, 'created_at' => $now])
            );
        }

        // Remove custom fields consolidated into sfp_form_fields (title, author, publish_date, isbn)
        // where_heard is now a custom field for SFP only — do not delete
        DB::table('sfp_custom_fields')->whereIn('key', [
            'title',
            'author',
            'publish_date',
            'isbn',
        ])->delete();

        // Remove legacy ILL-only keys (old names)
        DB::table('sfp_custom_fields')->whereIn('key', [
            'ill_title',
            'ill_author',
            'publication_date',
            'isbn_number',
        ])->delete();

        // ILL now uses material_types (MaterialType model); remove borrow_type custom field
        DB::table('sfp_custom_fields')->where('key', 'borrow_type')->delete();
    }
}

