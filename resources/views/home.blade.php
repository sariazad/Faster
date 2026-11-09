<x-layouts.app title="مين الأسبق — لعبة تحدي جماعية">
    <main class="mx-auto flex min-h-screen max-w-6xl flex-col items-center justify-center px-4 py-16 text-center">
        <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-lantern/30 bg-lantern/10 px-4 py-1.5 text-sm font-bold text-lantern-light">
            <span class="h-2 w-2 animate-pulse rounded-full bg-lantern"></span>
            لعبة القعدات والجمعات
        </div>

        <h1 class="text-5xl sm:text-8xl"><x-logo /></h1>

        <p class="mt-6 max-w-xl text-lg leading-relaxed text-white/70 sm:text-xl">
            فريقين، شاشة وحدة، وبنك أسئلة كبير. اختاروا طريقة اللعب:
        </p>

        <section class="mt-10 grid w-full gap-5 text-start md:grid-cols-2">
            {{-- التحدي --}}
            <a href="{{ route('game.new') }}"
               class="group relative overflow-hidden rounded-[2rem] border border-white/10 bg-night-800/80 p-6 transition hover:-translate-y-1 hover:border-lantern/60 sm:p-8">
                <div class="mb-5 grid w-fit grid-cols-3 gap-1.5" aria-hidden="true">
                    @foreach ([200, 400, 600, 400, 600, 200] as $p)
                        <span @class([
                            'flex h-9 w-14 items-center justify-center rounded-lg text-xs font-black',
                            'bg-level-200 text-night' => $p === 200,
                            'bg-level-400 text-night' => $p === 400,
                            'bg-level-600 text-white' => $p === 600,
                        ])>{{ $p }}</span>
                    @endforeach
                </div>
                <h2 class="text-3xl font-black">التحدي</h2>
                <p class="mt-2 leading-relaxed text-white/65">
                    كل فريق يختار ٣ فئات، والأسئلة على اللوحة ٢٠٠ و٤٠٠ و٦٠٠. اللي يجمع نقاط أكثر يفوز، ولو غلط الخصم تقدر تسرق السؤال.
                </p>
                <span class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-lantern px-6 py-3 text-lg font-extrabold text-night transition group-hover:bg-lantern-light">
                    ابدأ التحدي ←
                </span>
            </a>

            {{-- طق طق --}}
            <a href="{{ route('taqtaq.new') }}"
               class="group relative overflow-hidden rounded-[2rem] border border-white/10 bg-night-800/80 p-6 transition hover:-translate-y-1 hover:border-lantern/60 sm:p-8">
                <svg class="mb-5 h-[3.6rem] w-auto" viewBox="0 0 150 76" aria-hidden="true">
                    @foreach ([[0,0,'#22d3ee'],[1,0,'#fff7e6'],[2,0,'#fb7185'],[0,1,'#22d3ee'],[1,1,'#22d3ee'],[2,1,'#fff7e6']] as [$c,$r,$f])
                        @php
                            $x = 22 + $c * 38 + ($r ? 19 : 0); $y = 20 + $r * 33; $pts = [];
                            foreach ([-90,-30,30,90,150,210] as $d) { $a = deg2rad($d); $pts[] = round($x + 20 * cos($a), 1).','.round($y + 20 * sin($a), 1); }
                        @endphp
                        <polygon points="{{ implode(' ', $pts) }}" fill="{{ $f }}" stroke="#120d2b" stroke-opacity=".35" stroke-width="2"/>
                    @endforeach
                </svg>
                <h2 class="text-3xl font-black">طق طق 🚪</h2>
                <p class="mt-1 font-bold text-lantern">من طق طق… للسلام عليكم</p>
                <p class="mt-2 leading-relaxed text-white/65">
                    لوحة خلايا سداسية، وكل خلية بسؤال. جاوب صح وخذ الخلية، وأول فريق يوصّل جهتي اللوحة تبعه يفوز.
                </p>
                <span class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-lantern px-6 py-3 text-lg font-extrabold text-night transition group-hover:bg-lantern-light">
                    ابدأ طق طق ←
                </span>
            </a>
            {{-- آخر القائمة --}}
            <a href="{{ route('akher.home') }}"
               class="group relative overflow-hidden rounded-[2rem] border border-white/10 bg-night-800/80 p-6 transition hover:-translate-y-1 hover:border-lantern/60 sm:p-8">
                <span class="absolute end-5 top-5 rounded-full bg-lantern px-3 py-1 text-xs font-black text-night">جديد</span>
                <div class="mb-5 grid h-[3.6rem] w-[9.5rem] grid-cols-5 gap-1" aria-hidden="true" dir="ltr">
                    @foreach ([0,0,1,0,2,0,1,0,0,2] as $i => $t)
                        <span class="rounded-[4px] {{ $t === 1 ? 'bg-[#e5484d]' : ($t === 2 ? 'bg-[#2b7fd8]' : 'bg-white/10') }} text-[9px] font-bold leading-none text-white/60 ps-0.5 pt-0.5">{{ [91, 92, 93, 94, 95, 96, 97, 98, 99, 100][$i] }}</span>
                    @endforeach
                </div>
                <h2 class="text-3xl font-black"><span class="font-light">آخر</span> القائمة<sup class="ms-1 text-sm font-bold text-lantern">100</sup></h2>
                <p class="mt-1 font-bold text-lantern">كل ما بعّدت… ربحت أكتر</p>
                <p class="mt-2 leading-relaxed text-white/65">
                    قائمة من 100 وفريقين، وكل واحد بيخمّن من جواله. الدولة رقم 90 بـ90 نقطة، بس انتبه لا تطلع برّا القائمة.
                </p>
                <span class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-lantern px-6 py-3 text-lg font-extrabold text-night transition group-hover:bg-lantern-light">
                    افتح غرفة ←
                </span>
            </a>

            {{-- ألف ياء --}}
            <a href="{{ route('alefya.home') }}"
               class="group relative overflow-hidden rounded-[2rem] border border-white/10 bg-night-800/80 p-6 transition hover:-translate-y-1 hover:border-lantern/60 sm:p-8">
                <span class="absolute end-5 top-5 rounded-full bg-lantern px-3 py-1 text-xs font-black text-night">جديد</span>
                <svg class="mb-5 h-[3.6rem] w-auto" viewBox="0 0 150 76" aria-hidden="true">
                    @foreach ([[0,0,'#fff3dc','أ'],[1,0,'#22d3ee',''],[2,0,'#fff3dc','ي'],[0,1,'#fb7185',''],[1,1,'#ffaa33','ب'],[2,1,'#fff3dc','ح']] as [$c,$r,$f,$l])
                        @php
                            $x = 22 + $c * 38 + ($r ? 19 : 0); $y = 20 + $r * 33; $pts = [];
                            foreach ([-90,-30,30,90,150,210] as $d) { $a = deg2rad($d); $pts[] = round($x + 20 * cos($a), 1).','.round($y + 20 * sin($a), 1); }
                        @endphp
                        <polygon points="{{ implode(' ', $pts) }}" fill="{{ $f }}" stroke="#120d2b" stroke-opacity=".35" stroke-width="2"/>
                        <text x="{{ $x }}" y="{{ $y + 1 }}" text-anchor="middle" dominant-baseline="central" font-size="18" font-weight="900" fill="#120d2b">{{ $l }}</text>
                    @endforeach
                </svg>
                <h2 class="text-3xl font-black">ألف ياء 🔤</h2>
                <p class="mt-1 font-bold text-lantern">من الألف… للياء</p>
                <p class="mt-2 leading-relaxed text-white/65">
                    كل خلية فيها حرف، والجواب يبدأ فيه. أول فريق يوصّل ضلعيه يربح، ومعها تحدي فردي ولوحة شرف وبوابة أسئلة للمقدّم.
                </p>
                <span class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-lantern px-6 py-3 text-lg font-extrabold text-night transition group-hover:bg-lantern-light">
                    ابدأ ألف ياء ←
                </span>
            </a>
        </section>

        <a href="/admin"
           class="mt-8 rounded-2xl border border-white/15 px-8 py-3 text-base font-bold text-white/70 transition hover:border-white/30 hover:text-white">
            بنك الأسئلة ولوحة التحكم
        </a>

        <x-copyright class="mt-16 text-white/40" />
    </main>
</x-layouts.app>
