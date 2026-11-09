@extends('akher.layout', ['page' => 'faq', 'title' => \App\Support\AkherStrings::get('faq.title')])
@php use App\Support\AkherStrings as S; @endphp

@section('content')
<main class="wrap narrow">
    <div class="row between">@include('akher.partials.logo', ['size' => 'sm']) @include('akher.partials.toolbar')</div>
    <h1>{{ S::get('faq.title') }}</h1>
    @foreach (S::get('faq.items') as [$q, $a])
        <details class="acc"><summary>{{ $q }}</summary><p>{{ $a }}</p></details>
    @endforeach
    <p><a class="btn ghost" href="{{ route('akher.home') }}">{{ S::get('app.back_home') }}</a></p>
    @include('akher.partials.footer')
</main>
@endsection
