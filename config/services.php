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

    'brevo' => [
        'key' => env('BREVO_API_KEY'),
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

    // Inicio de sesión con Google (app móvil). Es el "Web client ID" de Google Cloud:
    // el mismo valor que VITE_GOOGLE_CLIENT_ID en localservice-movil.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    // Verificación de proveedores con el agente de IA (Gemini), aún no conectado.
    // Con puntuación >= umbral_aprobacion se aprobará sola; por debajo de umbral_revision se
    // rechazará la lectura y entre ambos irá al administrador.
    'verificacion' => [
        'umbral_aprobacion' => (float) env('VERIFICACION_UMBRAL_APROBACION', 85),
        'umbral_revision' => (float) env('VERIFICACION_UMBRAL_REVISION', 50),
        'gemini_api_key' => env('GEMINI_API_KEY'),
        'gemini_modelo' => env('GEMINI_MODELO'),
    ],

    // Notificaciones push (Firebase Cloud Messaging). Ver App\Services\PushFirebase.
    'firebase' => [
        'credentials_json' => env('FIREBASE_CREDENTIALS_JSON'),
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

];
