<?php 

return [
    'paths' => ['api', 'api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://clochic.com.br',
        'https://www.clochic.com.br',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['cart-token'],

    'max_age' => 86400, // Cache preflight por 24 horas

    'supports_credentials' => true,
];
