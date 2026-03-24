<?php

namespace Dcplibrary\Requests\Database\Seeders;

use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\Form;
use Dcplibrary\Requests\Models\FormFieldConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the unified fields, field_options, and form_field_config tables.
 *
 * Replaces: MaterialTypesSeeder, AudiencesSeeder, GenresSeeder, ConsolesSeeder,
 *           FormFieldsSeeder, SfpFieldsSeeder, IllCustomFieldsSeeder,
 *           FormFormFieldsSeeder.
 *
 * Run after FormsSeeder.
 */
class FieldsAndOptionsSeeder extends Seeder
{
    /**
     * Run the seeder.
     *
     * @return void
     */
    public function run(): void
    {
        // Retired: ILL patrons use notify_by_email on patron step (same as SFP).
        DB::table('fields')->where('key', 'prefer_email')->update([
            'active' => false,
            'include_as_token' => false,
            'updated_at' => now(),
        ]);
        if (Schema::hasTable('custom_fields')) {
            DB::table('custom_fields')->where('key', 'prefer_email')->delete();
        }

        $this->seedForms();
        $this->seedFields();
        $this->seedFieldOptions();
        $this->seedFormFieldConfig();
    }

    /**
     * Ensure the two core forms exist.
     *
     * @return void
     */
    private function seedForms(): void
    {
        Form::firstOrCreate(['slug' => 'sfp'], ['name' => 'Suggest for Purchase']);
        Form::firstOrCreate(['slug' => 'ill'], ['name' => 'Interlibrary Loan']);
    }

    /**
     * Seed all fields into the unified fields table.
     *
     * @return void
     */
    private function seedFields(): void
    {
        $genreCondition = [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book', 'large-print', 'ebook', 'eaudiobook']],
                ['field' => 'audience', 'operator' => 'in', 'values' => ['adult']],
            ],
        ];

