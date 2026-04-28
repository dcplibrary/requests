<?php

namespace Dcplibrary\Requests\Database\Seeders;

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
                'label'       => 'SFP Request Limit Count',
                'type'        => 'integer',
                'group'       => 'request_limits',
                'description' => 'Maximum number of SFP requests a patron can submit within the limit window. Leave blank for unlimited.',
            ],
            [
                'key'         => 'sfp_limit_window_type',
                'value'       => 'rolling',
                'label'       => 'SFP Limit Window Type',
                'type'        => 'text',
                'group'       => 'request_limits',
                'description' => 'How the submission limit window is measured: Rolling counts requests within a sliding day window; Calendar Month resets on a fixed day each month; Calendar Week resets every Monday.',
            ],
            [
                'key'         => 'sfp_limit_window_days',
                'value'       => '30',
                'label'       => 'SFP Rolling Window Length',
                'type'        => 'integer',
                'group'       => 'request_limits',
                'description' => 'How many days the SFP rolling window spans (e.g. 30 means a patron may submit up to the limit count within any 30-day period).',
            ],
            [
                'key'         => 'sfp_limit_calendar_reset_day',
                'value'       => '1',
                'label'       => 'SFP Monthly Reset Day',
                'type'        => 'integer',
                'group'       => 'request_limits',
                'description' => 'Day of the month the submission counter resets when using the Calendar Month window type. Must be between 1 and 28.',
            ],

            // --- ILL request limits (separate from SFP) ---
            [
                'key'         => 'ill_limit_count',
                'value'       => '',
                'label'       => 'ILL Request Limit Count',
                'type'        => 'integer',
                'group'       => 'request_limits',
                'description' => 'Maximum number of ILL requests a patron can submit within the limit window. Leave blank for unlimited.',
            ],
            [
                'key'         => 'ill_limit_window_type',
                'value'       => 'rolling',
                'label'       => 'ILL Limit Window Type',
                'type'        => 'text',
                'group'       => 'request_limits',
                'description' => 'How the ILL submission limit window is measured (same options as SFP).',
            ],
            [
                'key'         => 'ill_limit_window_days',
                'value'       => '30',
                'label'       => 'ILL Rolling Window Length',
                'type'        => 'integer',
                'group'       => 'request_limits',
                'description' => 'Days the ILL rolling window spans. Used only when ILL Limit Window Type is Rolling.',
            ],
            [
                'key'         => 'ill_limit_calendar_reset_day',
                'value'       => '1',
                'label'       => 'ILL Monthly Reset Day',
                'type'        => 'integer',
                'group'       => 'request_limits',
                'description' => 'Day of the month the ILL counter resets when using Calendar Month. Must be between 1 and 28.',
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
                'value' => '<p><strong>Good news:</strong> this item is already in our catalog. Please place a hold in the catalog to get it as soon as it\'s available.</p>',
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
                'value'       => '1',
                'label'       => 'Enable Request Assignment',
                'type'        => 'boolean',
                'group'       => 'staff',
                'description' => 'Allow staff to claim/reassign requests. When enabled, viewing an unassigned request auto-claims it. Rerouting changes field values and unassigns so the next group can claim.',
            ],
            [
                'key'         => 'ill_selector_group_id',
                'value'       => $illGroupId ? (string) $illGroupId : '',
                'label'       => 'ILL Access Group',
                'type'        => 'integer',
                'group'       => 'staff',
                'description' => 'Selector group whose members may view and work the ILL queue (in addition to admins). Leave unset to hide ILL from non-admins. The seeder picks a default group when first installed.',
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
                'description' => 'Default subject for new-request emails (groups without a staff template) and for assignee/workflow emails.',
                'tokens'      => json_encode(['{title}', '{author}', '{isbn}', '{patron_name}', '{patron_first_name}', '{material_type}', '{status}', '{submitted_date}']),
            ],
            [
                'key'         => 'staff_routing_template',
                'value'       => '',
                'label'       => 'Staff Routing — Email Body',
                'type'        => 'html',
                'group'       => 'notifications',
                'description' => 'Default HTML for new-request emails when a selector group has no staff template (body); assignee/workflow emails use this too. Per-group templates: Notifications → + Staff template.',
                'tokens'      => json_encode(['{title}', '{author}', '{isbn}', '{patron_name}', '{patron_first_name}', '{material_type}', '{audience}', '{status}', '{status_name}', '{action_buttons}', '{convert_to_ill_link}', '{convert_to_ill_url}', '{submitted_date}', '{request_url}']),
            ],
            [
                'key'         => 'staff_routing_title',
                'value'       => 'Staff routing',
                'label'       => 'Staff Routing — Title',
                'type'        => 'string',
                'group'       => 'notifications',
                'description' => 'Label for the default staff routing row in the Emails list.',
            ],
            [
                'key'         => 'staff_routing_material_type_ids',
                'value'       => '[]',
                'label'       => 'Staff Routing — Material Types',
                'type'        => 'string',
                'group'       => 'notifications',
                'description' => 'JSON array of material type IDs this staff routing template applies to. Empty means all.',
            ],
            [
                'key'         => 'staff_routing_status_ids',
                'value'       => '[]',
                'label'       => 'Staff Routing — Statuses',
                'type'        => 'string',
                'group'       => 'notifications',
                'description' => 'JSON array of request status IDs this staff routing template applies to. Empty means all.',
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
                'tokens'      => json_encode(['{title}', '{author}', '{isbn}', '{patron_name}', '{patron_first_name}', '{material_type}', '{status}', '{submitted_date}']),
            ],
            [
                'key'         => 'patron_status_template',
                'value'       => '',
                'label'       => 'Patron Status — Email Body',
                'type'        => 'html',
                'group'       => 'notifications',
                'description' => 'HTML body for patron status-change emails. Leave blank to use the built-in default.',
                'tokens'      => json_encode(['{title}', '{author}', '{isbn}', '{patron_name}', '{patron_first_name}', '{material_type}', '{audience}', '{status}', '{submitted_date}', '{request_url}']),
            ],
            [
                'key'         => 'email_footer_text',
                'value'       => 'Please do not reply to this message. Replies will not be routed to or seen by library staff. If you have any comments, please contact us at your library.',
                'label'       => 'Email Footer Text',
                'type'        => 'html',
                'group'       => 'notifications',
                'description' => 'Text shown in the footer of every notification email sent to patrons and staff.',
            ],
            [
                'key'         => 'staff_email_show_header',
                'value'       => '1',
                'label'       => 'Staff Emails — Show Logo Header',
                'type'        => 'boolean',
                'group'       => 'notifications',
                'description' => 'Show the library logo at the top of staff routing, assignee, and workflow emails. Disable to send plain body-only emails to staff.',
            ],
            [
                'key'         => 'staff_email_show_footer',
                'value'       => '1',
                'label'       => 'Staff Emails — Show Footer',
                'type'        => 'boolean',
                'group'       => 'notifications',
                'description' => 'Show the footer text at the bottom of staff routing, assignee, and workflow emails.',
            ],
            [
                'key'         => 'email_preview_enabled',
                'value'       => '1',
                'label'       => 'Email Preview Enabled',
                'type'        => 'boolean',
                'group'       => 'notifications',
                'description' => 'When enabled, selectors will see a preview of the patron notification email before it is sent when updating a request status.',
            ],
            [
                'key'         => 'email_editing_enabled',
                'value'       => '0',
                'label'       => 'Email Editing Enabled',
                'type'        => 'boolean',
                'group'       => 'notifications',
                'description' => 'When enabled, selectors can edit the email subject, body, and recipients in the preview modal before sending.',
            ],

            // --- Request limit messages ---
            [
                'key'         => 'limit_reached_message',
                'value'       => 'You have reached the limit of {limit} suggestions {period}.',
                'label'       => 'Limit Reached Message',
                'type'        => 'string',
                'group'       => 'messaging',
                'description' => 'Shown when a patron hits their request limit. Tokens: {limit}, {period}',
                'tokens'      => json_encode(['{limit}', '{period}']),
            ],
            [
                'key'         => 'limit_until_message',
                'value'       => 'You won\'t be able to submit another suggestion until {until}.',
                'label'       => 'Limit Until Message',
                'type'        => 'string',
                'group'       => 'messaging',
                'description' => 'Shown below the limit message when a reset date is known. Token: {until}',
                'tokens'      => json_encode(['{until}']),
            ],
            [
                'key'         => 'ill_limit_reached_message',
                'value'       => 'You have reached the limit of {limit} ILL requests {period}.',
                'label'       => 'ILL Limit Reached Message',
                'type'        => 'string',
                'group'       => 'messaging',
                'description' => 'Shown when a patron hits their ILL request limit. Tokens: {limit}, {period}',
                'tokens'      => json_encode(['{limit}', '{period}']),
            ],
            [
                'key'         => 'ill_limit_until_message',
                'value'       => 'You won\'t be able to submit another ILL request until {until}.',
                'label'       => 'ILL Limit Until Message',
                'type'        => 'string',
                'group'       => 'messaging',
                'description' => 'Shown below the ILL limit message when a reset date is known. Token: {until}',
                'tokens'      => json_encode(['{until}']),
            ],

            // --- Backup ---
            [
                'key'         => 'backup_retention_days',
                'value'       => '30',
                'label'       => 'Backup Retention',
                'type'        => 'integer',
                'group'       => 'backup',
                'description' => 'How many days to keep server-side backup files. Files older than this are removed when pruning runs.',
            ],
            [
                'key'         => 'backup_schedule_enabled',
                'value'       => '0',
                'label'       => 'Backup Schedule Enabled',
                'type'        => 'boolean',
                'group'       => 'backup',
                'description' => 'When true, the package registers a Laravel scheduler task using the other backup_schedule_* settings.',
            ],
            [
                'key'         => 'backup_schedule_cron',
                'value'       => '0 2 * * *',
                'label'       => 'Backup Schedule Cron',
                'type'        => 'string',
                'group'       => 'backup',
                'description' => 'Five-field cron expression for automated backups (server local time / app timezone).',
            ],
            [
                'key'         => 'backup_schedule_include_config',
                'value'       => '1',
                'label'       => 'Scheduled Backup: Configuration',
                'type'        => 'boolean',
                'group'       => 'backup',
                'description' => 'Include JSON configuration export in scheduled backups.',
            ],
            [
                'key'         => 'backup_schedule_include_db',
                'value'       => '1',
                'label'       => 'Scheduled Backup: Database',
                'type'        => 'boolean',
                'group'       => 'backup',
                'description' => 'Include database JSON dump in scheduled backups.',
            ],
            [
                'key'         => 'backup_schedule_include_storage',
                'value'       => '0',
                'label'       => 'Scheduled Backup: Storage Zip',
                'type'        => 'boolean',
                'group'       => 'backup',
                'description' => 'Include full storage zip in scheduled backups (can be large).',
            ],
            [
                'key'         => 'backup_schedule_prune',
                'value'       => '1',
                'label'       => 'Scheduled Backup: Prune',
                'type'        => 'boolean',
                'group'       => 'backup',
                'description' => 'After each scheduled backup, delete files older than backup retention.',
            ],
            [
                'key'         => 'backup_schedule_path',
                'value'       => '',
                'label'       => 'Scheduled Backup Output Path',
                'type'        => 'string',
                'group'       => 'backup',
                'description' => 'Optional absolute path for backup files. Empty uses storage/app/requests-backups.',
            ],

            // --- External lookup links ---
            [
                'key'         => 'library_website_url',
                'value'       => '',
                'label'       => 'Library Website URL',
                'type'        => 'string',
                'group'       => 'external_links',
                'description' => 'URL for the "Back to DCPL Website" link shown on patron-facing forms. Leave blank to hide the link.',
            ],
            [
                'key'         => 'sfp_isbn_lookup_url',
                'value'       => 'https://www.amazon.com/s?k=ISBN+{isbn}',
                'label'       => 'SFP ISBN Lookup URL',
                'type'        => 'string',
                'group'       => 'external_links',
                'description' => 'URL template for looking up SFP items by ISBN (e.g. Amazon). Use {isbn} as a placeholder.',
                'tokens'      => json_encode(['{isbn}']),
            ],
            [
                'key'         => 'ill_isbn_lookup_url',
                'value'       => 'https://www.worldcat.org/isbn/{isbn}',
                'label'       => 'ILL ISBN Lookup URL',
                'type'        => 'string',
                'group'       => 'external_links',
                'description' => 'URL template for looking up ILL items by ISBN (e.g. WorldCat). Use {isbn} as a placeholder.',
                'tokens'      => json_encode(['{isbn}']),
            ],
            [
                'key'         => 'polaris_leap_url',
                'value'       => 'https://catalog.dcplibrary.org/leapwebapp/staff/default#patrons/{PatronID}/record',
                'label'       => 'Polaris Leap Patron URL',
                'type'        => 'string',
                'group'       => 'external_links',
                'description' => 'URL template for viewing a patron in Polaris Leap. Use {PatronID} as a placeholder.',
                'tokens'      => json_encode(['{PatronID}']),
            ],

            // --- Form titles (patron-facing) ---
            [
                'key'         => 'sfp_form_title',
                'value'       => 'Suggest a Purchase',
                'label'       => 'SFP Form Title',
                'type'        => 'string',
                'group'       => 'forms',
                'description' => 'Heading shown at the top of the Suggest a Purchase patron form.',
            ],
            [
                'key'         => 'ill_form_title',
                'value'       => 'Request Interlibrary Loan',
                'label'       => 'ILL Form Title',
                'type'        => 'string',
                'group'       => 'forms',
                'description' => 'Heading shown at the top of the Interlibrary Loan patron form.',
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
            $exists = DB::table('settings')->where('key', $setting['key'])->exists();

            if (! $exists) {
                // New key — insert with the seeded default value.
                DB::table('settings')->insert(array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            } else {
                // Existing key — sync metadata (label, description, group, type, tokens)
                // but NEVER overwrite the admin-configured value.
                DB::table('settings')
                    ->where('key', $setting['key'])
                    ->update([
                        'label'       => $setting['label']       ?? null,
                        'type'        => $setting['type']        ?? 'string',
                        'group'       => $setting['group']       ?? 'general',
                        'description' => $setting['description'] ?? null,
                        'tokens'      => $setting['tokens']      ?? null,
                        'updated_at'  => now(),
                    ]);
            }

            Cache::forget("setting:{$setting['key']}");
        }
    }
}
