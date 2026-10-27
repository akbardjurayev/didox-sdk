<?php

return [
    'base_url' => env('DIDOX_BASE_URL', 'https://testapi3.didox.uz'),
    'partner_token' => env('DIDOX_PARTNER_TOKEN', ''),
    'user_token' => env('DIDOX_USER_TOKEN'),
    'locale' => env('DIDOX_LOCALE', 'ru'),
    'timeout' => (int) env('DIDOX_TIMEOUT', 30),
];
