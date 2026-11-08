@extends('alefya.layout', ['page' => 'solo', 'title' => \App\Support\AlefYaStrings::get('solo.title')])
@php use App\Support\AlefYaStrings as S; @endphp

@section('content')
<main class="ay-page" id="soloIntro">
    <div class="glass">
        <h1 class="page-title">{{ S::get('solo.title') }}</h1>
        <div class="solo-preview" aria-hidden="true">
            <i class="fa-solid fa-arrow-right"></i>
            <svg id="soloPreview" viewBox="0 0 220 120"></svg>
            <i class="fa-solid fa-arrow-left"></i>
        </div>
        <p class="muted" style="line-height:1.8">{{ S::get('solo.intro') }}</p>
        <div class="btn-stack" style="margin-top: 20px">
            <button class="btn btn-yellow" id="soloStart"><i class="fa-solid fa-play"></i> {{ S::get('solo.start') }}</button>
            <a class="btn btn-white" href="{{ route('alefya.home') }}">{{ S::get('app.back_home') }}</a>
        </div>
    </div>
</main>

<div id="soloGame" hidden>
@include('alefya.partials.game-header', ['pill' => '⏱ '.S::get('solo.title')])
<main class="solo-wrap">
    <div class="solo-board" id="soloBoard"></div>
    <div class="solo-side">
        <div class="timer" id="timer">
            <svg viewBox="0 0 120 120" width="150" height="150">
                <circle cx="60" cy="60" r="52" fill="#fff3dc" stroke="rgba(18,13,43,.08)" stroke-width="10"/>
                <circle id="timerRing" cx="60" cy="60" r="52" fill="none" stroke="#ffaa33" stroke-width="10" stroke-linecap="round" stroke-dasharray="326.7" stroke-dashoffset="0"/>
            </svg>
            <div class="num" id="timerNum">30</div>
        </div>
        <div class="counter good">{{ S::get('solo.correct') }}<b id="goodCount">0</b></div>
        <div class="counter bad">{{ S::get('solo.wrong') }}<b id="badCount">0</b></div>
    </div>
</main>
</div>

<div class="modal" id="soloQ" role="dialog" aria-modal="true" aria-labelledby="soloQText">
    <div class="glass">
        <div class="q-box" style="margin-top: 30px">
            <div class="q-hex" id="soloQHex"></div>
            <div class="q-text" id="soloQText"></div>
        </div>
        <div class="options" id="soloOptions"></div>
    </div>
</div>

<div class="modal" id="soloEnd" role="dialog" aria-modal="true" aria-labelledby="soloEndTitle">
    <div class="glass">
        <h2 id="soloEndTitle" class="page-title"></h2>
        <p class="muted" id="soloEndStats"></p>
        <p class="small" id="soloSaved" style="color: var(--cyan)"></p>
        <div class="btn-stack" style="margin-top: 16px">
            <button class="btn btn-yellow" id="soloRetry"><i class="fa-solid fa-rotate-right"></i> {{ S::get('solo.retry') }}</button>
            <a class="btn btn-gold" href="{{ route('alefya.page', 'leaderboard') }}"><i class="fa-solid fa-trophy"></i> {{ S::get('home.leaderboard') }}</a>
            <a class="btn btn-white" href="{{ route('alefya.home') }}">{{ S::get('solo.home') }}</a>
        </div>
    </div>
</div>
@endsection
