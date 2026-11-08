@extends('alefya.layout', ['page' => 'login', 'title' => \App\Support\AlefYaStrings::get('login.title')])
@php use App\Support\AlefYaStrings as S; @endphp

@section('content')
<main class="ay-page">
    <div class="glass">
        <div style="margin-bottom: 10px">@include('alefya.partials.logo', ['size' => 'sm'])</div>
        <h1 class="page-title">{{ S::get('login.title') }}</h1>

        <form id="loginForm" autocomplete="off" data-next="{{ request('next') === 'questions' ? route('alefya.portal') : route('alefya.home') }}">
            <div style="display:flex; justify-content:space-between; align-items:center">
                <label class="lbl" for="code">{{ S::get('login.code') }}</label>
                <button type="button" class="link-btn small" data-open="whereModal">{{ S::get('login.where') }}</button>
            </div>
            <input class="field code-input" id="code" inputmode="numeric" maxlength="10" placeholder="0000000000" required>

            <div id="pinWrap" hidden style="margin-top: 18px">
                <label class="lbl">{{ S::get('login.pin') }}</label>
                <div class="pin-boxes" role="group" aria-label="{{ S::get('login.pin') }}">
                    @for ($i = 0; $i < 4; $i++)
                        <input type="password" inputmode="numeric" maxlength="1" data-pin="{{ $i }}" aria-label="{{ S::get('login.pin') }} {{ $i + 1 }}">
                    @endfor
                </div>
            </div>

            <p class="small note-yellow">{{ S::get('login.first_note') }}</p>
            <button class="btn btn-yellow" style="margin-top: 10px" id="loginBtn">{{ S::get('login.submit') }}</button>
        </form>

        <div class="btn-stack" style="margin-top: 18px">
            <p class="muted small" style="margin:0">{{ S::get('login.no_code') }}</p>
            <button class="btn btn-outline cyan" id="demoBtn"><i class="fa-solid fa-wand-magic-sparkles"></i> {{ S::get('login.demo') }}</button>
        </div>
        @include('alefya.partials.footer')
    </div>
</main>

<div class="modal" id="whereModal" role="dialog" aria-modal="true">
    <div class="glass">
        <button class="modal-close" data-close aria-label="{{ S::get('app.close') }}"><i class="fa-solid fa-xmark"></i></button>
        <h2>{{ S::get('login.where') }}</h2>
        <p class="muted" style="line-height:1.8">{{ S::get('login.where_text') }}</p>
    </div>
</div>
@endsection
