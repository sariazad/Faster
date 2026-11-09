@extends('akher.layout', ['page' => 'room', 'title' => 'غرفة '.$code])

@section('content')
<main class="wrap" id="app" data-code="{{ $code }}" aria-live="off">
    <div class="center muted" style="padding: 80px 0">…</div>
</main>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
@endsection
