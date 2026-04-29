<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    | The URI prefix for all package routes. The patron form will be at
    | "/{prefix}" and the staff admin at "/{prefix}/staff/*".
    | Set to '' (empty string) to mount at the root.
    */
    'route_prefix' => env('REQUESTS_ROUTE_PREFIX', 'request'),

    /*
    |--------------------------------------------------------------------------
    | Route Middleware (Public)
    |--------------------------------------------------------------------------
    | Middleware applied to the public patron form route(s). 'web' is required
    | for sessions and CSRF protection.
    */
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Route Middleware (Staff)
    |--------------------------------------------------------------------------
    | Middleware applied to all staff routes under "/{prefix}/staff/*".
    | By default this uses the host application's authentication; the package
    | maps the authenticated user to a staff user record by email.
    |
    | Customize this in the host app to use a specific guard or additional gates,
    | e.g. ['web', 'auth:requests'] or ['web', 'auth', 'can:access-requests'].
    */
    'staff_middleware' => ['web', 'auth'],

    /*
    |--------------------------------------------------------------------------
    | Auth Guard (Optional)
    |--------------------------------------------------------------------------
    | If your host app authenticates staff using a dedicated guard,
    | set it here so package code can consistently reference that guard.
    | Leave empty to use Laravel's default guard.
    */
    'guard' => env('REQUESTS_GUARD', null),

    /*
    |--------------------------------------------------------------------------
    | ISBNdb API
    |--------------------------------------------------------------------------
    | API key for ISBNdb v2. Set ISBNDB_API_KEY in your .env file.
    | Docs: https://isbndb.com/isbndb-api-documentation-v2
    */
    'isbndb' => [
        'key' => env('ISBNDB_API_KEY', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    | Queue connection and name for package jobs (Polaris patron lookups).
    */
    'queue' => [
        'connection' => env('REQUESTS_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'redis')),
        'name'       => env('REQUESTS_QUEUE_NAME', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue notification mail
    |--------------------------------------------------------------------------
    | When true (default), staff/patron notification email from NotificationService
    | is dispatched to the queue so patron form submission and staff actions return
    | faster. Requires a running queue worker unless QUEUE_CONNECTION=sync.
    | Set to false to force synchronous Mail::send() (e.g. debugging).
    */
    'queue_notification_mail' => env('REQUESTS_QUEUE_NOTIFICATION_MAIL', true),

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Turnstile
    |--------------------------------------------------------------------------
    | CAPTCHA widget for the patron-facing forms (Step 1 / barcode check).
    | Get keys at Cloudflare Dashboard → Turnstile → Add site.
    | Set TURNSTILE_SITE_KEY and TURNSTILE_SECRET_KEY in .env.
    | Enable/disable the feature via Settings → Security → Enable CAPTCHA.
    */
    'turnstile' => [
        'site_key'   => env('TURNSTILE_SITE_KEY', ''),
        'secret_key' => env('TURNSTILE_SECRET_KEY', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Polaris PAPI
    |--------------------------------------------------------------------------
    | Staff authentication for Polaris PAPI (barcode check, patron lookup).
    | Set PAPI_DOMAIN, PAPI_STAFF, PAPI_PASSWORD in .env.
    */
    'polaris' => [
        'domain'   => env('PAPI_DOMAIN', ''),
        'staff'    => env('PAPI_STAFF', ''),
        'password' => env('PAPI_PASSWORD', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Laravel log pruning (scheduled)
    |--------------------------------------------------------------------------
    | The package registers a daily scheduler task that deletes *.log files under
    | storage/logs (or log_pruning.path) whose last modification time is older than
    | log_pruning.retention_days. Requires php artisan schedule:run every minute.
    |
    | Set log_pruning.enabled to false to disable, or override via .env.
    */
    'log_pruning' => [
        'enabled'        => env('REQUESTS_LOG_PRUNING_ENABLED', true),
        'retention_days' => (int) env('REQUESTS_LOG_RETENTION_DAYS', 14),
        'cron'           => env('REQUESTS_LOG_PRUNING_CRON', '15 3 * * *'),
        'path'           => env('REQUESTS_LOG_PRUNING_PATH') ?: null,
    ],

];
