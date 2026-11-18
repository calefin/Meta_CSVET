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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
    ],

    'govbr' => [
        'client_id' => env('GOVBR_CLIENT_ID'),
        'client_secret' => env('GOVBR_CLIENT_SECRET'),
        'mode' => env('GOVBR_MODE', 'staging'), // 'staging' or 'production'
        'cas_url' => env('GOVBR_MODE') === 'production' ? 'https://cas.assinatura.iti.br' : 'https://cas.staging.iti.br',
        'api_url' => env('GOVBR_MODE') === 'production' ? 'https://assinatura-api.assinatura.iti.br' : 'https://assinatura-api.staging.iti.br',
    ],

    'whatsapp' => [
        'phone' => env('WHATSAPP_PHONE', '5511977998476'), // Telefone comercial do Prof. Atilio
    ],

    // Avisos ao professor da seleção de alunos e das revisões sistemáticas (mensagens da dupla, etapas, OSF).
    'research' => [
        'professor_email' => env('RESEARCH_PROFESSOR_EMAIL', 'calefi@csvet.com.br'),
    ],

    'adsense' => [
        // ID do editor do Google AdSense. Vazio desliga o script em todas as páginas.
        'client' => env('ADSENSE_CLIENT', 'ca-pub-8311618945249138'),
    ],

];
