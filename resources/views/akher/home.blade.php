@extends('akher.layout', ['page' => 'home'])
@php use App\Support\AkherStrings as S; @endphp

@section('content')
<main class="wrap narrow">
    <div class="row between">
        @include('akher.partials.toolbar')
    </div>

    <header class="center" style="margin: 26px 0 22px">
        @include('akher.partials.logo')
        <p class="muted" style="margin: 12px 0 0; font-size: 17px">{{ S::get('app.tagline') }}</p>
        <a class="family" href="{{ route('home') }}">{{ S::get('app.family') }}</a>
    </header>

    <section class="card lift stack">
        <div id="resumeBox" hidden>
            <a class="btn lantern block" id="resumeBtn" href="#"></a>
        </div>
        <div>
            <label class="lbl" for="name">{{ S::get('home.name') }}</label>
            <input class="field" id="name" maxlength="20" autocomplete="nickname" enterkeyhint="go">
        </div>
        <button class="btn block" id="createBtn">{{ S::get('home.create') }}</button>
        <div class="divider">{{ S::get('home.or') }}</div>
        <form class="row" id="joinForm">
            <input class="field code grow" id="code" inputmode="numeric" maxlength="4" placeholder="0000" aria-label="{{ S::get('home.code') }}" autocomplete="off">
            <button class="btn ghost" style="min-height:50px">{{ S::get('home.join') }}</button>
        </form>
    </section>

    <section class="card" style="margin-top: 16px">
        <h2>{{ S::get('home.rules_title') }}</h2>
        <ul class="rules">
            @foreach (S::get('home.rules') as $rule)<li>{{ $rule }}</li>@endforeach
        </ul>
    </section>

    @include('akher.partials.footer')
</main>
@endsection
