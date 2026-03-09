<?php

namespace Dcplibrary\Sfp\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettingsSeeder extends Seeder
{
    /**
     * Ensure the ILL selector group exists and return its ID (or null).
     * Used by both SettingsSeeder and DefaultSettingsSeeder.
     */
    public static function ensureIllGroupExists(): ?int
    {
        if (! Schema::hasTable('selector_groups')) {
            return null;
        }
        $illGroupId = DB::table('selector_groups')->where('name', 'ILL')->value('id');
        if (! $illGroupId) {
            DB::table('selector_groups')->insert([
                'name' => 'ILL',
                'description' => 'Seeded access group for Interlibrary Loan requests.',
                'active' => 1,
                'notification_emails' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $illGroupId = (int) DB::getPdo()->lastInsertId();
        }

        return (int) $illGroupId;
    }

    /**
     * Return the default settings definitions. Pass the ILL group ID for ill_selector_group_id.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaultSettings(?int $illGroupId = null): array
    {
        $illGroupId = $illGroupId ?? self::ensureIllGroupExists();

        return [
            // --- Request limits ---
            [
                'key'         => 'sfp_limit_count',
                'value'       => '5',
                'label'       => 'Request Limit Count',
                'type'        => 'integer',
                'group'       => 'request_limits',
                'description' => 'Maximum number of SFP requests a patron can submit within the limit window.',
            ],
            [
                'key'         => 'sfp_limit_window_type',
                'value'       => 'rolling',
                'label'       => 'Limit Window Type',
                'type'        => 'text',
                'group'       => 'request_limits',
                'description' => 'How the submission limit window is measured: Rolling counts requests within a sliding day window; Calendar Month resets on a fixed day each month; Calendar Week resets every Monday.',
            ],
            [
                'key'         => 'sfp_limit_window_days',
                'value'       => '30',
                'label'       => 'Rolling Window Length',
                'type'        => 'integer',
                'group'       => 'request_limits',
                'description' => 'How many days the rolling window spans (e.g. 30 means a patron may submit up to the limit count within any 30-day period).',
            ],
            [
                'key'         => 'sfp_limit_calendar_reset_day',
                'value'       => '1',
                'label'       => 'Monthly Reset Day',
                'type'        => 'integer',
                'group'       => 'request_limits',
                'description' => 'Day of the month the submission counter resets when using the Calendar Month window type. Must be between 1 and 28.',
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
            [
                'key' => 'ill_isbndb_enabled',
                'value' => '1',
                'label' => 'Enable ISBNdb enrichment for ILL',
                'type' => 'boolean',
                'group' => 'ill',
                'description' => 'When enabled, ILL requests for books/audiobooks search ISBNdb to verify ISBN and add publisher, edition, and other enrichment. Patrons can confirm a match before submitting.',
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
            [
                'key' => 'catalog_owned_message',
                'value' => '<p><strong>Good news:</strong> this item is already in our catalog. Please place a hold in the catalog to get it as soon as it’s available.</p>',
                'label' => 'Catalog Owned Message',
                'type' => 'html',
                'group' => 'messaging',
                'description' => 'Shown when a patron confirms their item is already in the catalog. No request is created in this case.',
            ],

            // --- Syndetics book covers ---
            [
                'key' => 'syndetics_client',
                'value' => 'davia',
                'label' => 'Syndetics Client ID',
                'type' => 'string',
                'group' => 'syndetics',
                'description' => 'Your Syndetics client ID for book cover images (e.g. "davia").',
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

            // --- Patron PIN login ---
            [
                'key'         => 'patron_lookup_enabled',
                'value'       => '1',
                'label'       => 'Enable Patron PIN Login',
                'type'        => 'boolean',
                'group'       => 'patron',
                'description' => 'Allow patrons to sign in with their library card PIN at /my-requests to view and track their submitted requests.',
            ],

            // --- Polaris integration ---
            [
                'key' => 'polaris_barcode_check_enabled',
                'value' => '1',
                'label' => 'Enable Polaris Barcode Check',
                'type' => 'boolean',
                'group' => 'polaris',
                'description' => 'When enabled, Step 1 of the request form checks the patron barcode against Polaris before proceeding. If the barcode is not found, the form is stopped and the message below is shown.',
            ],
            [
                'key' => 'barcode_not_found_message',
                'value' => '<p>The card number you entered was not found. Please <a href="#">apply for a card online</a> or visit the library to register.</p>',
                'label' => 'Barcode Not Found Message',
                'type' => 'html',
                'group' => 'polaris',
                'description' => 'Shown on Step 1 of the request form when the patron\'s barcode is not found in Polaris. HTML is allowed — you can include links and your library\'s address.',
            ],

            // --- Email notifications ---
            [
                'key'         => 'notifications_enabled',
                'value'       => '1',
                'label'       => 'Enable Notifications',
                'type'        => 'boolean',
                'group'       => 'notifications',
                'description' => 'Master switch for all email notifications. Turn off to silence everything without changing individual settings.',
            ],
            // --- Request visibility / assignment ---
            [
                'key'         => 'requests_visibility_open_access',
                'value'       => '0',
                'label'       => 'Open Staff Access to Requests',
                'type'        => 'boolean',
                'group'       => 'staff',
                'description' => 'When enabled, all staff users can view all requests (SFP + ILL), regardless of selector groups.',
            ],
            [
                'key'         => 'requests_visibility_strict_groups',
                'value'       => '1',
                'label'       => 'Strict Selector Group Scoping',
                'type'        => 'boolean',
                'group'       => 'staff',
                'description' => 'When enabled (and Open Staff Access is off), selector users can only view SFP requests that match their selector group material types + audiences.',
            ],
            [
                'key'         => 'assignment_enabled',
                'value'       => '0',
                'label'       => 'Enable Request Assignment',
                'type'        => 'boolean',
                'group'       => 'staff',
                'description' => 'Allow staff to claim/reassign requests. When enabled, a status update will auto-claim an unassigned request.',
            ],
            [
                'key'         => 'ill_selector_group_id',
                'value'       => $illGroupId ? (string) $illGroupId : '',
                'label'       => 'ILL Access Group ID',
                'type'        => 'integer',
                'group'       => 'staff',
                'description' => 'Internal ID of the selector group that grants staff access to ILL requests. This is set automatically by the seeder.',
            ],
            [
                'key'         => 'staff_routing_enabled',
                'value'       => '1',
                'label'       => 'Enable Staff Routing Emails',
                'type'        => 'boolean',
                'group'       => 'notifications',
                'description' => 'Send an email to the selector group(s) matching the request\'s material type and audience when a new request is submitted. Configure routing addresses on each Group.',
            ],
            [
                'key'         => 'staff_routing_subject',
                'value'       => 'New Purchase Suggestion: {title}',
                'label'       => 'Staff Routing — Subject',
                'type'        => 'string',
                'group'       => 'notifications',
                'description' => 'Subject line for staff routing emails.',
                'tokens'      => json_encode(['{title}', '{author}', '{patron_name}', '{patron_first_name}', '{material_type}', '{audience}', '{status}', '{submitted_date}', '{request_url}']),
            ],
            [
                'key'         => 'staff_routing_template',
                'value'       => '',
                'label'       => 'Staff Routing — Email Body',
                'type'        => 'html',
                'group'       => 'notifications',
                'description' => 'HTML body for staff routing emails. Leave blank to use the built-in default.',
                'tokens'      => json_encode(['{title}', '{author}', '{patron_name}', '{patron_first_name}', '{material_type}', '{audience}', '{status}', '{submitted_date}', '{request_url}']),
            ],
            [
                'key'         => 'patron_status_notification_enabled',
                'value'       => '1',
                'label'       => 'Enable Patron Status Emails',
                'type'        => 'boolean',
                'group'       => 'notifications',
                'description' => 'Send an email to the patron when their request\'s status changes. Only fires for statuses that have "Notify Patron" checked. The patron must have an email on file.',
            ],
            [
                'key'         => 'patron_status_subject',
                'value'       => 'Update on your suggestion: {title}',
                'label'       => 'Patron Status — Subject',
                'type'        => 'string',
                'group'       => 'notifications',
                'description' => 'Subject line for patron status-change emails.',
                'tokens'      => json_encode(['{title}', '{author}', '{patron_name}', '{patron_first_name}', '{material_type}', '{audience}', '{status}', '{submitted_date}', '{request_url}']),
            ],
            [
                'key'         => 'patron_status_template',
                'value'       => '',
                'label'       => 'Patron Status — Email Body',
                'type'        => 'html',
                'group'       => 'notifications',
                'description' => 'HTML body for patron status-change emails. Leave blank to use the built-in default.',
                'tokens'      => json_encode(['{title}', '{author}', '{patron_name}', '{patron_first_name}', '{material_type}', '{audience}', '{status}', '{submitted_date}', '{request_url}']),
            ],

            // --- Auto-order exclusions (popular authors) ---
            [
                'key' => 'auto_order_author_exclusions',
                'value' => '',
                'label' => 'Auto-Order Author Exclusions',
                'type' => 'text',
                'group' => 'ordering',
                'description' => 'One author per line. If a submitted item has a future release date and the author matches this list, the patron will be shown an informational message instead of submitting a request.',
            ],
            [
                'key' => 'auto_order_author_exclusion_message',
                'value' => '<p><strong>Good news:</strong> the library automatically orders new releases from this author. Please check the catalog closer to the release date to place a hold.</p>',
                'label' => 'Auto-Order Author Exclusion Message',
                'type' => 'html',
                'group' => 'messaging',
                'description' => 'Shown when a patron submits a future (unreleased) title by an author on the auto-order exclusion list. No request is created in this case.',
            ],
        ];
    }

    public function run(): void
    {
        $illGroupId = self::ensureIllGroupExists();
        $settings = self::defaultSettings($illGroupId);

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );

            Cache::forget("setting:{$setting['key']}");
        }
    }
}
