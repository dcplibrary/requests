<?php

namespace Dcplibrary\Sfp\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // --- Rate limiting ---
            [
                'key' => 'sfp_limit_count',
                'value' => '5',
                'label' => 'Request Limit Count',
                'type' => 'integer',
                'group' => 'rate_limiting',
                'description' => 'Maximum number of SFP requests a patron can submit within the limit window.',
            ],
            [
                'key' => 'sfp_limit_window',
                'value' => 'day',
                'label' => 'Request Limit Window',
                'type' => 'string',
                'group' => 'rate_limiting',
                'description' => 'Time window for rate limiting. Options: day, week, month.',
            ],

            // --- ILL / age threshold ---
            [
                'key' => 'ill_age_threshold_years',
                'value' => '2',
                'label' => 'ILL Age Threshold (Years)',
                'type' => 'integer',
                'group' => 'ill',
                'description' => 'Items older than this many years will trigger the ILL soft warning.',
            ],
            [
                'key' => 'ill_warning_message',
                'value' => 'This item was published more than 2 years ago. For older titles, we recommend trying our Interlibrary Loan (ILL) Service, which can often obtain items not in our collection.',
                'label' => 'ILL Warning Message',
                'type' => 'text',
                'group' => 'ill',
                'description' => 'Message shown to patrons when their item exceeds the age threshold.',
            ],

            // --- Duplicate request messaging ---
            [
                'key' => 'duplicate_request_message',
                'value' => 'This item has already been requested by another patron. Please regularly check the catalog for availability. If the item becomes available, be sure to place a hold directly.',
                'label' => 'Duplicate Request Message',
                'type' => 'text',
                'group' => 'messaging',
                'description' => 'Shown to patrons when their submitted item matches an existing request.',
            ],

            // --- Submission confirmation ---
            [
                'key' => 'submission_success_message',
                'value' => 'Thank you for your suggestion! We review all requests and will consider it for our collection. Because we receive many suggestions, we\'re unable to respond individually or provide status updates.',
                'label' => 'Submission Success Message',
                'type' => 'text',
                'group' => 'messaging',
                'description' => 'Shown to patrons after a successful SFP submission.',
            ],

            // --- Catalog search ---
            [
                'key' => 'catalog_search_enabled',
                'value' => '1',
                'label' => 'Enable Catalog Search',
                'type' => 'boolean',
                'group' => 'catalog',
                'description' => 'Search Bibliocommons catalog during submission.',
            ],
            [
                'key' => 'catalog_search_url_template',
                'value' => 'https://dcpl.bibliocommons.com/v2/search?custom_edit=false&query=(title%3A({title})%20AND%20contributor%3A({author})%20)%20audience%3A%22{audience}%22%20pubyear%3A%5B{year_from}%20TO%20{year_to}%5D&searchType=bl&suppress=true',
                'label' => 'Catalog Search URL Template',
                'type' => 'text',
                'group' => 'catalog',
                'description' => 'URL template for Bibliocommons search. Tokens: {title}, {author}, {audience}, {year_from}, {year_to}.',
            ],

            // --- ISBNdb ---
            [
                'key' => 'isbndb_search_enabled',
                'value' => '1',
                'label' => 'Enable ISBNdb Search',
                'type' => 'boolean',
                'group' => 'isbndb',
                'description' => 'Search ISBNdb API when catalog search returns no results.',
            ],

            // --- Post-submit processing ---
            [
                'key' => 'post_submit_mode',
                'value' => 'wait',
                'label' => 'Post-Submit Processing Mode',
                'type' => 'string',
                'group' => 'processing',
                'description' => 'How to handle post-submission processing. Options: wait (patron waits on page), email (send confirmation when done).',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
