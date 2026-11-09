@extends('akher.layout', ['page' => 'about', 'title' => \App\Support\AkherStrings::get('about.title')])
@php use App\Support\AkherStrings as S; @endphp

@section('content')
<main class="wrap narrow center">
    <div style="margin: 30px 0 10px">@include('akher.partials.logo')</div>
    <h1>{{ S::get('about.title') }}</h1>
    <p class="muted" style="line-height: 1.9">{{ S::get('about.text') }}</p>
    <div class="row" style="justify-content:center; flex-wrap: wrap">
        @foreach (config('akher.links') as $label => $url)
            @if ($url)<a class="btn ghost sm" href="{{ $url }}" target="_blank" rel="noopener">{{ $label }}</a>@endif
        @endforeach
    </div>
    <p style="margin-top: 20px"><a class="btn" href="{{ route('akher.home') }}">{{ S::get('app.back_home') }}</a></p>
    @include('akher.partials.footer')
</main>
@endsection
