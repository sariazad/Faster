@php use App\Support\AkherStrings as S; @endphp
<nav class="foot" aria-label="روابط">
    <a href="{{ route('akher.page', 'how') }}">{{ S::get('app.how') }}</a>
    <a href="{{ route('akher.page', 'faq') }}">{{ S::get('app.faq') }}</a>
    <a href="{{ route('akher.page', 'about') }}">{{ S::get('app.about') }}</a>
    <a href="{{ route('home') }}">مين الأسبق</a>
</nav>
<x-copyright class="center small muted" />
