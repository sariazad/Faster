@php
    use App\Services\PlayStats;
    $d = fn ($s) => PlayStats::duration((int) $s);
    $n = fn ($v) => number_format((int) $v);
    $states = ['finished' => ['خلصت', 'ok'], 'live' => ['شغالة', 'live'], 'abandoned' => ['ما كملت', 'off']];
@endphp

<x-filament-panels::page>
    <style>
        .st-grid { display: grid; gap: 1rem; }
        .st-kpis { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
        .st-games { grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); }
        .st-two { grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
        .st-card { background: #fff; border: 1px solid rgba(0,0,0,.07); border-radius: .9rem; padding: 1rem 1.1rem; }
        .dark .st-card { background: rgb(24 24 27); border-color: rgba(255,255,255,.08); }
        .st-label { font-size: .8rem; opacity: .65; }
        .st-big { font-size: 1.7rem; font-weight: 800; line-height: 1.2; margin-top: .2rem; }
        .st-sub { font-size: .78rem; opacity: .6; margin-top: .15rem; }
        .st-game { border-top: 4px solid var(--c); }
        .st-game h3 { font-weight: 800; font-size: 1.05rem; display: flex; align-items: center; gap: .4rem; }
        .st-game .st-big { color: var(--c); }
        .st-rows { margin-top: .6rem; display: grid; grid-template-columns: 1fr auto; gap: .25rem .75rem; font-size: .85rem; }
        .st-rows span:nth-child(odd) { opacity: .65; }
        .st-rows span:nth-child(even) { font-weight: 700; text-align: left; }
        .st-bar { height: 6px; border-radius: 99px; background: rgba(127,127,127,.15); overflow: hidden; margin-top: .7rem; }
        .st-bar i { display: block; height: 100%; background: var(--c); }
        .st-table { width: 100%; font-size: .87rem; border-collapse: collapse; }
        .st-table th { text-align: right; font-weight: 600; opacity: .6; padding: .45rem .5rem; font-size: .78rem; border-bottom: 1px solid rgba(127,127,127,.2); }
        .st-table td { padding: .5rem; border-bottom: 1px solid rgba(127,127,127,.1); vertical-align: middle; }
        .st-table tr:last-child td { border-bottom: 0; }
        .st-dot { display: inline-block; width: .6rem; height: .6rem; border-radius: 50%; background: var(--c); margin-left: .35rem; }
        .st-pill { font-size: .72rem; padding: .1rem .5rem; border-radius: 99px; font-weight: 700; white-space: nowrap; }
        .st-pill.ok { background: rgba(16,185,129,.15); color: #059669; }
        .st-pill.live { background: rgba(245,158,11,.18); color: #b45309; }
        .st-pill.off { background: rgba(127,127,127,.15); opacity: .75; }
        .st-rank { display: grid; grid-template-columns: 1.6rem 1fr auto; gap: .5rem; align-items: center; padding: .35rem 0; font-size: .9rem; }
        .st-rank b { opacity: .45; font-size: .8rem; }
        .st-rank .st-meter { grid-column: 2 / 4; height: 5px; border-radius: 99px; background: rgba(127,127,127,.15); overflow: hidden; margin-top: -.2rem; }
        .st-rank .st-meter i { display: block; height: 100%; background: #f59e0b; }
        .st-empty { text-align: center; opacity: .55; padding: 1.2rem 0; font-size: .9rem; }
        .st-note { font-size: .78rem; opacity: .6; line-height: 1.7; }
        .st-scroll { overflow-x: auto; }
    </style>

    {{-- Period --}}
    <x-filament::tabs>
        @foreach ($periods as $key => $label)
            <x-filament::tabs.item :active="$period === $key" wire:click="setPeriod('{{ $key }}')">
                {{ $label }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    {{-- Headline --}}
    <div class="st-grid st-kpis">
        <div class="st-card">
            <div class="st-label">عدد الجلسات</div>
            <div class="st-big">{{ $n($totals['sessions']) }}</div>
            <div class="st-sub">{{ $n($totals['finished']) }} خلصت للآخر @if ($totals['live']) · {{ $totals['live'] }} شغالة هلأ @endif</div>
        </div>
        <div class="st-card">
            <div class="st-label">مجموع وقت اللعب</div>
            <div class="st-big">{{ $d($totals['seconds']) }}</div>
            <div class="st-sub">على {{ $totals['days'] }} {{ $totals['days'] === 1 ? 'يوم' : 'أيام' }}</div>
        </div>
        <div class="st-card">
            <div class="st-label">متوسط مدة الجلسة</div>
            <div class="st-big">{{ $d($totals['avg']) }}</div>
            <div class="st-sub">للجلسات اللي خلصت</div>
        </div>
        <div class="st-card">
            <div class="st-label">أسئلة / تخمينات انلعبت</div>
            <div class="st-big">{{ $n($totals['questions']) }}</div>
            <div class="st-sub">بكل الألعاب</div>
        </div>
        <div class="st-card">
            <div class="st-label">أكتر وقت لعب</div>
            <div class="st-big" style="font-size:1.35rem">{{ $peaks['hour'] ?? '—' }}</div>
            <div class="st-sub">{{ $peaks['day'] ? 'وأكتر يوم: '.$peaks['day'] : 'ما في بيانات لسا' }}</div>
        </div>
    </div>

    {{-- Per game --}}
    <div class="st-grid st-games">
        @foreach ($games as $key => $g)
            <div class="st-card st-game" style="--c: {{ $g['color'] }}">
                <h3><span>{{ $g['icon'] }}</span> {{ $g['label'] }}</h3>
                <div class="st-big">{{ $n($g['sessions']) }} <span style="font-size:.85rem;font-weight:600;opacity:.7">{{ $key === 'alefya_solo' ? 'محاولة' : 'جلسة' }}</span></div>
                <div class="st-rows">
                    @if ($key === 'alefya_solo')
                        <span>فازوا</span><span>{{ $g['wins'] }} @if ($g['sessions']) ({{ round($g['wins'] * 100 / $g['sessions']) }}%) @endif</span>
                    @else
                        <span>خلصت للآخر</span><span>{{ $g['finished'] }}</span>
                        <span>ما كملت</span><span>{{ $g['abandoned'] }}</span>
                    @endif
                    <span>متوسط المدة</span><span>{{ $d($g['avg']) }}</span>
                    <span>أطول جلسة</span><span>{{ $d($g['longest']) }}</span>
                    <span>مجموع الوقت</span><span>{{ $d($g['seconds']) }}</span>
                    <span>{{ $key === 'akher' ? 'تخمينات' : 'أسئلة' }}</span><span>{{ $n($g['questions']) }}</span>
                    @if ($key === 'akher' && $g['players'])
                        <span>متوسط اللاعبين</span><span>{{ $g['players'] }}</span>
                    @endif
                    <span>آخر جلسة</span><span>{{ $g['last'] ? $g['last']->copy()->setTimezone(PlayStats::TZ)->locale('ar')->diffForHumans() : '—' }}</span>
                </div>
                <div class="st-bar" title="{{ $g['share'] }}% من كل الجلسات"><i style="width: {{ $g['share'] }}%"></i></div>
                <div class="st-sub">{{ $g['share'] }}% من كل الجلسات</div>
            </div>
        @endforeach
    </div>

    {{-- Charts (keyed by period so they redraw) --}}
    @livewire(\App\Filament\Stats\TimelineChart::class, ['period' => $period], key('timeline-'.$period))

    <div class="st-grid st-two">
        @livewire(\App\Filament\Stats\HoursChart::class, ['period' => $period], key('hours-'.$period))
        @livewire(\App\Filament\Stats\WeekdaysChart::class, ['period' => $period], key('days-'.$period))
    </div>

    {{-- Top --}}
    <div class="st-grid st-two">
        <x-filament::section heading="أكتر الفئات انلعبت" description="مين الأسبق + طق طق، حسب عدد الأسئلة">
            @php $max = max(1, $categories->max('questions') ?? 1); @endphp
            @forelse ($categories as $i => $c)
                <div class="st-rank">
                    <b>{{ $i + 1 }}</b>
                    <span>{{ $c['name'] }}</span>
                    <span class="st-sub" style="margin:0">{{ $c['questions'] }} سؤال · {{ $c['games'] }} لعبة</span>
                    <div class="st-meter"><i style="width: {{ round($c['questions'] * 100 / $max) }}%"></i></div>
                </div>
            @empty
                <div class="st-empty">ما انلعبت فئات بهالفترة</div>
            @endforelse
        </x-filament::section>

        <x-filament::section heading="أكتر القوائم انلعبت" description="آخر القائمة">
            @php $max = max(1, $lists->max('plays') ?? 1); @endphp
            @forelse ($lists as $i => $l)
                <div class="st-rank">
                    <b>{{ $i + 1 }}</b>
                    <span>{{ $l['name'] }}</span>
                    <span class="st-sub" style="margin:0">{{ $l['plays'] }} مرة</span>
                    <div class="st-meter"><i style="width: {{ round($l['plays'] * 100 / $max) }}%; background:#3b82f6"></i></div>
                </div>
            @empty
                <div class="st-empty">ما انلعبت قوائم بهالفترة</div>
            @endforelse
        </x-filament::section>
    </div>

    {{-- Latest sessions --}}
    <x-filament::section heading="آخر الجلسات">
        <div class="st-scroll">
            <table class="st-table">
                <thead>
                    <tr>
                        <th>اللعبة</th>
                        <th>التفاصيل</th>
                        <th>بدأت</th>
                        <th>المدة</th>
                        <th>النتيجة</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recent as $r)
                        @php $g = PlayStats::GAMES[$r['game']]; [$stateLabel, $stateClass] = $states[$r['state']]; @endphp
                        <tr>
                            <td style="white-space:nowrap"><span class="st-dot" style="--c: {{ $g['color'] }}"></span>{{ $g['label'] }}</td>
                            <td>{{ $r['detail'] }}</td>
                            @php $t = $r['start']->copy()->setTimezone(PlayStats::TZ); @endphp
                            <td style="white-space:nowrap">{{ $t->format('Y/m/d') }} · {{ $t->format('g:i') }} {{ $t->hour < 12 ? 'ص' : 'م' }}</td>
                            <td style="white-space:nowrap">{{ $d($r['seconds']) }}</td>
                            <td>{{ $r['winner'] ? ($r['game'] === 'alefya_solo' || $r['winner'] === 'تعادل' ? $r['winner'] : '🏆 '.$r['winner']) : '—' }}</td>
                            <td><span class="st-pill {{ $stateClass }}">{{ $stateLabel }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="st-empty">ما في جلسات بهالفترة</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <p class="st-note">
        الأوقات بتوقيت الرياض. مين الأسبق وطق طق وتحدي ألف ياء محسوبين من أول ما بلّشت اللعبة.
        ألف ياء (فرق) وآخر القائمة وقدّها؟ بيتسجلوا من وقت ما انضاف التسجيل إلهم.
        الجلسة اللي ما صار فيها شي لأكتر من ساعتين بتنحسب «ما كملت»، وأي جلسة بتنحسب مدتها لحد ٤ ساعات بالكتير.
    </p>
</x-filament-panels::page>
