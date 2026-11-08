@extends('alefya.layout', ['page' => 'contribute', 'title' => \App\Support\AlefYaStrings::get('contribute.title')])
@php use App\Support\AlefYaStrings as S; @endphp

@section('content')
<main class="ay-page">
    <div class="glass">
        <h1 class="page-title"><i class="fa-solid fa-pen-nib"></i> {{ S::get('contribute.title') }}</h1>
        <p class="muted small" style="line-height: 1.8">{{ S::get('contribute.intro') }}</p>

        <form id="contribForm" novalidate>
            <input class="field" id="cName" maxlength="60" placeholder="{{ S::get('contribute.name') }}" aria-label="{{ S::get('contribute.name') }}" style="margin: 10px 0 16px">
            <div id="blocks"></div>
            <button type="button" class="btn btn-outline cyan" id="addBlock">{{ S::get('contribute.add') }}</button>
            <p class="small muted" id="maxNote" hidden>{{ S::get('contribute.max') }}</p>
            <button class="btn btn-yellow" style="margin-top: 16px" id="cSend"><i class="fa-solid fa-paper-plane"></i> {{ S::get('contribute.send') }}</button>
        </form>

        <a class="btn btn-white" style="margin-top: 14px" href="{{ route('alefya.portal') }}">{{ S::get('app.back') }}</a>
    </div>
</main>

<template id="blockTpl">
    <div class="q-block">
        <div class="head"><span class="num"></span><span class="letter-tag"></span>
            <button type="button" class="link-btn remove">{{ S::get('contribute.remove') }}</button></div>
        <textarea class="field q" maxlength="500" placeholder="{{ S::get('contribute.question') }}" aria-label="{{ S::get('contribute.question') }}"></textarea>
        <input class="field a" maxlength="120" placeholder="{{ S::get('contribute.answer') }}" aria-label="{{ S::get('contribute.answer') }}">
    </div>
</template>
@endsection
