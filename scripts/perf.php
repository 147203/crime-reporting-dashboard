<?php

declare(strict_types=1);

/*
 * Response-time measurement for NFR-04 (docs/06 section 5). Signs in once, then for each page sends 10 warm-up requests
 * and 100 timed requests, and prints mean, p50 and p95 in milliseconds as a Markdown table for TEST_LOG.
 *
 *   PERF_PASSWORD=... php scripts/perf.php --base=http://127.0.0.1:8123 --email=analyst@e2e.test [--runs=100]
 *
 * The password is read from the environment and never printed. Only test your own local instance.
 */

$options = getopt('', ['base:', 'email:', 'runs::', 'warmup::']);
$base = rtrim((string) ($options['base'] ?? 'http://127.0.0.1:8123'), '/');
$email = (string) ($options['email'] ?? '');
$runs = (int) ($options['runs'] ?? 100);
$warmup = (int) ($options['warmup'] ?? 10);
$password = (string) getenv('PERF_PASSWORD');

if ($email === '' || $password === '') {
    fwrite(STDERR, "Usage: PERF_PASSWORD=... php scripts/perf.php --base=URL --email=EMAIL\n");
    exit(1);
}

$cookies = tempnam(sys_get_temp_dir(), 'perf');

function request(string $url, string $cookies, ?array $post = null): array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookies,
        CURLOPT_COOKIEFILE => $cookies,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 60,
    ]);
    if ($post !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = (string) curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $seconds = (float) curl_getinfo($curl, CURLINFO_TOTAL_TIME);
    curl_close($curl);

    return [$status, $body, $seconds * 1000];
}

[, $loginPage] = request($base.'/login', $cookies);
if (preg_match('/name="_token" value="([^"]+)"/', $loginPage, $match) !== 1) {
    fwrite(STDERR, "Could not read the CSRF token from the login page.\n");
    exit(1);
}
[$status] = request($base.'/login', $cookies, ['_token' => $match[1], 'email' => $email, 'password' => $password]);
[$check] = request($base.'/', $cookies);
if ($status !== 302 || $check !== 200) {
    fwrite(STDERR, "Sign-in failed (login {$status}, dashboard {$check}).\n");
    exit(1);
}

$year = date('Y');
$pages = [
    'Dashboard, last 12 months (default)' => '/',
    'Dashboard, last 24 months' => '/?from='.date('Y-m-01', strtotime('-23 months')).'&to='.date('Y-m-d'),
    'Dashboard, one category' => '/?category_id=1',
    'Incident list, page 1' => '/incidents',
    'Incident list, filtered and sorted' => '/incidents?category_id=1&sort=occurred_at&direction=asc',
    'Reports page' => '/reports',
];

$percentile = function (array $values, float $p): float {
    sort($values);

    return $values[max(0, (int) ceil($p * count($values)) - 1)];
};

echo "| Page or action | Runs | Mean ms | p50 ms | p95 ms |\n|---|---|---|---|---|\n";
foreach ($pages as $label => $path) {
    for ($i = 0; $i < $warmup; $i++) {
        request($base.$path, $cookies);
    }
    $times = [];
    for ($i = 0; $i < $runs; $i++) {
        [$code, , $ms] = request($base.$path, $cookies);
        if ($code !== 200) {
            fwrite(STDERR, "{$label}: HTTP {$code}\n");
            exit(1);
        }
        $times[] = $ms;
    }
    printf("| %s | %d | %.0f | %.0f | %.0f |\n", $label, $runs, array_sum($times) / count($times), $percentile($times, 0.5), $percentile($times, 0.95));
}

unlink($cookies);
