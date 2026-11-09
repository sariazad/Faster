@extends('akher.layout', ['page' => 'how', 'title' => \App\Support\AkherStrings::get('how.title')])
@php use App\Support\AkherStrings as S; @endphp

@section('content')
<main class="wrap narrow">
    <div class="row between">@include('akher.partials.logo', ['size' => 'sm']) @include('akher.partials.toolbar')</div>
    <h1>{{ S::get('how.title') }}</h1>
    <div class="tabs" role="tablist">
        @foreach (S::get('how.tabs') as $i => $tab)
            <button class="opt @if (! $i) on @endif" data-tab="{{ $i }}" role="tab" aria-selected="{{ $i ? 'false' : 'true' }}">{{ $tab }}</button>
        @endforeach
    </div>
    @foreach (S::get('how.sections') as $i => $lines)
        <section class="card" data-panel="{{ $i }}" role="tabpanel" @if ($i) hidden @endif>
            <ul class="rules">@foreach ($lines as $l)<li>{{ $l }}</li>@endforeach</ul>
        </section>
    @endforeach
    <p><a class="btn ghost" href="{{ route('akher.home') }}">{{ S::get('app.back_home') }}</a></p>
    @include('akher.partials.footer')
</main>
@endsection
