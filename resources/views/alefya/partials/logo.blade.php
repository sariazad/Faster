@php $w = \App\Support\AlefYaStrings::get('app.words'); @endphp
{{-- ألف ياء wordmark: a letter tile with speed lines (sibling of the مين الأسبق stopwatch) + sticker lettering. --}}
<a href="{{ route('alefya.home') }}" class="ay-logo {{ $size ?? '' }}" aria-label="{{ \App\Support\AlefYaStrings::get('app.name') }}">
    <svg viewBox="0 0 66 50" aria-hidden="true">
        <g stroke="#ffaa33" stroke-width="4" stroke-linecap="round">
            <path d="M50 17h13"/><path d="M52 26h9"/><path d="M50 35h13"/>
        </g>
        <polygon points="24,3 44,14.5 44,37.5 24,49 4,37.5 4,14.5" fill="#fff3dc" stroke="#ffffff" stroke-width="7" stroke-linejoin="round"/>
        <polygon points="24,3 44,14.5 44,37.5 24,49 4,37.5 4,14.5" fill="#fff3dc" stroke="#ffaa33" stroke-width="4" stroke-linejoin="round"/>
        <text x="24" y="28" text-anchor="middle" dominant-baseline="central" font-family="Tajawal" font-weight="900" font-size="26" fill="#120d2b">أ</text>
    </svg>
    <span class="mark">{{ $w[0] }} <span class="accent">{{ $w[1] }}</span></span>
</a>
