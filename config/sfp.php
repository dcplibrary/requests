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
    'route_prefix' => env('SFP_ROUTE_PREFIX', 'sfp'),

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    | Middleware applied to all package routes. 'web' is required for
    | sessions and CSRF protection.
    */
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Auth Guard
    |--------------------------------------------------------------------------
    | The package registers an 'sfp' auth guard backed by the sfp_users table.
    */
    'guard' => 'sfp',

    /*
    |--------------------------------------------------------------------------
    | Entra SSO Redirect URI
    |--------------------------------------------------------------------------
    | The callback URL registered in your Azure / Entra application registration.
    | Must match exactly what is configured in the Entra portal.
    | Defaults to: APP_URL + /sfp/auth/callback
    |
    | In the Entra portal add this as a "Web" redirect URI:
    |   https://yourdomain.org/sfp/auth/callback
    */
    'entra_redirect_uri' => env('SFP_ENTRA_REDIRECT_URI', env('APP_URL') . '/sfp/auth/callback'),

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
        'connection' => env('SFP_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'redis')),
        'name'       => env('SFP_QUEUE_NAME', 'default'),
    ],

];
