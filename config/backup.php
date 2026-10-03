<?php

declare(strict_types=1);

// Database backup and restore (NFR-06). Paths default to XAMPP's client tools when present.
return [

    'dump_binary' => env('DB_DUMP_BINARY', is_file('/opt/lampp/bin/mysqldump') ? '/opt/lampp/bin/mysqldump' : 'mysqldump'),

    'client_binary' => env('DB_CLIENT_BINARY', is_file('/opt/lampp/bin/mysql') ? '/opt/lampp/bin/mysql' : 'mysql'),

    'directory' => storage_path('app/private/backups'),

];
