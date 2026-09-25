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

    'ged' => [
        'ouvidoria_url' => env('GED_OUVIDORIA_URL', 'https://prdmaissdoc.fgmaiss.com.br/ouvidoria'),
        'timeout' => (int) env('GED_OUVIDORIA_TIMEOUT', 20),
        // API oficial de integração do MAISSDoc (/api/portal/v1). Quando a URL
        // e o token estão configurados, envio/consulta/anexos usam JSON em vez
        // do scraping do formulário público.
        'api_url' => env('GED_API_URL', 'https://prdmaissdoc.fgmaiss.com.br/api/portal/v1'),
        'api_token' => env('GED_API_TOKEN'),
    ],

];
