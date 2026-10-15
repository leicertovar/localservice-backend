<?php

return [
    'paths'             => ['api/*'],
    'allowed_methods'   => ['*'],
    'allowed_origins'   => [
        'http://localhost:8080',  // Frontend web
        'http://localhost:5173',  // App móvil: npm run dev (Vite)
        'http://localhost:8100',  // App móvil: ionic serve
        'https://localhost',      // App móvil: Capacitor Android
        'capacitor://localhost',  // App móvil: Capacitor iOS
    ],
    'allowed_headers'   => ['*'],
    'exposed_headers'  => [],
    'max_age'          => 0,
    'supports_credentials' => true,
];