@php
    use App\Support\AlefYaStrings as S;
    $page ??= 'home';
    $letters = \App\Support\AlefYaLetters::ALL;
    // Edge pattern: a strip of hexagons with letters on each side of the screen.
    $strip = function (int $seed) use ($letters) {
        mt_srand($seed);
        $out = '';
        for ($r = 0; $r < 14; $r++) {
            for ($c = 0; $c < 3; $c++) {
                $x = 40 + $c * 70 + ($r % 2 ? 35 : 0); $y = 40 + $r * 61;
                $pts = [];
                foreach ([-90, -30, 30, 90, 150, 210] as $d) { $a = deg2rad($d); $pts[] = round($x + 38 * cos($a), 1).','.round($y + 38 * sin($a), 1); }
                $out .= '<polygon points="'.implode(' ', $pts).'" fill="none" stroke="#fff" stroke-width="3"/>';
                $out .= '<text x="'.$x.'" y="'.($y + 2).'" fill="#fff" font-size="30" font-weight="800" text-anchor="middle" dominant-baseline="central" font-family="Tajawal">'.$letters[mt_rand(0, 27)].'</text>';
            }
        }
        mt_srand();
        return $out;
    };
    $ayData = [
        'page' => $page,
        'strings' => S::all(),
        'account' => $account?->code,
        'base' => url('/alefya'),
        'home' => route('home'),
        'solo' => config('alefya.solo'),
    ];
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#4A1C75">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' — ' : '' }}{{ S::get('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <script>
        window.AY = @json($ayData);
    </script>
    @vite(['resources/css/alefya.css', 'resources/js/alefya/main.js'])
</head>
<body class="ay" data-page="{{ $page }}">
    <div class="ay-pattern" aria-hidden="true">
        <svg class="left" viewBox="0 0 250 880" preserveAspectRatio="xMinYMid slice">{!! $strip(3) !!}</svg>
        <svg class="right" viewBox="0 0 250 880" preserveAspectRatio="xMaxYMid slice">{!! $strip(11) !!}</svg>
    </div>

    {{ $slot ?? '' }}
    @yield('content')

    <div class="toast" id="toast" role="status" aria-live="polite"></div>
    <div class="ay-version">{{ S::get('app.version') }}</div>
</body>
</html>
