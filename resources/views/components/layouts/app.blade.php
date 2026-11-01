<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#120d2b">
    <title>{{ $title ?? 'مين الأسبق' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        // Question timer: main phase for the picking team, then a steal phase for the other team.
        document.addEventListener('alpine:init', () => {
            Alpine.data('questionTimer', (main, steal, canSteal) => ({
                phase: 'main',      // main | steal | over | stopped
                remaining: main,
                total: main,
                steal,
                canSteal,
                paused: false,
                interval: null,

                init() {
                    this.interval = setInterval(() => this.tick(), 1000);
                },

                destroy() {
                    clearInterval(this.interval);
                },

                tick() {
                    if (this.paused || this.phase === 'over' || this.phase === 'stopped') return;

                    this.remaining--;

                    if (this.remaining <= 5 && this.remaining > 0) beep(660, 0.08);

                    if (this.remaining > 0) return;

                    if (this.phase === 'main' && this.canSteal) {
                        this.phase = 'steal';
                        this.remaining = this.total = this.steal;
                        beep(330, 0.4);
                    } else {
                        this.phase = 'over';
                        this.remaining = 0;
                        beep(220, 0.7);
                    }
                },

                add(seconds) {
                    if (this.phase !== 'main') return;
                    this.remaining += seconds;
                    this.total += seconds;
                },

                lock() {
                    this.canSteal = false;
                    if (this.phase === 'steal') this.phase = 'over';
                },

                stop() {
                    this.phase = 'stopped';
                },

                toggle() {
                    this.paused = ! this.paused;
                },

                get progress() {
                    return this.total ? this.remaining / this.total : 0;
                },
            }));
        });

        let audioCtx;
        function beep(freq, duration) {
            try {
                audioCtx ??= new (window.AudioContext || window.webkitAudioContext)();
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.frequency.value = freq;
                gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + duration);
                osc.connect(gain).connect(audioCtx.destination);
                osc.start();
                osc.stop(audioCtx.currentTime + duration);
            } catch (e) { /* audio is optional */ }
        }
    </script>
</head>
<body class="min-h-screen bg-night text-white font-sans antialiased selection:bg-lantern/40">
    <div class="fixed inset-0 -z-10 bg-[radial-gradient(ellipse_at_top,_rgba(255,170,51,0.12),_transparent_55%),radial-gradient(ellipse_at_bottom,_rgba(99,70,255,0.18),_transparent_60%)]"></div>

    {{ $slot }}
</body>
</html>
