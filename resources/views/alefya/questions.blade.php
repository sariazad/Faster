@extends('alefya.layout', ['page' => 'questions', 'title' => \App\Support\AlefYaStrings::get('portal.title')])
@php use App\Support\AlefYaStrings as S; @endphp

@section('content')
<main class="ay-page">
    <div class="glass">
        <div class="portal-head">
            <button class="icon-btn round logout" id="logoutBtn" aria-label="{{ S::get('portal.logout') }}" title="{{ S::get('portal.logout') }}"><i class="fa-solid fa-right-from-bracket"></i></button>
            <button class="icon-btn round bell" id="bellBtn" aria-label="{{ S::get('home.notifications') }}"><i class="fa-solid fa-bell"></i><span class="count-badge" id="bellCount" hidden>0</span></button>
            @include('alefya.partials.logo', ['size' => 'sm'])
            <div class="page-title" style="font-size: 22px; margin: 4px 0 0">{{ S::get('portal.title') }}</div>
        </div>

        <div class="portal-q" id="pq" aria-live="polite">
            <div class="hand"><i class="fa-solid fa-hand-pointer"></i></div>
            <div class="muted">{{ S::get('portal.pick') }}</div>
        </div>

        <div id="pControls" hidden>
            <div class="two-col">
                <button class="btn btn-cyan" id="pAnswer"><i class="fa-solid fa-eye"></i> {{ S::get('portal.show_answer') }}</button>
                <button class="btn btn-yellow" id="pNew"><i class="fa-solid fa-rotate-right"></i> {{ S::get('portal.new_question') }}</button>
            </div>
            <div class="rate-box" id="pRate" hidden>
                <p class="small" style="margin: 0 0 8px">{{ S::get('portal.rate') }}</p>
                <div class="two-col">
                    <button class="btn btn-white btn-sm" data-rate="easy">😊 {{ S::get('portal.easy') }}</button>
                    <button class="btn btn-white btn-sm" data-rate="medium">😐 {{ S::get('portal.medium') }}</button>
                    <button class="btn btn-white btn-sm" data-rate="hard">☹️ {{ S::get('portal.hard') }}</button>
                </div>
            </div>
            <button class="btn btn-outline" id="pNote" style="margin-top: 14px"><i class="fa-solid fa-triangle-exclamation"></i> {{ S::get('portal.note_button') }}</button>
        </div>

        <div class="letters-grid" id="pLetters" role="group" aria-label="الحروف">
            @foreach (\App\Support\AlefYaLetters::ALL as $l)
                <button data-letter="{{ $l }}" aria-label="حرف {{ $l }}">{{ $l }}</button>
            @endforeach
        </div>

        <div class="btn-stack">
            <button class="btn btn-red" id="pMystery">{{ S::get('portal.mystery') }}</button>
            <a class="btn btn-outline cyan" href="{{ route('alefya.page', 'contribute') }}"><i class="fa-solid fa-pen-nib"></i> {{ S::get('portal.contribute') }}</a>
        </div>

        @include('alefya.partials.footer')
    </div>
</main>

<div class="modal" id="noteModal" role="dialog" aria-modal="true" aria-labelledby="noteTitle">
    <div class="glass">
        <button class="modal-close" data-close aria-label="{{ S::get('app.close') }}"><i class="fa-solid fa-xmark"></i></button>
        <h2 id="noteTitle">{{ S::get('portal.note_title') }}</h2>
        <p class="muted small">{{ S::get('portal.note_text') }}</p>
        <textarea class="field" id="noteBody" maxlength="2000" aria-label="{{ S::get('portal.note_title') }}"></textarea>
        <button class="btn btn-red" id="noteSend" style="margin-top: 14px"><i class="fa-solid fa-paper-plane"></i> <span>{{ S::get('portal.note_send') }}</span></button>
    </div>
</div>

@include('alefya.partials.notifications')
@endsection
