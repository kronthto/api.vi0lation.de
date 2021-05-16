<?php

return [
    /*
     |--------------------------------------------------------------------------
     | Laravel CORS
     |--------------------------------------------------------------------------
     |
     | allowedOrigins, allowedHeaders and allowedMethods can be set to array('*')
     | to accept any value.
     |
     */
    'paths' => ['api/*'],
    'supports_credentials' => false,
    'allowed_origins' => ['*'],
    'allowed_headers' => ['*'],
    'allowed_methods' => ['GET', 'PUT', 'POST', 'DELETE'],
    'exposed_headers' => [],
    'max_age' => 86400,
];

