@php
    $qdData = ['page' => $page, 'base' => url('/qaddaha'), 'code' => $code ?? null];
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0E1330">
    <title>{{ isset($title) ? $title.' — ' : '' }}قدّها؟</title>
    <script>window.QD = @json($qdData);</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lalezar&family=IBM+Plex+Sans+Arabic:wght@400;500;700&display=swap" rel="stylesheet">
    @vite(['resources/css/qaddaha.css', 'resources/js/qaddaha/main.js'])
</head>
<body class="qd {{ $page }}">
    <div id="app" aria-live="polite"><div class="boot">قدّها؟</div></div>
    <div class="toast" id="toast" role="status" aria-live="polite"></div>
    <div class="net" id="net" hidden>في مشكلة بالاتصال… نحاول</div>
    @yield('after')
</body>
</html>
