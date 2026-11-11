<?php

declare(strict_types=1);

// Demo sign-in (D-54): one-click sign-in as a demo account per role, for presentations on a local machine only.
// Active only when DEMO_LOGIN=true, APP_ENV is local or testing, APP_URL is a loopback address and the request comes
// from this computer to a loopback host (Preflight::demoLoginAllowedFor); preflight refuses
// DEMO_LOGIN=true in production. Demo accounts get a random password nobody knows, so they cannot be used to sign in
// with a password, and they are never the accounts real users sign in with.
return [

    'enabled' => (bool) env('DEMO_LOGIN', false),

    'accounts' => [
        'admin' => ['name' => 'Demo Administrator', 'email' => 'demo-admin@demo.invalid'],
        'officer' => ['name' => 'Demo Reporting Officer', 'email' => 'demo-officer@demo.invalid'],
        'analyst' => ['name' => 'Demo Analyst', 'email' => 'demo-analyst@demo.invalid'],
    ],

];
