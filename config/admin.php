<?php

declare(strict_types=1);

return [

    // No defaults: the first administrator is created only from values the operator sets.
    'name' => env('ADMIN_NAME'),
    'email' => env('ADMIN_EMAIL'),
    'initial_password' => env('ADMIN_INITIAL_PASSWORD'),

];
