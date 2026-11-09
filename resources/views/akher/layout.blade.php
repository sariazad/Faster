@php
    use App\Support\AkherStrings as S;
    $page ??= 'home';
    $akData = ['page' => $page, 'strings' => S::all(), 'base' => url('/akher'), 'code' => $code ?? null];
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#120d2b">
    <title>{{ isset($title) ? $title.' — ' : '' }}{{ S::get('app.name') }}</title>
    <script>
        // Apply the saved theme before paint (no flash).
        try { const t = localStorage.getItem('akher.theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
        window.AK = @json($akData);
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Reem+Kufi:wght@500;700&family=Tajawal:wght@300;500;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/akher.css', 'resources/js/akher/main.js'])
</head>
<body class="ak" data-page="{{ $page }}">
    <div class="ak-top" aria-hidden="true"></div>
    @yield('content')
    <div class="toast" id="toast" role="status" aria-live="polite"></div>
    <div class="net" id="net" hidden>{{ S::get('app.offline') }}</div>
    <div class="version">{{ S::get('app.version') }}</div>
</body>
</html>
