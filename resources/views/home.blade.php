<x-layouts.app title="مين الأسبق — لعبة تحدي جماعية">
    <main class="mx-auto flex min-h-screen max-w-5xl flex-col items-center justify-center px-4 py-16 text-center">
        <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-lantern/30 bg-lantern/10 px-4 py-1.5 text-sm font-bold text-lantern-light">
            <span class="h-2 w-2 animate-pulse rounded-full bg-lantern"></span>
            لعبة القعدات والجمعات
        </div>

        <h1 class="text-5xl sm:text-8xl"><x-logo /></h1>

        <p class="mt-6 max-w-xl text-lg leading-relaxed text-white/70 sm:text-xl">
            فريقين، شاشة وحدة، وست فئات. كل فريق يختار سؤاله ويحاول يجاوب قبل ما يخلص الوقت، وإذا ما عرف، الفريق الثاني يسرق النقاط.
        </p>

        <div class="mt-10 flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('game.new') }}"
               class="animate-glow rounded-2xl bg-lantern px-10 py-4 text-xl font-extrabold text-night transition hover:bg-lantern-light">
                ابدأ لعبة جديدة
            </a>
            <a href="/admin"
               class="rounded-2xl border border-white/15 px-8 py-4 text-lg font-bold text-white/80 transition hover:border-white/30 hover:text-white">
                بنك الأسئلة
            </a>
        </div>

        <section class="mt-20 grid w-full gap-4 text-start sm:grid-cols-3">
            @foreach ([
                ['١', 'اختاروا الفئات', 'كل فريق يختار ٣ فئات، والمجموع ٣٦ سؤال على اللوحة.'],
                ['٢', 'العبوا بالدور', 'الفريق يختار سؤال ٢٠٠ أو ٤٠٠ أو ٦٠٠، وعنده ٦٠ ثانية.'],
                ['٣', 'اسرقوا النقاط', 'إذا ما عرف، الفريق الثاني عنده ٣٠ ثانية يسرق فيها السؤال.'],
            ] as [$n, $head, $text])
                <div class="rounded-2xl border border-white/10 bg-night-800/70 p-6">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-lantern/15 text-xl font-black text-lantern">{{ $n }}</div>
                    <h2 class="text-lg font-extrabold">{{ $head }}</h2>
                    <p class="mt-1 text-white/60">{{ $text }}</p>
                </div>
            @endforeach
        </section>
    </main>
</x-layouts.app>
