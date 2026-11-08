@extends('alefya.layout', ['page' => 'play', 'title' => \App\Support\AlefYaStrings::get('home.start')])
@php use App\Support\AlefYaStrings as S; @endphp

@section('content')
<main class="play-wrap">
    <section class="board-area" id="boardArea" aria-label="لوحة الحروف"></section>

    <aside class="side" aria-label="لوحة التحكم">
        <div class="side-head">
            <button class="icon-btn" id="menuBtn" aria-label="{{ S::get('play.menu') }}"><i class="fa-solid fa-bars"></i></button>
            <div class="round-title" id="roundTitle"><span class="a">{{ S::get('play.round') }}</span> <span class="b">{{ S::get('play.round_names.0') }}</span></div>
            <button class="icon-btn" id="musicBtn" aria-label="{{ S::get('home.music') }}"><i class="fa-solid fa-music"></i></button>
        </div>
        <div class="contest-name" id="contestName"></div>

        <div class="q-box" aria-live="polite">
            <div class="q-hex empty" id="qHex">!</div>
            <div class="q-text placeholder" id="qText">{{ S::get('play.pick_cell') }}</div>
            <div class="q-contrib" id="qContrib" hidden></div>
            <div class="answer-bar" id="qAnswer" hidden></div>
        </div>

        <div class="q-actions" id="qActions" hidden>
            <button class="btn btn-cyan" id="answerBtn"><i class="fa-solid fa-eye"></i> <span>{{ S::get('play.show_answer') }}</span></button>
            <button class="btn btn-yellow" id="newQBtn"><i class="fa-solid fa-rotate-right"></i> {{ S::get('play.new_question') }}</button>
        </div>

        <div id="teamBars"></div>

        <div style="text-align:center"><span class="sync-pill" id="syncPill" hidden></span></div>
        <div class="keys-hint">{{ S::get('play.keys') }}</div>
    </aside>
</main>

{{-- Question mode (before the first round) --}}
<div class="modal" id="modeModal" role="dialog" aria-modal="true" aria-labelledby="modeTitle">
    <div class="glass">
        <h2 id="modeTitle">{{ S::get('mode.title') }}</h2>
        <p class="muted">{{ S::get('mode.question') }}</p>
        <div class="btn-stack" style="margin-top: 18px">
            <button class="btn btn-red" data-mode="portal"><i class="fa-solid fa-mobile-screen"></i> {{ S::get('mode.portal') }}</button>
            <button class="btn btn-yellow" data-mode="side"><i class="fa-solid fa-table-columns"></i> {{ S::get('mode.side') }}</button>
        </div>
        <div class="row" style="margin-top: 18px; grid-template-columns: 1fr auto; text-align: start">
            <div class="lbl-col">🎲 {{ S::get('mode.mystery') }}<small>{{ S::get('mode.mystery_hint') }}</small></div>
            <div class="choices" data-toggle="mystery"><button type="button" class="choice" data-value="1">{{ S::get('customize.on') }}</button><button type="button" class="choice on" data-value="0">{{ S::get('customize.off') }}</button></div>
        </div>
        <div class="row" style="margin-top: 10px; grid-template-columns: 1fr auto; text-align: start">
            <div class="lbl-col">🔗 {{ S::get('mode.sync') }}<small>{{ S::get('mode.sync_hint') }}</small></div>
            <div class="choices" data-toggle="sync"><button type="button" class="choice" data-value="1">{{ S::get('customize.on') }}</button><button type="button" class="choice on" data-value="0">{{ S::get('customize.off') }}</button></div>
        </div>
        <a class="btn btn-white" style="margin-top: 18px" href="{{ route('alefya.home') }}">{{ S::get('app.back') }}</a>
        <p class="note-yellow">{{ S::get('mode.note') }}</p>
    </div>
</div>

{{-- Menu --}}
<div class="modal" id="menuModal" role="dialog" aria-modal="true" aria-labelledby="menuTitle">
    <div class="glass">
        <button class="modal-close" data-close aria-label="{{ S::get('app.close') }}"><i class="fa-solid fa-xmark"></i></button>
        <h2 id="menuTitle">{{ S::get('play.menu') }}</h2>
        <div class="menu-list">
            <button class="btn btn-yellow" id="restartBtn"><i class="fa-solid fa-rotate-left"></i> {{ S::get('play.menu_restart') }}</button>
            <button class="btn btn-cyan" id="newGameBtn"><i class="fa-solid fa-plus"></i> {{ S::get('play.menu_new') }}</button>
            <div id="manualAward" hidden>
                <p class="muted small" style="margin: 6px 0">{{ S::get('play.menu_award_round') }}</p>
                <div class="two-col" id="manualAwardBtns"></div>
            </div>
            <a class="btn btn-red" href="{{ route('alefya.home') }}" id="exitBtn"><i class="fa-solid fa-right-from-bracket"></i> {{ S::get('play.menu_exit') }}</a>
            <button class="btn btn-white" data-close>{{ S::get('app.close') }}</button>
        </div>
    </div>
</div>

<div class="sunburst" id="sunburst" aria-live="assertive"><div class="label"><span class="a">{{ S::get('play.round') }}</span><span class="b" id="sunName"></span></div></div>

<div class="results" id="results" role="dialog" aria-modal="true" aria-label="النتيجة النهائية">
    <div>
        <div class="teams" id="resultTeams"></div>
        <div class="actions">
            <button class="btn btn-cyan" id="resNew"><i class="fa-solid fa-rotate-right"></i> {{ S::get('result.new_game') }}</button>
            <a class="btn btn-white" href="{{ route('alefya.home') }}"><i class="fa-solid fa-house"></i> {{ S::get('result.home') }}</a>
        </div>
    </div>
</div>
@endsection
