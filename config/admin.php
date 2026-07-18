<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin dashboard credentials
    |--------------------------------------------------------------------------
    | Single universal admin login, read from the environment. Set ADMIN_EMAIL
    | and ADMIN_PASSWORD in the .env file.
    */
    'email'    => env('ADMIN_EMAIL'),
    'password' => env('ADMIN_PASSWORD'),
];
