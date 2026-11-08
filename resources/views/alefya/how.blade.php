@extends('alefya.layout', ['page' => 'how', 'title' => \App\Support\AlefYaStrings::get('how.title')])
@php use App\Support\AlefYaStrings as S; @endphp

@section('content')
<main class="ay-page">
    <div class="glass wide">
        <h1 class="page-title"><i class="fa-solid fa-file-lines"></i> {{ S::get('how.title') }}</h1>
        @foreach (S::get('how.sections') as $i => $items)
            <div class="tab-content" data-tab-panel="{{ $i }}" @if ($i) hidden @endif role="tabpanel">
                <ul>@foreach ($items as $line)<li>{{ $line }}</li>@endforeach</ul>
            </div>
        @endforeach
        <div class="tabs" role="tablist">
            @foreach (S::get('how.tabs') as $i => $tab)
                <button class="choice @if (! $i) on @endif" data-tab="{{ $i }}" role="tab" aria-selected="{{ $i ? 'false' : 'true' }}">{{ $tab }}</button>
            @endforeach
        </div>
        <a class="btn btn-white" style="margin-top: 20px" href="{{ route('alefya.home') }}">{{ S::get('app.back_home') }}</a>
    </div>
</main>
@endsection
