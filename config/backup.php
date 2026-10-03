<?php

declare(strict_types=1);

// Database backup and restore (NFR-06). Paths default to XAMPP's client tools where XAMPP installs them (Linux,
// Windows, macOS); otherwise the tools are looked up on the PATH. DB_DUMP_BINARY and DB_CLIENT_BINARY override both.
$xampp = fn (string $tool): ?string => collect([
    "/opt/lampp/bin/{$tool}",
    "C:\\xampp\\mysql\\bin\\{$tool}.exe",
    "/Applications/XAMPP/xamppfiles/bin/{$tool}",
])->first(fn (string $path): bool => is_file($path));

return [

    'dump_binary' => env('DB_DUMP_BINARY', $xampp('mysqldump') ?? 'mysqldump'),

    'client_binary' => env('DB_CLIENT_BINARY', $xampp('mysql') ?? 'mysql'),

    'directory' => storage_path('app/private/backups'),

];
