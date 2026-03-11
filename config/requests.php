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

];
