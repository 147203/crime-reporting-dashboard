@props(['title' => null])
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ $title ? $title.' | ' : '' }}{{ config('app.name') }}</title>
    {{-- Runs before paint so the saved theme applies without a flash; allowed by the CSP nonce. --}}
    <script nonce="{{ Vite::cspNonce() }}">
        (function () {
            var t = null;
            try { t = localStorage.getItem('theme'); } catch (e) {}
            if (t !== 'light' && t !== 'dark') { t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>
    {{ $slot }}
</body>
</html>
