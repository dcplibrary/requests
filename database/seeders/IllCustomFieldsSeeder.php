<?php

namespace Dcplibrary\Sfp\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds default custom fields for the patron-facing ILL form.
 *
 * These are intended as sane defaults that staff can edit in the Custom Fields UI.
 */
class IllCustomFieldsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $fields = [
            [
                'key' => 'borrow_type',
                'label' => 'I want to borrow',
                'type' => 'radio',
                'step' => 2,
                'request_kind' => 'ill',
                'sort_order' => 1,
                'active' => true,
                'required' => true,
                'include_as_token' => true,
                'filterable' => true,
                'condition' => null,
            ],
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
                'key' => 'ill_title',
                'label' => 'Title',
                'type' => 'text',
                'step' => 2,
                'request_kind' => 'ill',
                'sort_order' => 10,
                'active' => true,
                'required' => true,
                'include_as_token' => true,
                'filterable' => true,
                'condition' => null,
            ],
            [
                'key' => 'ill_author',
                'label' => 'Author / Creator',
                'type' => 'text',
                'step' => 2,
                'request_kind' => 'ill',
                'sort_order' => 11,
                'active' => true,
                'required' => false,
                'include_as_token' => true,
                'filterable' => false,
                'condition' => null,
            ],
            [
                'key' => 'publisher',
                'label' => 'Publisher',
                'type' => 'text',
                'step' => 2,
                'request_kind' => 'ill',
                'sort_order' => 12,
                'active' => true,
                'required' => false,
                'include_as_token' => true,
                'filterable' => false,
                'condition' => json_encode([
                    'match' => 'any',
                    'rules' => [
                        ['field' => 'borrow_type', 'operator' => 'in', 'values' => ['book', 'audiobook']],
                    ],
                ]),
            ],
            [
                'key' => 'isbn_number',
                'label' => 'ISBN Number',
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
                        ['field' => 'borrow_type', 'operator' => 'in', 'values' => ['book', 'audiobook']],
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
                        ['field' => 'borrow_type', 'operator' => 'in', 'values' => ['magazine-article', 'newspaper-microfilm']],
                    ],
                ]),
            ],
            [
                'key' => 'article_title',
                'label' => 'Title of Article',
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
                        ['field' => 'borrow_type', 'operator' => 'in', 'values' => ['magazine-article', 'newspaper-microfilm']],
                    ],
                ]),
            ],
            [
                'key' => 'article_author',
                'label' => 'Author of Article',
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
                        ['field' => 'borrow_type', 'operator' => 'in', 'values' => ['magazine-article', 'newspaper-microfilm']],
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
                        ['field' => 'borrow_type', 'operator' => 'in', 'values' => ['magazine-article', 'newspaper-microfilm']],
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
                        ['field' => 'borrow_type', 'operator' => 'in', 'values' => ['magazine-article', 'newspaper-microfilm']],
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
                        ['field' => 'borrow_type', 'operator' => 'in', 'values' => ['dvd-vhs']],
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
                        ['field' => 'borrow_type', 'operator' => 'in', 'values' => ['dvd-vhs']],
                    ],
                ]),
            ],
            [
                'key' => 'comments',
                'label' => 'Comments',
                'type' => 'textarea',
                'step' => 2,
                'request_kind' => 'ill',
                'sort_order' => 99,
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

        // Seed borrow_type options
        $borrowFieldId = DB::table('sfp_custom_fields')->where('key', 'borrow_type')->value('id');
        if ($borrowFieldId) {
            $opts = [
                ['name' => 'Book',               'slug' => 'book',                'sort_order' => 1],
                ['name' => 'Audiobook',          'slug' => 'audiobook',           'sort_order' => 2],
                ['name' => 'DVD/VHS',            'slug' => 'dvd-vhs',             'sort_order' => 3],
                ['name' => 'Magazine Article',   'slug' => 'magazine-article',    'sort_order' => 4],
                ['name' => 'Newspaper/Microfilm','slug' => 'newspaper-microfilm', 'sort_order' => 5],
                ['name' => 'Other',              'slug' => 'other',               'sort_order' => 6],
            ];

            foreach ($opts as $opt) {
                DB::table('sfp_custom_field_options')->updateOrInsert(
                    ['custom_field_id' => $borrowFieldId, 'slug' => $opt['slug']],
                    array_merge($opt, [
                        'custom_field_id' => $borrowFieldId,
                        'active'          => true,
                        'updated_at'      => $now,
                        'created_at'      => $now,
                    ])
                );
            }
        }
    }
}

