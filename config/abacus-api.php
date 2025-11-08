<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Abacus REST API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Abacus REST API integration
    |
    */

    'rest_api' => [
        'url'            => env('ABACUS_REST_API_URL', 'entity-api1-1.demo.abacus.ch'),
        'mandate'        => env('ABACUS_REST_API_MANDATE', '7777'),
        'client_id'      => env('ABACUS_REST_API_CLIENT_ID', ''),
        'client_secret'  => env('ABACUS_REST_API_CLIENT_SECRET', ''),
        'token_endpoint' => env('ABACUS_TOKEN_ENDPOINT', '/oauth/oauth2/v1/token'),
    ],

    /*
    |--------------------------------------------------------------------------
    | IDE Helper Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic IDE helper generation
    |
    */

    'ide_helper' => [
        'enabled'           => env('ABACUS_IDE_HELPER_ENABLED', true),
        'swagger_json_file' => env('ABACUS_SWAGGER_JSON_FILE', 'storage/app/swagger.json'),
        'output_file'       => env('ABACUS_IDE_HELPER_OUTPUT', '_ide_helper_abacus.php'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Models Namespace
    |--------------------------------------------------------------------------
    |
    | The namespace where your custom Abacus models are located
    |
    */

    'models_namespace' => env('ABACUS_MODELS_NAMESPACE', 'App\\Models\\Abacus'),

    /*
    |--------------------------------------------------------------------------
    | Reports Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Abacus AbaReports (non-OData endpoints)
    |
    */

    'reports' => [
        'poll_interval'     => env('ABACUS_REPORTS_POLL_INTERVAL', 200000), /* Microseconds (0.2 seconds) */
        'max_poll_attempts' => env('ABACUS_REPORTS_MAX_POLL_ATTEMPTS', 150),
        'reports_namespace' => env('ABACUS_REPORTS_NAMESPACE', 'App\\Services\\Abacus\\Reports'),
    ],

];