@php use App\Support\AkherStrings as S; @endphp
<div class="row" style="gap:8px">
    <a class="icon-btn" href="{{ route('akher.page', 'how') }}" aria-label="{{ S::get('app.how') }}" title="{{ S::get('app.how') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v5h5"/><path d="M8 13h8M8 17h6"/></svg>
    </a>
    <button class="icon-btn" data-theme-toggle aria-label="{{ S::get('app.theme') }}" title="{{ S::get('app.theme') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 3v18a9 9 0 0 0 0-18Z" fill="currentColor"/></svg>
    </button>
    <button class="icon-btn" data-sound-toggle aria-label="{{ S::get('app.sound') }}" title="{{ S::get('app.sound') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5 6 9H2v6h4l5 4V5Z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M19 5a10 10 0 0 1 0 14"/></svg>
    </button>
</div>
