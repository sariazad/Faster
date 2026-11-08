@extends('alefya.layout', ['page' => 'home'])
@php use App\Support\AlefYaStrings as S; @endphp

@section('content')
<main class="ay-page">
    <div class="glass">
        <div class="corner left">
            <button class="icon-btn" data-open="idModal" aria-label="{{ S::get('home.login_id') }}" title="{{ S::get('home.login_id') }}"><i class="fa-solid fa-key"></i></button>
            <a class="icon-btn" href="{{ route('alefya.page', 'how') }}" aria-label="{{ S::get('home.how') }}" title="{{ S::get('home.how') }}"><i class="fa-solid fa-file-lines"></i></a>
            <button class="icon-btn" id="musicBtn" aria-label="{{ S::get('home.music') }}" title="{{ S::get('home.music') }}"><i class="fa-solid fa-music"></i></button>
        </div>
        <div class="corner right">
            <button class="icon-btn" id="bellBtn" aria-label="{{ S::get('home.notifications') }}" title="{{ S::get('home.notifications') }}">
                <i class="fa-solid fa-bell"></i><span class="count-badge" id="bellCount" hidden>0</span>
            </button>
        </div>

        <div style="margin-top: 34px">@include('alefya.partials.logo')</div>
        <div class="intro-box">{{ S::get('app.intro') }}</div>

        <nav class="btn-stack" aria-label="القائمة الرئيسية">
            <a class="btn btn-yellow" href="{{ route('alefya.page', 'play') }}"><i class="fa-solid fa-play"></i> {{ S::get('home.start') }}</a>
            <a class="btn btn-yellow" href="{{ route('alefya.page', 'solo') }}">
                <i class="fa-solid fa-stopwatch-20"></i> {{ S::get('home.solo') }}
                <span class="badge-new">{{ S::get('home.new_badge') }}</span>
            </a>
            <a class="btn btn-cyan" href="{{ route('alefya.page', 'customize') }}"><i class="fa-solid fa-sliders"></i> {{ S::get('home.customize') }}</a>
            <a class="btn btn-red" href="{{ route('alefya.portal') }}"><i class="fa-solid fa-mobile-screen"></i> {{ S::get('home.portal') }}</a>
            <a class="btn btn-gold" href="{{ route('alefya.page', 'leaderboard') }}"><i class="fa-solid fa-trophy"></i> {{ S::get('home.leaderboard') }}</a>
            <a class="btn btn-white" href="{{ route('alefya.page', 'about') }}"><i class="fa-solid fa-circle-info"></i> {{ S::get('home.about') }}</a>
        </nav>

        @include('alefya.partials.footer')
    </div>
</main>

<div class="modal" id="idModal" role="dialog" aria-modal="true" aria-labelledby="idTitle">
    <div class="glass">
        <button class="modal-close" data-close aria-label="{{ S::get('app.close') }}"><i class="fa-solid fa-xmark"></i></button>
        <h2 id="idTitle"><i class="fa-solid fa-key"></i> {{ S::get('home.your_id') }}</h2>
        @if ($account)
            <div class="code-show" id="myCode">{{ $account->code }}</div>
            <div class="btn-stack">
                <button class="btn btn-cyan" data-copy="{{ $account->code }}"><i class="fa-solid fa-copy"></i> {{ S::get('app.copy') }}</button>
                <button class="btn btn-white" id="logoutBtn"><i class="fa-solid fa-right-from-bracket"></i> {{ S::get('home.logout') }}</button>
            </div>
        @else
            <p class="muted">{{ S::get('home.not_logged') }}</p>
            <a class="btn btn-yellow" href="{{ route('alefya.page', 'login') }}">{{ S::get('home.go_login') }}</a>
        @endif
    </div>
</div>

@include('alefya.partials.notifications')
@endsection
