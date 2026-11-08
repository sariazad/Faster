@extends('alefya.layout', ['page' => 'faq', 'title' => \App\Support\AlefYaStrings::get('faq.title')])
@php use App\Support\AlefYaStrings as S; @endphp

@section('content')
<main class="ay-page">
    <div class="glass wide">
        <h1 class="page-title"><i class="fa-solid fa-circle-question"></i> {{ S::get('faq.title') }}</h1>
        @foreach (S::get('faq.items') as [$q, $a])
            <details class="acc"><summary>{{ $q }}</summary><p>{{ $a }}</p></details>
        @endforeach
        <a class="btn btn-white" style="margin-top: 14px" href="{{ route('alefya.home') }}">{{ S::get('app.back_home') }}</a>
    </div>
</main>
@endsection