        $consoleCondition = [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['video-game']],
            ],
        ];

        $otherCondition = [
            'match' => 'any',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['other']],
            ],
        ];

        $bookCondition = [
            'match' => 'any',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book']],
            ],
        ];

        $periodicalCondition = [
            'match' => 'any',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['magazine-article', 'newspaper-microfilm']],
            ],
        ];

        $dvdCondition = [
            'match' => 'any',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['dvd']],
            ],
        ];

        //                                                                                                           token  filter
        $fields = [
            // Core form fields (scope=both → appear on SFP and ILL)
            ['key' => 'material_type',    'label' => 'Type of Material',                                 'type' => 'select',   'step' => 2, 'scope' => 'both', 'sort_order' => 10,  'active' => true,  'required' => true,  'include_as_token' => false, 'filterable' => true,  'condition' => null],
            ['key' => 'audience',         'label' => 'Audience',                                         'type' => 'radio',    'step' => 2, 'scope' => 'both', 'sort_order' => 20,  'active' => true,  'required' => true,  'include_as_token' => false, 'filterable' => true,  'condition' => null],
            ['key' => 'genre',            'label' => 'Genre',                                            'type' => 'radio',    'step' => 2, 'scope' => 'both', 'sort_order' => 30,  'active' => true,  'required' => true,  'include_as_token' => true,  'filterable' => false, 'condition' => $genreCondition],
            ['key' => 'title',            'label' => 'Title',                                            'type' => 'text',     'step' => 2, 'scope' => 'both', 'sort_order' => 40,  'active' => true,  'required' => true,  'include_as_token' => false, 'filterable' => false, 'condition' => null],
            ['key' => 'author',           'label' => 'Author / Creator',                                 'type' => 'text',     'step' => 2, 'scope' => 'both', 'sort_order' => 50,  'active' => true,  'required' => true,  'include_as_token' => false, 'filterable' => false, 'condition' => null],
            ['key' => 'isbn',             'label' => 'ISBN',                                             'type' => 'text',     'step' => 2, 'scope' => 'both', 'sort_order' => 60,  'active' => false, 'required' => false, 'include_as_token' => true,  'filterable' => false, 'condition' => null],
            ['key' => 'publish_date',     'label' => 'Publish / Release Date',                           'type' => 'date',     'step' => 2, 'scope' => 'both', 'sort_order' => 70,  'active' => true,  'required' => false, 'include_as_token' => true,  'filterable' => false, 'condition' => null],

            // SFP-only custom fields
            ['key' => 'where_heard',      'label' => 'Where did you hear about this?',                   'type' => 'textarea', 'step' => 2, 'scope' => 'sfp',  'sort_order' => 100, 'active' => true,  'required' => false, 'include_as_token' => true,  'filterable' => false, 'condition' => null],
            ['key' => 'console',          'label' => 'Console',                                          'type' => 'radio',    'step' => 2, 'scope' => 'sfp',  'sort_order' => 110, 'active' => true,  'required' => false, 'include_as_token' => true,  'filterable' => false, 'condition' => $consoleCondition],
            ['key' => 'ill_requested',    'label' => 'If the library decides not to purchase this item, would you like the library to try to obtain it from another library (via interlibrary loan)?', 'type' => 'checkbox', 'step' => 2, 'scope' => 'sfp', 'sort_order' => 120, 'active' => true, 'required' => false, 'include_as_token' => true, 'filterable' => false, 'condition' => null],

            // ILL-only custom fields
            ['key' => 'date_needed_by',   'label' => 'Date needed by',                                   'type' => 'date',     'step' => 2, 'scope' => 'ill',  'sort_order' => 200, 'active' => true,  'required' => false, 'include_as_token' => true,  'filterable' => false, 'condition' => null],
            ['key' => 'will_pay_up_to',   'label' => 'Will pay charges up to',                           'type' => 'number',   'step' => 2, 'scope' => 'ill',  'sort_order' => 210, 'active' => true,  'required' => false, 'include_as_token' => true,  'filterable' => false, 'condition' => null],
            ['key' => 'other_specify',    'label' => 'Details (please describe what you need)',           'type' => 'textarea', 'step' => 2, 'scope' => 'ill',  'sort_order' => 230, 'active' => true,  'required' => false, 'include_as_token' => true,  'filterable' => false, 'condition' => $otherCondition],
            ['key' => 'publisher',        'label' => 'Publisher',                                        'type' => 'text',     'step' => 2, 'scope' => 'ill',  'sort_order' => 240, 'active' => true,  'required' => false, 'include_as_token' => true,  'filterable' => false, 'condition' => $bookCondition],
            ['key' => 'periodical_title', 'label' => 'Periodical/Newspaper Title',                       'type' => 'text',     'step' => 2, 'scope' => 'ill',  'sort_order' => 250, 'active' => true,  'required' => false, 'include_as_token' => true,  'filterable' => false, 'condition' => $periodicalCondition],
            ['key' => 'volume_number',    'label' => 'Volume Number',                                    'type' => 'text',     'step' => 2, 'scope' => 'ill',  'sort_order' => 260, 'active' => true,  'required' => false, 'include_as_token' => true,  'filterable' => false, 'condition' => $periodicalCondition],
            ['key' => 'page_number',      'label' => 'Page Number',                                      'type' => 'text',     'step' => 2, 'scope' => 'ill',  'sort_order' => 270, 'active' => true,  'required' => false, 'include_as_token' => true,  'filterable' => false, 'condition' => $periodicalCondition],
            ['key' => 'director',         'label' => 'Director',                                         'type' => 'text',     'step' => 2, 'scope' => 'ill',  'sort_order' => 280, 'active' => true,  'required' => false, 'include_as_token' => true,  'filterable' => false, 'condition' => $dvdCondition],
            ['key' => 'cast',             'label' => 'Cast',                                             'type' => 'textarea', 'step' => 2, 'scope' => 'ill',  'sort_order' => 290, 'active' => true,  'required' => false, 'include_as_token' => true,  'filterable' => false, 'condition' => $dvdCondition],
            ['key' => 'comments',         'label' => 'Comments',                                         'type' => 'textarea', 'step' => 2, 'scope' => 'ill',  'sort_order' => 300, 'active' => true,  'required' => false, 'include_as_token' => true,  'filterable' => false, 'condition' => null],
        ];

        foreach ($fields as $row) {
            Field::updateOrCreate(
                ['key' => $row['key']],
                array_merge($row, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    /**
     * Seed options for select/radio fields.
     *
     * @return void
     */
    private function seedFieldOptions(): void
    {
        $this->seedOptionsForField('material_type', [
            ['name' => 'Book',                'slug' => 'book',                'sort_order' => 1,  'metadata' => ['has_other_text' => false, 'isbndb_searchable' => true,  'ill_enabled' => true]],
            ['name' => 'Large Print',         'slug' => 'large-print',         'sort_order' => 2,  'metadata' => ['has_other_text' => false, 'isbndb_searchable' => false, 'ill_enabled' => true]],
            ['name' => 'Graphic Novel',       'slug' => 'graphic-novel',       'sort_order' => 3,  'metadata' => ['has_other_text' => false, 'isbndb_searchable' => false, 'ill_enabled' => true]],
            ['name' => 'DVD',                 'slug' => 'dvd',                 'sort_order' => 4,  'metadata' => ['has_other_text' => false, 'isbndb_searchable' => false, 'ill_enabled' => true]],
            ['name' => 'Blu-Ray',             'slug' => 'blu-ray',             'sort_order' => 5,  'metadata' => ['has_other_text' => false, 'isbndb_searchable' => false, 'ill_enabled' => true]],
            ['name' => 'eAudiobook',          'slug' => 'eaudiobook',          'sort_order' => 6,  'metadata' => ['has_other_text' => false, 'isbndb_searchable' => false, 'ill_enabled' => true]],
            ['name' => 'eBook',               'slug' => 'ebook',               'sort_order' => 7,  'metadata' => ['has_other_text' => false, 'isbndb_searchable' => false, 'ill_enabled' => true]],
            ['name' => 'Video Game',          'slug' => 'video-game',          'sort_order' => 8,  'metadata' => ['has_other_text' => false, 'isbndb_searchable' => false, 'ill_enabled' => true]],
            ['name' => 'Other',               'slug' => 'other',               'sort_order' => 9,  'metadata' => ['has_other_text' => true,  'isbndb_searchable' => false, 'ill_enabled' => true]],
            ['name' => 'Audiobook',           'slug' => 'audiobook',           'sort_order' => 10, 'metadata' => ['has_other_text' => false, 'isbndb_searchable' => true,  'ill_enabled' => true]],
            ['name' => 'Magazine Article',    'slug' => 'magazine-article',    'sort_order' => 11, 'metadata' => ['has_other_text' => false, 'isbndb_searchable' => false, 'ill_enabled' => true]],
            ['name' => 'Newspaper/Microfilm', 'slug' => 'newspaper-microfilm', 'sort_order' => 12, 'metadata' => ['has_other_text' => false, 'isbndb_searchable' => false, 'ill_enabled' => true]],
        ]);

        $this->seedOptionsForField('audience', [
            ['name' => 'Adult',       'slug' => 'adult',       'sort_order' => 1, 'metadata' => ['bibliocommons_value' => 'adult']],
            ['name' => 'Children',    'slug' => 'children',    'sort_order' => 2, 'metadata' => ['bibliocommons_value' => 'children']],
            ['name' => 'Young Adult', 'slug' => 'young-adult', 'sort_order' => 3, 'metadata' => ['bibliocommons_value' => 'teen']],
        ]);

        $this->seedOptionsForField('genre', [
            ['name' => 'Fiction',    'slug' => 'fiction',    'sort_order' => 1, 'metadata' => null],
            ['name' => 'Nonfiction', 'slug' => 'nonfiction', 'sort_order' => 2, 'metadata' => null],
        ]);

        $this->seedOptionsForField('console', [
            ['name' => 'Nintendo Switch', 'slug' => 'switch',        'sort_order' => 1, 'metadata' => null],
            ['name' => 'PlayStation 5',   'slug' => 'playstation-5', 'sort_order' => 2, 'metadata' => null],
            ['name' => 'Xbox One',        'slug' => 'xbox-one',      'sort_order' => 3, 'metadata' => null],
        ]);
    }

    /**
     * Insert options for a field, identified by key.
     *
     * @param  string  $fieldKey
     * @param  array   $options
     * @return void
     */
    private function seedOptionsForField(string $fieldKey, array $options): void
    {
        $field = Field::where('key', $fieldKey)->first();
        if (! $field) {
            return;
        }

        foreach ($options as $opt) {
            FieldOption::updateOrCreate(
                ['field_id' => $field->id, 'slug' => $opt['slug']],
                [
                    'name'       => $opt['name'],
                    'sort_order' => $opt['sort_order'],
                    'active'     => true,
                    'metadata'   => $opt['metadata'] ? json_encode($opt['metadata']) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Seed form_field_config: attach each field to the correct form(s).
     *
     * @return void
     */
    private function seedFormFieldConfig(): void
    {
        $sfpForm = Form::where('slug', 'sfp')->first();
        $illForm = Form::where('slug', 'ill')->first();
        if (! $sfpForm || ! $illForm) {
            return;
        }

        $fields = Field::ordered()->get();

        foreach ($fields as $field) {
            $formsToAttach = match ($field->scope) {
                'sfp'  => [$sfpForm],
                'ill'  => [$illForm],
                default => [$sfpForm, $illForm], // 'both'
            };

            foreach ($formsToAttach as $form) {
                FormFieldConfig::updateOrCreate(
                    ['form_id' => $form->id, 'field_id' => $field->id],
                    [
                        'sort_order'        => $field->sort_order,
                        'required'          => $field->required,
                        'visible'           => $field->active,
                        'step'              => $field->step,
                        'conditional_logic' => $field->condition,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]
                );
            }
        }
    }
}
