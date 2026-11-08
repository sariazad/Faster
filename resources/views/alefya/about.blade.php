@extends('alefya.layout', ['page' => 'about', 'title' => \App\Support\AlefYaStrings::get('about.title')])
@php use App\Support\AlefYaStrings as S; @endphp

@section('content')
<main class="ay-page">
    <div class="glass">
        <div style="margin: 6px 0 14px">@include('alefya.partials.logo', ['size' => 'sm'])</div>
        <h1 class="page-title">{{ S::get('about.title') }}</h1>
        <p class="muted" style="line-height: 1.9">{{ S::get('about.text') }}</p>
        <h2 style="font-size: 18px; margin-top: 20px">{{ S::get('about.accounts') }}</h2>
        @foreach (config('alefya.accounts') as $acc)
            <div class="account-row">
                <b>{{ $acc['name'] }}</b>
                <div class="links">
                    @if ($acc['x'] ?? null)<a href="{{ $acc['x'] }}" target="_blank" rel="noopener" aria-label="X"><i class="fa-brands fa-x-twitter"></i></a>@endif
                    @if ($acc['tiktok'] ?? null)<a href="{{ $acc['tiktok'] }}" target="_blank" rel="noopener" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a>@endif
                    @if ($acc['youtube'] ?? null)<a href="{{ $acc['youtube'] }}" target="_blank" rel="noopener" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>@endif
                </div>
            </div>
        @endforeach
        <a class="btn btn-white" style="margin-top: 20px" href="{{ route('alefya.home') }}">{{ S::get('app.back_home') }}</a>
        @include('alefya.partials.footer')
    </div>
</main>
@endsection
