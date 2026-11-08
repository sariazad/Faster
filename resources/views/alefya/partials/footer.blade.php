@php use App\Support\AlefYaStrings as S; @endphp
<footer class="ay-footer">
    <a href="{{ route('alefya.page', 'faq') }}">{{ S::get('app.help_link') }}</a>
    <div class="socials">
        @if ($x = config('alefya.accounts.0.x'))
            <a href="{{ $x }}" target="_blank" rel="noopener" aria-label="X"><i class="fa-brands fa-x-twitter"></i></a>
        @endif
        <a href="mailto:{{ config('alefya.email') }}" aria-label="البريد الإلكتروني"><i class="fa-solid fa-envelope"></i></a>
        <a href="{{ route('home') }}" aria-label="{{ S::get('app.main_site') }}" title="{{ S::get('app.main_site') }}"><i class="fa-solid fa-stopwatch"></i></a>
    </div>
    <x-copyright class="ay-copy" />
</footer>
