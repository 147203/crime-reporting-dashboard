<?php

declare(strict_types=1);

return [

    'login_max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),

    'login_lockout_minutes' => (int) env('LOGIN_LOCKOUT_MINUTES', 15),

    'password_min_length' => 12,

    // Sign-in, sign-out and demo sign-in submissions per minute from one address (page loads are not counted).
    'auth_actions_per_minute' => (int) env('AUTH_ACTIONS_PER_MINUTE', 20),

    // Set only by the Playwright config for one run; used by e2e:prepare and nothing else.
    'e2e_password' => env('E2E_PASSWORD'),

];
