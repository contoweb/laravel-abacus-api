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
        'url' => env('ABACUS_REST_API_URL', 'entity-api1-1.demo.abacus.ch'),
        'mandate' => env('ABACUS_REST_API_MANDATE', '7777'),
        'client_id' => env('ABACUS_REST_API_CLIENT_ID', ''),
        'client_secret' => env('ABACUS_REST_API_CLIENT_SECRET', ''),
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
        'enabled' => env('ABACUS_IDE_HELPER_ENABLED', true),
        'swagger_url' => env(
            'ABACUS_SWAGGER_URL',
            'https://apihub.abacus.ch/VAADIN/dynamic/resource/3/6ec89d2f-56c8-4dac-b64b-124ae63ebfe4/swagger.json'
        ),
        'output_file' => env('ABACUS_IDE_HELPER_OUTPUT', '_ide_helper_abacus.php'),
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

];