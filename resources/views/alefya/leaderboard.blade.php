@extends('alefya.layout', ['page' => 'leaderboard', 'title' => \App\Support\AlefYaStrings::get('leaderboard.title')])
@php use App\Support\AlefYaStrings as S; @endphp

@section('content')
<main class="ay-page">
    <div class="glass wide">
        <h1 class="page-title"><i class="fa-solid fa-trophy"></i> {{ S::get('leaderboard.title') }}</h1>
        <p class="muted">{{ S::get('leaderboard.subtitle') }}</p>

        <form class="inline-form" id="rankForm">
            <input class="field code-input" id="rankCode" inputmode="numeric" maxlength="10" placeholder="0000000000"
                   aria-label="{{ S::get('leaderboard.my_rank') }}" value="{{ $account?->code }}">
            <button class="btn btn-cyan">{{ S::get('leaderboard.show') }}</button>
        </form>
        <p class="small muted" style="margin-top:6px">{{ S::get('leaderboard.my_rank') }}</p>
        <p id="rankResult" style="color: var(--yellow); font-weight: 800"></p>

        <div style="overflow-x:auto">
            <table class="lb">
                <thead><tr>
                    <th>{{ S::get('leaderboard.rank') }}</th><th>{{ S::get('leaderboard.player') }}</th><th>{{ S::get('leaderboard.result') }}</th>
                    <th>{{ S::get('leaderboard.correct') }}</th><th>{{ S::get('leaderboard.time') }}</th><th>{{ S::get('leaderboard.date') }}</th>
                </tr></thead>
                <tbody id="lbBody"><tr><td colspan="6">{{ S::get('app.loading') }}</td></tr></tbody>
            </table>
        </div>
        <a class="btn btn-white" style="margin-top: 20px" href="{{ route('alefya.home') }}">{{ S::get('app.back_home') }}</a>
    </div>
</main>
@endsection
