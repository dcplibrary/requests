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
                'key' => 'sfp_limit_window_days',
                'value' => '30',
                'label' => 'Request Limit Window',
                'type' => 'integer',
                'group' => 'rate_limiting',
                'description' => 'Time window for rate limiting (in days).',
            ],

            // --- ILL / age threshold ---
            [
                'key' => 'ill_age_threshold_days',
                'value' => '730',
                'label' => 'ILL Age Threshold',
                'type' => 'integer',
                'group' => 'ill',
                'description' => 'Items older than this many days will trigger the ILL soft warning.',
            ],
            [
                'key' => 'ill_warning_message',
                'value' => '<p>This item was published more than 2 years ago. For older titles, we recommend trying our <strong>Interlibrary Loan (ILL) Service</strong>, which can often obtain items not in our collection.</p>',
                'label' => 'ILL Warning Message',
                'type' => 'html',
                'group' => 'ill',
                'description' => 'Message shown to patrons when their item exceeds the age threshold.',
            ],

            // --- Duplicate request messaging ---
            [
                'key' => 'duplicate_request_message',
                'value' => '<p>This item has already been requested by another patron. Please regularly check the catalog for availability. If the item becomes available, be sure to place a hold directly.</p>',
                'label' => 'Duplicate Request Message',
                'type' => 'html',
                'group' => 'messaging',
                'description' => 'Shown to patrons when their submitted item matches an existing request from a different patron.',
            ],
            [
                'key' => 'duplicate_self_request_message',
                'value' => "You've already requested this item. We'll let you know when it's available.",
                'label' => 'Duplicate Self-Request Message',
                'type' => 'text',
                'group' => 'messaging',
                'description' => "Shown to patrons when they re-submit an item they've already requested.",
            ],
            [
                'key' => 'duplicate_self_request_message',
                'value' => '<p>You have already submitted a request for this item.</p>',
                'label' => 'Duplicate Self-Request Message',
                'type' => 'html',
                'group' => 'messaging',
                'description' => 'Shown to patrons when they submit an item they have already requested.',
            ],

            // --- Submission confirmation ---
            [
                'key' => 'submission_success_message',
                'value' => '<p>Thank you for your suggestion! We review all requests and will consider it for our collection. Because we receive many suggestions, we\'re unable to respond individually or provide status updates.</p>',
                'label' => 'Submission Success Message',
                'type' => 'html',
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
                'key' => 'catalog_library_slug',
                'value' => 'dcpl',
                'label' => 'Bibliocommons Library Slug',
                'type' => 'string',
                'group' => 'catalog',
                'description' => 'Your Bibliocommons subdomain slug (e.g. "dcpl" for dcpl.bibliocommons.com).',
            ],
            [
                'key' => 'catalog_search_url_template',
                'value' => 'https://{slug}.bibliocommons.com/v2/search?query={query}&searchType=smart',
                'label' => 'Catalog Search URL Template',
                'type' => 'string',
                'group' => 'catalog',
                'description' => 'URL template for catalog search links. Use {slug} and {query} as placeholders.',
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
