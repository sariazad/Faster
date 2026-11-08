@php use App\Support\AlefYaStrings as S; @endphp
<header class="game-header">
    <div class="bar">
        <div class="head-actions">
            @isset($menu)
                <button class="icon-btn dark" id="menuBtn" aria-label="{{ S::get('play.menu') }}"><i class="fa-solid fa-bars"></i></button>
            @else
                <a class="icon-btn dark" href="{{ route('alefya.home') }}" aria-label="{{ S::get('app.back_home') }}"><i class="fa-solid fa-house"></i></a>
            @endisset
            <button class="icon-btn dark" id="musicBtn" aria-label="{{ S::get('home.music') }}"><i class="fa-solid fa-music"></i></button>
        </div>
        <div style="display:flex; align-items:center; gap:10px">
            <span class="mode-pill">{{ $pill }}</span>
            @include('alefya.partials.logo', ['size' => 'xs'])
        </div>
    </div>
    <div class="checker"></div>
</header>
