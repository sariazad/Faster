@php
    $turn = $game->current_team;
    $finished = $game->isFinished();
    $other = $turn === 'a' ? 'b' : 'a';

    // ---- Hex geometry (pointy-top, odd rows shifted half a cell) ----
    $R = 44;
    $w = sqrt(3) * $R;
    $pad = 34;
    $W = 2 * $pad + $size * $w + $w / 2;
    $H = 2 * $pad + 2 * $R + ($size - 1) * 1.5 * $R;
    $cx = fn ($row, $col) => $pad + $w / 2 + $col * $w + ($row % 2 ? $w / 2 : 0);
    $cy = fn ($row) => $pad + $R + $row * 1.5 * $R;
    $hex = function ($x, $y, $r) {
        $pts = [];
        foreach ([-90, -30, 30, 90, 150, 210] as $deg) {
            $a = deg2rad($deg);
            $pts[] = round($x + $r * cos($a), 2).','.round($y + $r * sin($a), 2);
        }

        return implode(' ', $pts);
    };

    $teamColor = ['a' => '#22d3ee', 'b' => '#fb7185'];
    $teamDark = ['a' => '#0e7490', 'b' => '#be123c'];
    $currentCellId = $current?->board_cell_id;
@endphp

<main class="relative flex min-h-screen flex-col bg-paper text-night lg:h-screen lg:overflow-hidden">

    {{-- ============ Header ============ --}}
    <header class="relative z-10 shrink-0 bg-night text-white shadow-lg shadow-night/25">
        <div class="mx-auto grid max-w-[1700px] grid-cols-[1fr_auto_1fr] items-center gap-3 px-3 py-2.5 sm:px-6 sm:py-3">
            <div class="flex items-center justify-start">
                @unless ($finished)
                    <div @class([
                        'flex items-center gap-1 rounded-full py-1 pe-1 ps-4 text-sm font-extrabold ring-2 sm:text-base',
                        'bg-teamA/15 ring-teamA' => $turn === 'a',
                        'bg-teamB/15 ring-teamB' => $turn === 'b',
                    ])>
                        <span class="hidden text-white/70 sm:inline">الدور:</span>
                        <span class="max-w-[5rem] truncate sm:max-w-[9rem]">{{ $game->teamName($turn) }}</span>
                        <button type="button" wire:click="switchTurn" title="تبديل الدور"
                                class="ms-1 flex h-8 w-8 items-center justify-center rounded-full bg-white/10 transition hover:rotate-180 hover:bg-white/20">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3l4 4-4 4"/><path d="M3 11V9a2 2 0 0 1 2-2h16"/><path d="M7 21l-4-4 4-4"/><path d="M21 13v2a2 2 0 0 1-2 2H3"/></svg>
                        </button>
                    </div>
                @endunless
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('home') }}" class="text-xl sm:text-4xl" title="الرئيسية"><x-logo /></a>
                <span class="hidden rounded-full bg-lantern px-3 py-1 text-sm font-black text-night shadow sm:inline">🚪 طق طق</span>
            </div>

            <div class="flex items-center justify-end gap-2">
                @unless ($finished)
                    <button type="button" wire:click="finish" wire:confirm="متأكد تبي تنهي اللعبة الحين؟"
                            class="hidden rounded-full border-2 border-white/25 px-4 py-1.5 text-sm font-extrabold transition hover:border-lantern hover:text-lantern sm:block">
                        إنهاء اللعبة
                    </button>
                @endunless
                <a href="{{ route('taqtaq.new') }}" class="rounded-full bg-white px-4 py-1.5 text-sm font-extrabold text-night transition hover:bg-lantern">خروج</a>
            </div>
        </div>
        <div class="checker h-2.5"></div>
    </header>

    {{-- ============ Flash ============ --}}
    @if ($flash)
        <div wire:key="flash-{{ md5($flash.microtime()) }}"
             x-data="{ show: true }" x-init="setTimeout(() => show = false, 2500)" x-show="show" x-transition.opacity.duration.400ms
             class="pointer-events-none fixed inset-x-0 top-24 z-50 flex justify-center">
            <div class="animate-pop rounded-2xl bg-night px-6 py-3 text-lg font-extrabold text-lantern shadow-2xl ring-2 ring-lantern/50">{{ $flash }}</div>
        </div>
    @endif

    <div class="mx-auto grid w-full max-w-[1700px] flex-1 gap-4 px-3 py-4 sm:px-6 lg:min-h-0 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.35fr)]">

        {{-- =====================================================
             Right column (RTL first): question panel + teams/helpers
             ===================================================== --}}
        <section class="flex min-h-0 flex-col gap-4">

            <div class="relative flex min-h-[22rem] flex-1 flex-col overflow-hidden rounded-[2rem] bg-night text-white shadow-xl shadow-night/20">
                <div class="checker h-2 shrink-0"></div>

                <div class="flex flex-1 flex-col overflow-y-auto p-5 sm:p-6">
                    @if ($finished)
                        {{-- ---------- Winner ---------- --}}
                        @php $winner = $game->winner(); @endphp
                        <div class="flex flex-1 animate-pop flex-col items-center justify-center gap-4 text-center">
                            <div class="text-7xl">{{ $winner === 'tie' ? '🤝' : '🏆' }}</div>
                            @if ($winner === 'tie')
                                <h2 class="text-4xl font-black">انتهت اللعبة</h2>
                            @else
                                <h2 class="text-4xl font-black sm:text-5xl">
                                    <span @class(['text-teamA' => $winner === 'a', 'text-teamB' => $winner === 'b'])>{{ $game->teamName($winner) }}</span> وصّل!
                                </h2>
                                <p class="text-xl font-bold text-lantern">من طق طق… للسلام عليكم 🚪</p>
                            @endif
                            <div class="mt-2 grid w-full max-w-sm grid-cols-2 gap-3">
                                @foreach (['a', 'b'] as $team)
                                    <div @class(['rounded-2xl p-3', 'bg-teamA/15' => $team === 'a', 'bg-teamB/15' => $team === 'b'])>
                                        <div class="truncate text-sm font-bold text-white/70">{{ $game->teamName($team) }}</div>
                                        <div class="text-3xl font-black tabular-nums">{{ $counts[$team] }} <span class="text-base font-bold text-white/50">خلية</span></div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-2 flex flex-wrap justify-center gap-2">
                                <a href="{{ route('taqtaq.new') }}" class="rounded-2xl bg-lantern px-8 py-3 text-lg font-extrabold text-night hover:bg-lantern-light">لعبة جديدة</a>
                                <a href="{{ route('home') }}" class="rounded-2xl border border-white/15 px-8 py-3 text-lg font-bold hover:bg-white/5">الرئيسية</a>
                            </div>
                            <x-copyright class="mt-2 text-white/40" />
                        </div>

                    @elseif ($current)
                        {{-- ---------- Open question ---------- --}}
                        @php
                            $q = $current->question;
                            $opener = $current->opened_by;
                            $stealer = $opener === 'a' ? 'b' : 'a';
                        @endphp
                        <div wire:key="cq-{{ $current->id }}" class="flex flex-1 flex-col">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-sm font-extrabold">
                                    <span class="text-lg">{{ $current->cell->category->icon ?: '❓' }}</span>
                                    {{ $current->cell->category->name }}
                                </span>
                                <span @class([
                                    'rounded-full px-3 py-1 text-sm font-black text-night',
                                    'bg-teamA' => $opener === 'a',
                                    'bg-teamB' => $opener === 'b',
                                ])>دور {{ $game->teamName($opener) }}</span>
                            </div>

                            <div class="flex flex-1 flex-col items-center justify-center gap-5 py-5 text-center">
                                @if ($q->imageUrl())
                                    <img src="{{ $q->imageUrl() }}" alt="" class="max-h-[26vh] rounded-2xl object-contain shadow-2xl">
                                @endif

                                <h1 class="text-2xl font-extrabold leading-snug sm:text-4xl sm:leading-snug">{{ $q->body }}</h1>

                                @if ($q->audioUrl())
                                    <x-sound :src="$q->audioUrl()" label="صوت السؤال" />
                                @endif

                                @if ($revealed)
                                    <div class="flex animate-pop flex-col items-center gap-3 rounded-3xl border-2 border-lantern bg-lantern/10 px-6 py-4">
                                        <div class="text-sm font-bold text-lantern">الإجابة</div>
                                        @if (filled($q->answer))
                                            <div class="text-2xl font-black sm:text-3xl">{{ $q->answer }}</div>
                                        @endif
                                        @if ($q->answerImageUrl())
                                            <img src="{{ $q->answerImageUrl() }}" alt="" class="max-h-[22vh] rounded-2xl object-contain">
                                        @endif
                                        @if ($q->answerAudioUrl())
                                            <x-sound :src="$q->answerAudioUrl()" label="صوت الإجابة" />
                                        @endif
                                    </div>
                                @else
                                    {{-- Timer: main phase for the picking team, then the steal phase --}}
                                    <div wire:ignore wire:key="timer-{{ $current->id }}"
                                         x-data="questionTimer({{ (int) $game->answer_seconds }}, {{ (int) $game->steal_seconds }}, true)"
                                         @timer-stop.window="stop()"
                                         x-show="phase !== 'stopped'"
                                         class="flex flex-col items-center gap-1">
                                        <button type="button" @click="toggle()" class="relative h-28 w-28 sm:h-32 sm:w-32" title="إيقاف / تشغيل المؤقت">
                                            <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90">
                                                <circle cx="50" cy="50" r="44" fill="none" stroke="rgba(255,255,255,.08)" stroke-width="8"/>
                                                <circle cx="50" cy="50" r="44" fill="none" stroke-width="8" stroke-linecap="round"
                                                        :stroke="phase === 'steal' ? '{{ $teamColor[$stealer] }}' : (phase === 'over' ? '#64748b' : '#ffaa33')"
                                                        stroke-dasharray="276.46" :stroke-dashoffset="276.46 * (1 - progress)"
                                                        style="transition: stroke-dashoffset 1s linear"/>
                                            </svg>
                                            <span class="absolute inset-0 flex items-center justify-center text-4xl font-black tabular-nums"
                                                  :class="remaining <= 5 && phase !== 'over' ? 'text-teamB animate-pulse' : ''"
                                                  x-text="phase === 'over' ? '⏱' : remaining"></span>
                                        </button>
                                        <div class="text-base font-extrabold">
                                            <span x-show="phase === 'main'">وقت {{ $game->teamName($opener) }}<span x-show="paused" class="text-white/50"> (متوقف)</span></span>
                                            <span x-show="phase === 'steal'" style="display: none" class="{{ $stealer === 'a' ? 'text-teamA' : 'text-teamB' }}">فرصة سرقة لـ {{ $game->teamName($stealer) }}!</span>
                                            <span x-show="phase === 'over'" style="display: none" class="text-white/60">انتهى الوقت</span>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="flex flex-col items-center gap-3">
                                @if (! $revealed)
                                    <div class="flex w-full max-w-md gap-2">
                                        <button wire:click="reveal" type="button"
                                                class="flex-1 rounded-2xl bg-lantern py-3.5 text-lg font-extrabold text-night transition hover:bg-lantern-light">
                                            إظهار الإجابة
                                        </button>
                                        @if ($available($stealer, 'change'))
                                            <button wire:click="change" type="button" title="{{ $helpers['change']['hint'] }}"
                                                    class="rounded-2xl border border-white/20 px-4 text-sm font-extrabold transition hover:border-lantern hover:text-lantern">
                                                🔄 غيّره
                                                <span class="block text-[11px] font-bold text-white/50">{{ $game->teamName($stealer) }}</span>
                                            </button>
                                        @endif
                                    </div>
                                    <button wire:click="cancel" type="button" class="text-sm text-white/40 hover:text-white/80">رجوع للوحة بدون احتساب</button>
                                @else
                                    <div class="text-white/60">مين جاوب؟</div>
                                    <div class="grid w-full max-w-lg grid-cols-3 gap-2">
                                        @foreach (['a', 'b'] as $team)
                                            <button type="button" wire:click="award('{{ $team }}')"
                                                    @class([
                                                        'rounded-2xl py-3.5 text-base font-extrabold text-night transition hover:brightness-110',
                                                        'bg-teamA' => $team === 'a',
                                                        'bg-teamB' => $team === 'b',
                                                    ])>
                                                {{ $game->teamName($team) }}
                                                @if ($team !== $opener) <span class="block text-xs">(سرقة)</span> @endif
                                            </button>
                                        @endforeach
                                        <button wire:click="award('n')" type="button" class="rounded-2xl border border-white/20 py-3.5 text-base font-extrabold transition hover:bg-white/5">ولا أحد</button>
                                    </div>
                                    <button wire:click="$set('revealed', false)" type="button" class="text-sm text-white/40 hover:text-white/80">↩ ارجع للسؤال</button>
                                @endif
                            </div>
                        </div>

                    @else
                        {{-- ---------- Waiting for a cell ---------- --}}
                        <div class="flex flex-1 flex-col items-center justify-center gap-4 text-center">
                            @if ($targeting)
                                <div class="text-6xl">{{ $helpers[$targeting]['icon'] }}</div>
                                <h2 class="text-3xl font-black">{{ $helpers[$targeting]['label'] }}</h2>
                                <p class="max-w-sm text-lg font-bold text-white/70">
                                    {{ $targeting === 'erase' ? 'اختاروا خلية من خلايا '.$game->teamName($other).' عشان تنمسح.' : 'اختاروا خلية من خلاياكم عشان تنحمى.' }}
                                </p>
                                <button type="button" wire:click="target('{{ $targeting }}')" class="rounded-full border border-white/20 px-5 py-2 text-sm font-bold hover:bg-white/5">إلغاء</button>
                            @else
                                <div @class([
                                    'flex h-20 w-20 items-center justify-center rounded-3xl text-4xl shadow-lg',
                                    'bg-teamA text-night' => $turn === 'a',
                                    'bg-teamB text-night' => $turn === 'b',
                                ])>{{ $turn === 'a' ? '↕' : '↔' }}</div>
                                <h2 class="text-3xl font-black sm:text-4xl">دور {{ $game->teamName($turn) }}</h2>
                                <p class="max-w-sm text-lg font-bold text-white/70">
                                    طق طق! اختاروا خلية فاضية من اللوحة،
                                    وهدفكم توصلوا {{ $turn === 'a' ? 'من فوق لتحت' : 'من اليمين لليسار' }}.
                                </p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            {{-- ---------- Teams + helpers ---------- --}}
            <div class="grid shrink-0 grid-cols-[1fr_auto_1fr] items-stretch gap-2 sm:gap-3">
                @foreach (['a', 'b'] as $team)
                    @php $isTurn = ! $finished && $turn === $team; @endphp
                    <div @class([
                        'flex flex-col justify-center rounded-[1.5rem] px-3 py-2.5 text-white shadow-lg transition sm:px-4',
                        'order-1' => $team === 'a',
                        'order-3' => $team === 'b',
                        'bg-teamA-dark' => $team === 'a',
                        'bg-teamB-dark' => $team === 'b',
                        'ring-4 ring-lantern' => $isTurn,
                    ])>
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-sm font-black sm:text-lg">{{ $game->teamName($team) }}</span>
                            <span class="text-xl font-black" title="{{ $team === 'a' ? 'فوق ↕ تحت' : 'يمين ↔ يسار' }}">{{ $team === 'a' ? '↕' : '↔' }}</span>
                        </div>
                        <div class="mt-1 flex items-end justify-between gap-2">
                            <span class="text-3xl font-black tabular-nums sm:text-4xl">{{ $counts[$team] }}</span>
                            <span class="flex gap-1">
                                @foreach ($helpers as $key => $helper)
                                    <span title="{{ $helper['label'] }}" @class([
                                        'flex h-7 w-7 items-center justify-center rounded-lg text-sm',
                                        'bg-white/20' => $available($team, $key),
                                        'bg-black/20 opacity-30 grayscale' => ! $available($team, $key),
                                    ])>{{ $helper['icon'] }}</span>
                                @endforeach
                            </span>
                        </div>
                    </div>
                @endforeach

                <div class="order-2 flex flex-col justify-center gap-2">
                    @foreach (['erase', 'protect'] as $key)
                        @php $can = ! $finished && ! $current && $available($turn, $key); @endphp
                        <button type="button" @if ($can) wire:click="target('{{ $key }}')" @else disabled @endif
                                title="{{ $helpers[$key]['hint'] }}"
                                @class([
                                    'flex items-center gap-2 rounded-2xl px-3 py-2 text-sm font-extrabold shadow-sm ring-2 transition',
                                    'bg-lantern text-night ring-lantern' => $targeting === $key,
                                    'bg-white ring-lantern/50 hover:ring-lantern' => $can && $targeting !== $key,
                                    'cursor-not-allowed bg-night/5 text-night/30 ring-transparent' => ! $can && $targeting !== $key,
                                ])>
                            <span class="text-lg">{{ $helpers[$key]['icon'] }}</span>
                            {{ $helpers[$key]['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- =====================================================
             Left column: the board + legend
             ===================================================== --}}
        <section class="flex min-h-0 flex-col gap-3">
            <div class="flex min-h-0 flex-1 items-center justify-center overflow-hidden rounded-[2rem] bg-white p-2 shadow-xl shadow-night/10 ring-1 ring-night/10">
                <svg viewBox="0 0 {{ round($W, 2) }} {{ round($H, 2) }}" class="h-full max-h-[78vh] w-full" role="img" aria-label="لوحة طق طق">
                    <defs>
                        <filter id="glow" x="-50%" y="-50%" width="200%" height="200%">
                            <feGaussianBlur stdDeviation="5" result="b"/>
                            <feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
                        </filter>
                        <pattern id="checkA" width="14" height="14" patternUnits="userSpaceOnUse">
                            <rect width="14" height="14" fill="{{ $teamColor['a'] }}"/>
                            <rect width="7" height="7" fill="{{ $teamDark['a'] }}"/><rect x="7" y="7" width="7" height="7" fill="{{ $teamDark['a'] }}"/>
                        </pattern>
                        <pattern id="checkB" width="14" height="14" patternUnits="userSpaceOnUse">
                            <rect width="14" height="14" fill="{{ $teamColor['b'] }}"/>
                            <rect width="7" height="7" fill="{{ $teamDark['b'] }}"/><rect x="7" y="7" width="7" height="7" fill="{{ $teamDark['b'] }}"/>
                        </pattern>
                    </defs>

                    {{-- Frame: top/bottom belong to team A, left/right to team B --}}
                    @php $mx = $W / 2; $my = $H / 2; @endphp
                    <polygon points="0,0 {{ $W }},0 {{ $mx }},{{ $my }}" fill="{{ $teamColor['a'] }}" opacity=".22"/>
                    <polygon points="0,{{ $H }} {{ $W }},{{ $H }} {{ $mx }},{{ $my }}" fill="{{ $teamColor['a'] }}" opacity=".22"/>
                    <polygon points="0,0 0,{{ $H }} {{ $mx }},{{ $my }}" fill="{{ $teamColor['b'] }}" opacity=".22"/>
                    <polygon points="{{ $W }},0 {{ $W }},{{ $H }} {{ $mx }},{{ $my }}" fill="{{ $teamColor['b'] }}" opacity=".22"/>

                    {{-- Finish lines: racing-flag strips on each team's sides --}}
                    <rect x="{{ $pad }}" y="4" width="{{ $W - 2 * $pad }}" height="12" rx="6" fill="url(#checkA)"/>
                    <rect x="{{ $pad }}" y="{{ $H - 16 }}" width="{{ $W - 2 * $pad }}" height="12" rx="6" fill="url(#checkA)"/>
                    <rect x="4" y="{{ $pad }}" width="12" height="{{ $H - 2 * $pad }}" rx="6" fill="url(#checkB)"/>
                    <rect x="{{ $W - 16 }}" y="{{ $pad }}" width="12" height="{{ $H - 2 * $pad }}" rx="6" fill="url(#checkB)"/>

                    @foreach ($cells as $cell)
                        @php
                            $x = $cx($cell->row, $cell->col);
                            $y = $cy($cell->row);
                            $owner = $cell->owner;
                            $isCurrent = $cell->id === $currentCellId;
                            $inPath = isset($path[$cell->id]);
                            $clickable = ! $finished && ! $current && (
                                ($targeting === 'erase' && $owner === $other && ! $cell->protected)
                                || ($targeting === 'protect' && $owner === $turn && ! $cell->protected)
                                || (! $targeting && $owner === null)
                            );
                            $fill = $owner ? $teamColor[$owner] : '#fff7e6';
                        @endphp
                        <g wire:key="hex-{{ $cell->id }}"
                           @if ($clickable) wire:click="clickCell({{ $cell->id }})" role="button" tabindex="0" @endif
                           @class(['cursor-pointer [&:hover>polygon]:brightness-95' => $clickable])
                           style="{{ $finished && ! $inPath ? 'opacity:.55' : '' }}">
                            <title>{{ $cell->category->name }}</title>
                            <polygon points="{{ $hex($x, $y, $R - 2.5) }}"
                                     fill="{{ $fill }}"
                                     stroke="{{ $inPath ? '#ffaa33' : ($isCurrent ? '#ffaa33' : ($owner ? $teamDark[$owner] : '#120d2b')) }}"
                                     stroke-opacity="{{ $owner || $isCurrent || $inPath ? 1 : .22 }}"
                                     stroke-width="{{ $inPath ? 6 : ($isCurrent ? 6 : 2.5) }}"
                                     stroke-linejoin="round"
                                     @if ($inPath) filter="url(#glow)" @endif
                                     @class(['animate-pulse' => $isCurrent])/>
                            @if ($targeting && $clickable)
                                <polygon points="{{ $hex($x, $y, $R - 9) }}" fill="none" stroke="#ffaa33" stroke-width="3" stroke-dasharray="6 5"/>
                            @endif
                            <text x="{{ round($x, 2) }}" y="{{ round($y + 1, 2) }}" text-anchor="middle" dominant-baseline="central"
                                  font-size="{{ $R * 0.72 }}" style="pointer-events:none" @if ($owner) opacity=".9" @endif>{{ $cell->category->icon ?: '❓' }}</text>
                            @if ($cell->protected)
                                <text x="{{ round($x + $R * 0.42, 2) }}" y="{{ round($y - $R * 0.45, 2) }}" text-anchor="middle" dominant-baseline="central"
                                      font-size="{{ $R * 0.38 }}" style="pointer-events:none">🛡️</text>
                            @endif
                        </g>
                    @endforeach
                </svg>
            </div>

            {{-- Legend --}}
            <div class="flex shrink-0 flex-wrap items-center justify-center gap-2">
                @foreach ($legend as $category)
                    <span class="flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-sm font-extrabold shadow-sm ring-1 ring-night/10">
                        <span class="text-lg">{{ $category->icon ?: '❓' }}</span>
                        {{ $category->name }}
                        <span @class([
                            'h-2.5 w-2.5 rounded-full',
                            'bg-teamA' => $category->pivot->team === 'a',
                            'bg-teamB' => $category->pivot->team === 'b',
                        ])></span>
                    </span>
                @endforeach
            </div>
            <p class="text-center text-xs font-bold text-night/45">
                ⏱ الإجابة {{ $game->answer_seconds }} ث · السرقة {{ $game->steal_seconds }} ث
                <span class="mx-1">·</span>
                <span class="text-teamA-dark">{{ $game->teamName('a') }} ↕</span>
                <span class="mx-1">·</span>
                <span class="text-teamB-dark">{{ $game->teamName('b') }} ↔</span>
            </p>
            <x-copyright class="text-[11px] text-night/35" />
        </section>
    </div>
</main>
