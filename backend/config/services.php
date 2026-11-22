<?php
return [
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        // GOOGLE_SECRET se acepta como alias por despliegues que ya lo usan en Render.
        // El operador ?: también cubre el caso de que la variable exista pero vacía.
        'client_secret' => env('GOOGLE_CLIENT_SECRET') ?: env('GOOGLE_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL', 'http://localhost:8000').'/auth/google/callback'),
        // Las URL de autoración/token sólo se sustituyen en pruebas aisladas.
        'auth_url' => env('GOOGLE_AUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth'),
        'token_url' => env('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token'),
        'jwks_url' => env('GOOGLE_JWKS_URL', 'https://www.googleapis.com/oauth2/v3/certs'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],
    'gmail' => [
        'client_id' => env('GMAIL_OAUTH_CLIENT_ID'),
        'client_secret' => env('GMAIL_OAUTH_CLIENT_SECRET'),
        'refresh_token' => env('GMAIL_OAUTH_REFRESH_TOKEN'),
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

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'url' => env('GROQ_URL', 'https://api.groq.com/openai/v1/chat/completions'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-20b'),
        'timeout' => max(1, min(4, (int) env('GROQ_TIMEOUT', 4))),
    ],
];
