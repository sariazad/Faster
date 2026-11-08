@php $w = \App\Support\AlefYaStrings::get('app.words'); @endphp
<a href="{{ route('alefya.home') }}" class="ay-logo {{ $size ?? '' }}" aria-label="{{ \App\Support\AlefYaStrings::get('app.name') }}">
    <span class="w1">{{ $w[0] }}</span><i class="dot" aria-hidden="true"></i><span class="w2">{{ $w[1] }}</span>
</a>
