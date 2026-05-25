<?php

return [
    'paths'             => ['api/*'],
    'allowed_methods'   => ['*'],
   // 'allowed_origins'   => ['http://localhost:8080'],
   'allowed_origins'   => ['http://localservice.ds2.eleueleo.com'],
    'allowed_headers'   => ['*'],
    'exposed_headers'  => [],
    'max_age'          => 0,
    'supports_credentials' => true,
];