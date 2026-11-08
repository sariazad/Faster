@extends('alefya.layout', ['page' => 'customize', 'title' => \App\Support\AlefYaStrings::get('customize.title')])
@php use App\Support\AlefYaStrings as S; @endphp

@section('content')
<main class="ay-page">
    <div class="glass wide">
        <div class="corner right">
            <button class="btn btn-yellow btn-sm" id="saveBtn"><i class="fa-solid fa-floppy-disk"></i> {{ S::get('customize.save') }}</button>
        </div>
        <h1 class="page-title" style="margin-top: 30px">{{ S::get('customize.title') }}</h1>

        <form class="rows" id="settingsForm" onsubmit="return false">
            <div class="row">
                <div class="lbl-col"><label for="contestName">{{ S::get('customize.contest_name') }}</label><small>{{ S::get('customize.contest_name_hint') }}</small></div>
                <input class="field" id="contestName" maxlength="40" autocomplete="off">
            </div>

            <div class="row">
                <div class="lbl-col">{{ S::get('customize.players') }}</div>
                <div>
                    <div class="player-field"><span class="mini-hex" data-team-hex="0"></span><input class="field" id="team1" maxlength="20" aria-label="{{ S::get('customize.team1') }}"></div>
                    <div class="player-field"><span class="mini-hex" data-team-hex="1"></span><input class="field" id="team2" maxlength="20" aria-label="{{ S::get('customize.team2') }}"></div>
                </div>
            </div>

            <div class="row">
                <div class="lbl-col">{{ S::get('customize.rounds') }}</div>
                <div class="choices" data-setting="rounds" role="radiogroup">
                    <button type="button" class="choice" data-value="1">{{ S::get('customize.rounds_1') }}</button>
                    <button type="button" class="choice" data-value="2">{{ S::get('customize.rounds_2') }}</button>
                    <button type="button" class="choice" data-value="3">{{ S::get('customize.rounds_3') }}</button>
                </div>
            </div>

            <div class="row">
                <div class="lbl-col">{{ S::get('customize.size') }}</div>
                <div class="choices" data-setting="size" role="radiogroup">
                    @foreach ([4, 5, 6] as $n)
                        <button type="button" class="choice" data-value="{{ $n }}">{{ $n }}×{{ $n }}</button>
                    @endforeach
                </div>
            </div>

            <div class="row">
                <div class="lbl-col">{{ S::get('customize.auto_win') }}</div>
                <div class="choices" data-setting="autoWin" role="radiogroup">
                    <button type="button" class="choice" data-value="1">{{ S::get('customize.on') }}</button>
                    <button type="button" class="choice" data-value="0">{{ S::get('customize.off') }}</button>
                </div>
            </div>

            <div class="row">
                <div class="lbl-col">{{ S::get('customize.colors') }}</div>
                <div class="choices" data-setting="colors" role="radiogroup">
                    @foreach (S::get('colors') as $i => [$a, $b])
                        <button type="button" class="choice pair" data-value="{{ $i }}" aria-label="زوج الألوان {{ $i + 1 }}">
                            <span class="mini-hex" style="background: {{ $a }}"></span><span class="mini-hex" style="background: {{ $b }}"></span>
                        </button>
                    @endforeach
                </div>
            </div>
        </form>

        <a class="btn btn-white" style="margin-top: 20px" href="{{ route('alefya.home') }}">{{ S::get('app.back_home') }}</a>
    </div>
</main>
@endsection
