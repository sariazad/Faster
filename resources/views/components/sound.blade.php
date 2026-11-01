@props(['src', 'label' => 'تشغيل الصوت'])

{{-- Big, TV-friendly audio player. wire:ignore keeps playback alive across Livewire updates. --}}
<div wire:ignore wire:key="sound-{{ md5($src) }}"
     x-data="{
         playing: false,
         progress: 0,
         current: '0:00',
         toggle() {
             const a = this.$refs.audio;
             a.paused ? a.play() : a.pause();
         },
         restart() {
             this.$refs.audio.currentTime = 0;
             this.$refs.audio.play();
         },
         seek(e) {
             const bar = e.currentTarget.getBoundingClientRect();
             const rtl = getComputedStyle(e.currentTarget).direction === 'rtl';
             const ratio = rtl ? (bar.right - e.clientX) / bar.width : (e.clientX - bar.left) / bar.width;
             const a = this.$refs.audio;
             if (a.duration) a.currentTime = Math.min(Math.max(ratio, 0), 1) * a.duration;
         },
         tick(a) {
             this.progress = a.duration ? a.currentTime / a.duration : 0;
             const s = Math.floor(a.currentTime);
             this.current = Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
         },
     }"
     class="flex w-full max-w-md items-center gap-4 rounded-full bg-white/5 py-2 pe-6 ps-2 ring-1 ring-white/10">
    <audio x-ref="audio" src="{{ $src }}" preload="auto"
           @play="playing = true" @pause="playing = false"
           @ended="playing = false" @timeupdate="tick($event.target)"></audio>

    <button type="button" @click="toggle()"
            class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-lantern text-night shadow-lg shadow-lantern/30 transition hover:scale-105 hover:bg-lantern-light"
            :title="playing ? 'إيقاف' : 'تشغيل'">
        <svg x-show="! playing" class="h-7 w-7 translate-x-[2px]" viewBox="0 0 24 24" fill="currentColor"><path d="M7 4.5v15a1 1 0 0 0 1.5.86l12.5-7.5a1 1 0 0 0 0-1.72L8.5 3.64A1 1 0 0 0 7 4.5Z"/></svg>
        <svg x-show="playing" class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor" style="display: none"><rect x="6" y="4" width="4.5" height="16" rx="1.2"/><rect x="13.5" y="4" width="4.5" height="16" rx="1.2"/></svg>
    </button>

    <div class="min-w-0 flex-1">
        <div class="mb-1.5 flex items-center justify-between text-sm font-bold text-white/70">
            <span>{{ $label }}</span>
            <span class="flex items-center gap-2">
                <span class="tabular-nums" x-text="current"></span>
                <button type="button" @click="restart()" class="rounded px-1 text-white/50 hover:text-lantern" title="من البداية">↻</button>
            </span>
        </div>
        <div class="h-2.5 cursor-pointer overflow-hidden rounded-full bg-white/10" @click="seek($event)">
            <div class="h-full rounded-full bg-lantern transition-[width] duration-200" :style="`width: ${progress * 100}%`"></div>
        </div>
    </div>
</div>
