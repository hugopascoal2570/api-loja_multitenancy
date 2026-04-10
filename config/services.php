<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    
    'mercadopago' => [
        'access_token' => env('MERCADO_PAGO_ACCESS_TOKEN'),
        'public_key' => env('MERCADO_PAGO_PUBLIC_KEY'),
        'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
        'enforce_signature' => env('MERCADOPAGO_ENFORCE_SIGNATURE', true),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    'notifications' => [
        'admin_email' => env('ADMIN_NOTIFICATION_EMAIL'),
    ],

    'melhor_envio' => [
        'token' => env('MELHOR_ENVIO_TOKEN'),
        'sandbox' => env('MELHOR_ENVIO_SANDBOX', true),
        'app_secret' => env('MELHOR_ENVIO_APP_SECRET', ''),
        'phone' => env('MELHOR_ENVIO_PHONE', ''),
        'email' => env('MELHOR_ENVIO_EMAIL', ''),
        'document' => env('MELHOR_ENVIO_DOCUMENT', ''),
        'company_document' => env('MELHOR_ENVIO_COMPANY_DOCUMENT', ''),
        'state_register' => env('MELHOR_ENVIO_STATE_REGISTER', ''),
        'address' => env('MELHOR_ENVIO_ADDRESS', ''),
        'complement' => env('MELHOR_ENVIO_COMPLEMENT', ''),
        'number' => env('MELHOR_ENVIO_NUMBER', ''),
        'district' => env('MELHOR_ENVIO_DISTRICT', ''),
        'city' => env('MELHOR_ENVIO_CITY', ''),
        'state_abbr' => env('MELHOR_ENVIO_STATE_ABBR', ''),
    ],

];
