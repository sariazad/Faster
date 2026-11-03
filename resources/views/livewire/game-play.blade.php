@php
    $turn = $game->current_team;
    $finished = $game->isFinished();
@endphp

<main class="relative flex min-h-screen flex-col bg-paper text-night lg:h-screen lg:overflow-hidden">

    {{-- ============ Header ============ --}}
    <header class="relative z-10 shrink-0 bg-night text-white shadow-lg shadow-night/25">
        <div class="mx-auto grid max-w-[1700px] grid-cols-[1fr_auto_1fr] items-center gap-3 px-3 py-2.5 sm:px-6 sm:py-3">

            {{-- Turn --}}
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

            {{-- Logo --}}
            <a href="{{ route('home') }}" class="text-xl sm:text-4xl" title="الرئيسية"><x-logo /></a>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-2">
                @unless ($finished)
                    <button type="button" wire:click="finish" wire:confirm="متأكد تبي تنهي اللعبة الحين؟"
                            class="hidden rounded-full border-2 border-white/25 px-4 py-1.5 text-sm font-extrabold transition hover:border-lantern hover:text-lantern sm:block">
                        إنهاء اللعبة
                    </button>
                @endunless
                <a href="{{ route('game.new') }}"
                   class="rounded-full bg-white px-4 py-1.5 text-sm font-extrabold text-night transition hover:bg-lantern">
                    خروج
                </a>
            </div>
        </div>
        <div class="checker h-2.5"></div>
    </header>

    {{-- ============ Flash ============ --}}
    @if ($flash)
        <div wire:key="flash-{{ md5($flash.microtime()) }}"
             x-data="{ show: true }" x-init="setTimeout(() => show = false, 2500)" x-show="show" x-transition.opacity.duration.400ms
             class="pointer-events-none fixed inset-x-0 top-24 z-50 flex justify-center">
            <div class="animate-pop rounded-2xl bg-night px-6 py-3 text-lg font-extrabold text-lantern shadow-2xl ring-2 ring-lantern/50">
                {{ $flash }}
            </div>
        </div>
    @endif

    {{-- ============ Board ============ --}}
    <section class="mx-auto grid w-full max-w-[1700px] flex-1 grid-cols-2 gap-3 px-3 py-4 sm:grid-cols-3 sm:gap-4 sm:px-6 lg:min-h-0 xl:grid-cols-6">
        @foreach ($columns as $category)
            @php $columnCells = ($cells[$category->id] ?? collect())->sortBy([['points', 'asc'], ['slot', 'asc']]); @endphp
            <article wire:key="col-{{ $category->id }}"
                     class="flex min-h-0 flex-col overflow-hidden rounded-[1.75rem] bg-white shadow-[0_6px_0_0_rgba(18,13,43,0.10)] ring-1 ring-night/10">

                {{-- Picture + name --}}
                <div class="relative aspect-[4/3] overflow-hidden bg-gradient-to-b from-card to-lantern/30 lg:aspect-auto lg:min-h-[8rem] lg:flex-1">
                    @if ($category->imageUrl())
                        <img src="{{ $category->imageUrl() }}" alt="{{ $category->name }}" class="absolute inset-0 h-full w-full object-cover object-top">
                    @else
                        <div class="absolute inset-0 flex items-center justify-center pt-6 text-6xl xl:text-7xl">{{ $category->icon ?: '❓' }}</div>
                    @endif

                    <div class="absolute inset-x-2 top-2 flex justify-center">
                        <span class="max-w-full truncate rounded-full bg-night px-3 py-1 text-xs font-extrabold text-lantern shadow-md sm:text-sm">
                            {{ $category->name }}
                        </span>
                    </div>

                    <span title="اختارها {{ $game->teamName($category->pivot->team) }}"
                          @class([
                              'absolute bottom-2 start-2 h-3 w-3 rounded-full ring-2 ring-white',
                              'bg-teamA' => $category->pivot->team === 'a',
                              'bg-teamB' => $category->pivot->team === 'b',
                          ])></span>
                </div>

                {{-- Point tiles --}}
                <div class="grid shrink-0 gap-[3px] bg-white p-[3px]">
                    @foreach ($levels as $points)
                        <div class="grid grid-cols-2 gap-[3px]">
                            @for ($slot = 1; $slot <= $perLevel; $slot++)
                                @php $cell = $columnCells->first(fn ($c) => $c->points === $points && $c->slot === $slot); @endphp
                                @if (! $cell)
                                    <span class="flex h-10 items-center justify-center bg-night/5 text-night/20 sm:h-11 2xl:h-14" title="ما في سؤال كافي بهالمستوى">—</span>
                                    @continue
                                @endif
                                @php $pending = $cell->status === 'pending'; @endphp
                                <button type="button"
                                        wire:key="cell-{{ $cell->id }}"
                                        @if ($pending && ! $finished) wire:click="open({{ $cell->id }})" @else disabled @endif
                                        @class([
                                            'flex h-10 items-center justify-center text-base font-black tabular-nums transition sm:h-11 sm:text-lg 2xl:h-14 2xl:text-xl',
                                            'hover:brightness-110 active:scale-95' => $pending && ! $finished,
                                            'bg-level-200 text-night' => $pending && $points === 200,
                                            'bg-level-400 text-night' => $pending && $points === 400,
                                            'bg-level-600 text-white' => $pending && $points === 600,
                                            'bg-teamA/15 text-teamA-dark' => $cell->answered_by === 'a',
                                            'bg-teamB/15 text-teamB-dark' => $cell->answered_by === 'b',
                                            'bg-night/5 text-night/25' => $cell->answered_by === 'n',
                                        ])>
                                    @if ($pending)
                                        {{ $points }}
                                    @elseif ($cell->answered_by === 'n')
                                        ✕
                                    @else
                                        ✓ <span class="ms-1 text-xs font-bold opacity-70">{{ $points }}</span>
                                    @endif
                                </button>
                            @endfor
                        </div>
                    @endforeach
                </div>
            </article>
        @endforeach
    </section>

    {{-- ============ Bottom bar: scores + helpers ============ --}}
    @php
        $allCells = $cells->flatten(1);
        $answeredCount = $allCells->where('status', 'answered')->count();
    @endphp
    <footer class="mx-auto grid w-full max-w-[1700px] shrink-0 grid-cols-2 items-end gap-3 px-3 pb-4 sm:px-6 lg:grid-cols-[minmax(0,20rem)_1fr_minmax(0,20rem)]">
        @foreach (['a', 'b'] as $team)
            @php $isTurn = ! $finished && $turn === $team; @endphp
            <div @class([
                'rounded-[1.5rem] p-2.5 text-white shadow-xl transition sm:rounded-[2rem] sm:p-4',
                'order-1' => $team === 'a',
                'order-2 lg:order-3' => $team === 'b',
                'bg-teamA-dark shadow-teamA-dark/30' => $team === 'a',
                'bg-teamB-dark shadow-teamB-dark/30' => $team === 'b',
                'ring-4 ring-lantern' => $isTurn,
            ])>
                <div class="mb-2 flex items-center justify-between gap-2 px-1">
                    <h2 class="truncate text-base font-black sm:text-xl">{{ $game->teamName($team) }}</h2>
                    <div class="hidden shrink-0 gap-1 sm:flex">
                        @foreach ($helpers as $key => $helper)
                            @php $available = $game->helperAvailable($team, $key); @endphp
                            <span title="{{ $helper['label'] }} — {{ $available ? 'متاحة' : 'مستخدمة' }}"
                                  @class([
                                      'flex h-7 min-w-7 items-center justify-center rounded-lg px-1 text-xs font-black',
                                      'bg-white/20' => $available,
                                      'bg-black/15 text-white/35 line-through' => ! $available,
                                  ])>{{ $helper['icon'] }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center gap-1.5 rounded-full bg-black/15 p-1 sm:gap-2 sm:p-1.5">
                    <button type="button" @if (! $finished) wire:click="adjust('{{ $team }}', 100)" @else disabled @endif
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/15 text-xl font-black sm:h-10 sm:w-10 sm:text-2xl transition hover:bg-white/30 disabled:opacity-30"
                            title="+١٠٠">+</button>
                    <div class="min-w-0 flex-1 rounded-full bg-white py-1 text-center text-2xl font-black tabular-nums text-night sm:text-4xl">
                        {{ $game->score($team) }}
                    </div>
                    <button type="button" @if (! $finished) wire:click="adjust('{{ $team }}', -100)" @else disabled @endif
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/15 text-xl font-black sm:h-10 sm:w-10 sm:text-2xl transition hover:bg-white/30 disabled:opacity-30"
                            title="−١٠٠">−</button>
                </div>
            </div>
        @endforeach

        {{-- Center: current team's helpers + progress --}}
        <div class="order-3 col-span-2 flex flex-col items-center gap-3 lg:order-2 lg:col-span-1">
            @unless ($finished)
                <div class="flex flex-wrap justify-center gap-2 sm:gap-3">
                    @foreach ($helpers as $key => $helper)
                        @php $available = $game->helperAvailable($turn, $key); @endphp
                        <div title="{{ $helper['hint'] }}"
                             @class([
                                 'flex items-center gap-2 rounded-2xl px-3 py-2 shadow-sm ring-2 transition sm:px-4',
                                 'bg-white ring-lantern/60' => $available,
                                 'bg-night/5 text-night/30 ring-transparent' => ! $available,
                             ])>
                            <span @class([
                                'flex h-9 w-9 items-center justify-center rounded-xl text-sm font-black',
                                'bg-lantern text-night' => $available,
                                'bg-night/10' => ! $available,
                            ])>{{ $helper['icon'] }}</span>
                            <span class="text-sm font-extrabold">{{ $helper['label'] }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="text-xs font-bold text-night/45">وسائل {{ $game->teamName($turn) }} — تُستخدم من داخل السؤال</p>
            @endunless

            <div class="flex w-full max-w-md items-center gap-3">
                <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-night/10">
                    <div class="h-full rounded-full bg-gradient-to-l from-level-600 via-level-400 to-level-200 transition-all duration-500"
                         style="width: {{ $allCells->count() ? round($answeredCount / $allCells->count() * 100) : 0 }}%"></div>
                </div>
                <span class="text-sm font-black tabular-nums text-night/60">{{ $answeredCount }}/{{ $allCells->count() }}</span>
            </div>
            <p class="text-xs font-bold text-night/45">
                ⏱ الإجابة {{ $game->answer_seconds }} ث · السرقة {{ $game->steal_seconds }} ث
            </p>
        </div>
    </footer>

    {{-- ============ Question ============ --}}
    @if ($current)
        @php
            $opener = $current->opened_by;
            $other = $opener === 'a' ? 'b' : 'a';
            $double = $current->hasEffect('double');
            $locked = $current->hasEffect('no_steal');
            $q = $current->question;
        @endphp

        <div wire:key="question-{{ $current->id }}"
             class="fixed inset-0 z-40 flex flex-col overflow-y-auto bg-night text-white">
            <div class="checker h-2.5 shrink-0"></div>
            <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col px-4 py-6 sm:py-10">

                {{-- Header --}}
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="text-3xl">{{ $current->category->icon ?: '❓' }}</span>
                        <span class="text-xl font-extrabold sm:text-2xl">{{ $current->category->name }}</span>
                        <span class="rounded-full bg-lantern px-3 py-1 text-lg font-black tabular-nums text-night">
                            {{ $double ? $current->points * 2 : $current->points }}
                        </span>
                        @if ($double)
                            <span class="rounded-full bg-lantern/15 px-2 py-1 text-sm font-black text-lantern">دبل ×٢</span>
                        @endif
                        @if ($locked)
                            <span class="rounded-full bg-white/10 px-2 py-1 text-sm font-black">🔒 مقفول</span>
                        @endif
                    </div>
                    <span @class([
                        'rounded-full px-4 py-1.5 text-base font-black text-night',
                        'bg-teamA' => $opener === 'a',
                        'bg-teamB' => $opener === 'b',
                    ])>دور {{ $game->teamName($opener) }}</span>
                </div>

                {{-- Body --}}
                <div class="flex flex-1 flex-col items-center justify-center gap-8 py-8 text-center">
                    @if ($q->imageUrl())
                        <div x-data="{ zoom: false }" wire:key="qimg-{{ $current->id }}">
                            <img src="{{ $q->imageUrl() }}" alt="" @click="zoom = true"
                                 @class([
                                     'cursor-zoom-in rounded-2xl object-contain shadow-2xl transition-all',
                                     'max-h-[40vh]' => ! $revealed,
                                     'max-h-[22vh]' => $revealed,
                                 ])>
                            <div x-show="zoom" x-transition.opacity @click="zoom = false" style="display: none"
                                 class="fixed inset-0 z-[60] flex cursor-zoom-out items-center justify-center bg-night/95 p-6">
                                <img src="{{ $q->imageUrl() }}" alt="" class="max-h-full max-w-full rounded-2xl object-contain">
                            </div>
                        </div>
                    @endif

                    <h1 class="text-3xl font-extrabold leading-snug sm:text-5xl sm:leading-snug">{{ $q->body }}</h1>

                    @if ($q->audioUrl())
                        <x-sound :src="$q->audioUrl()" label="صوت السؤال" />
                    @endif

                    @if ($revealed)
                        <div class="flex animate-pop flex-col items-center gap-4 rounded-3xl border-2 border-lantern bg-lantern/10 px-8 py-5">
                            <div class="text-sm font-bold text-lantern">الإجابة</div>
                            @if (filled($q->answer))
                                <div class="text-3xl font-black sm:text-4xl">{{ $q->answer }}</div>
                            @endif
                            @if ($q->answerImageUrl())
                                <img src="{{ $q->answerImageUrl() }}" alt="" class="max-h-[35vh] rounded-2xl object-contain shadow-2xl">
                            @endif
                            @if ($q->answerAudioUrl())
                                <x-sound :src="$q->answerAudioUrl()" label="صوت الإجابة" />
                            @endif
                        </div>
                    @endif

                    {{-- Timer (kept outside Livewire morphing so it keeps ticking) --}}
                    <div wire:ignore wire:key="timer-{{ $current->id }}"
                         x-data="questionTimer({{ (int) $game->answer_seconds }}, {{ (int) $game->steal_seconds }}, @js(! $locked))"
                         @timer-add.window="add($event.detail.seconds)"
                         @timer-lock.window="lock()"
                         @timer-stop.window="stop()"
                         x-show="phase !== 'stopped'"
                         class="flex flex-col items-center gap-2">
                        <button type="button" @click="toggle()" class="relative h-36 w-36 sm:h-44 sm:w-44" title="إيقاف / تشغيل المؤقت">
                            <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90">
                                <circle cx="50" cy="50" r="44" fill="none" stroke="rgba(255,255,255,.08)" stroke-width="8"/>
                                <circle cx="50" cy="50" r="44" fill="none" stroke-width="8" stroke-linecap="round"
                                        :stroke="phase === 'steal' ? '{{ $other === 'a' ? '#22d3ee' : '#fb7185' }}' : (phase === 'over' ? '#64748b' : '#ffaa33')"
                                        stroke-dasharray="276.46"
                                        :stroke-dashoffset="276.46 * (1 - progress)"
                                        style="transition: stroke-dashoffset 1s linear"/>
                            </svg>
                            <span class="absolute inset-0 flex items-center justify-center text-5xl font-black tabular-nums sm:text-6xl"
                                  :class="remaining <= 5 && phase !== 'over' ? 'text-teamB animate-pulse' : ''"
                                  x-text="phase === 'over' ? '⏱' : remaining"></span>
                        </button>
                        <div class="text-lg font-extrabold">
                            <span x-show="phase === 'main'">وقت {{ $game->teamName($opener) }}<span x-show="paused" class="text-white/50"> (متوقف)</span></span>
                            <span x-show="phase === 'steal'" class="{{ $other === 'a' ? 'text-teamA' : 'text-teamB' }}">فرصة سرقة لـ {{ $game->teamName($other) }}!</span>
                            <span x-show="phase === 'over'" class="text-white/60">انتهى الوقت</span>
                        </div>
                    </div>
                </div>

                {{-- Helpers for the picking team --}}
                @unless ($revealed)
                    <div class="mb-6 flex flex-wrap justify-center gap-2">
                        @foreach ($helpers as $key => $helper)
                            @php $applied = $current->hasEffect($key); @endphp
                            @php $available = $game->helperAvailable($opener, $key); @endphp
                            <button type="button"
                                    @if ($available) wire:click="useHelper('{{ $key }}')" @else disabled @endif
                                    title="{{ $helper['hint'] }}"
                                    @class([
                                        'flex items-center gap-2 rounded-xl border px-4 py-2 font-bold transition',
                                        'border-lantern bg-lantern/20 text-lantern-light' => $applied,
                                        'border-white/15 hover:border-lantern/60 hover:bg-white/5' => $available,
                                        'cursor-not-allowed border-white/5 text-white/25' => ! $available && ! $applied,
                                    ])>
                                <span class="text-lg font-black">{{ $helper['icon'] }}</span>
                                {{ $helper['label'] }}
                                @if ($applied) <span class="text-xs">✓</span> @endif
                            </button>
                        @endforeach
                    </div>
                @endunless

                {{-- Host controls --}}
                <div class="flex flex-col items-center gap-3">
                    @if (! $revealed)
                        <button wire:click="reveal" type="button"
                                class="w-full max-w-md rounded-2xl bg-lantern py-4 text-xl font-extrabold text-night transition hover:bg-lantern-light">
                            إظهار الإجابة
                        </button>
                        <button wire:click="cancel" type="button" class="text-sm text-white/40 hover:text-white/80">
                            رجوع للوحة بدون احتساب
                        </button>
                    @else
                        <div class="text-white/60">مين جاوب صح؟</div>
                        <div class="grid w-full max-w-2xl grid-cols-1 gap-3 sm:grid-cols-3">
                            @foreach (['a', 'b'] as $team)
                                @php $blocked = $locked && $team !== $opener; @endphp
                                <button type="button"
                                        @if ($blocked) disabled title="السؤال مقفول" @else wire:click="award('{{ $team }}')" @endif
                                        @class([
                                            'rounded-2xl py-4 text-lg font-extrabold text-night transition disabled:cursor-not-allowed disabled:opacity-30',
                                            'bg-teamA hover:brightness-110' => $team === 'a',
                                            'bg-teamB hover:brightness-110' => $team === 'b',
                                        ])>
                                    {{ $game->teamName($team) }}
                                    @if ($team !== $opener) <span class="text-sm">(سرقة)</span> @endif
                                </button>
                            @endforeach
                            <button wire:click="award('n')" type="button"
                                    class="rounded-2xl border border-white/20 py-4 text-lg font-extrabold transition hover:bg-white/5">
                                ولا أحد
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- ============ Results ============ --}}
    @if ($finished)
        @php $winner = $game->winner(); @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-night/90 p-4 text-white backdrop-blur">
            <div class="w-full max-w-xl animate-pop rounded-3xl border border-lantern/40 bg-night-800 p-8 text-center shadow-2xl">
                <div class="text-6xl">{{ $winner === 'tie' ? '🤝' : '🏆' }}</div>
                <h2 class="mt-4 text-4xl font-black">
                    @if ($winner === 'tie')
                        تعادل!
                    @else
                        <span @class(['text-teamA' => $winner === 'a', 'text-teamB' => $winner === 'b'])>{{ $game->teamName($winner) }}</span> فاز!
                    @endif
                </h2>

                <div class="mt-6 grid grid-cols-2 gap-3">
                    @foreach (['a', 'b'] as $team)
                        <div @class([
                            'rounded-2xl p-4',
                            'bg-teamA/15' => $team === 'a',
                            'bg-teamB/15' => $team === 'b',
                        ])>
                            <div class="truncate font-bold text-white/70">{{ $game->teamName($team) }}</div>
                            <div class="text-4xl font-black tabular-nums">{{ $game->score($team) }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-8 flex flex-col gap-2 sm:flex-row sm:justify-center">
                    <a href="{{ route('game.new') }}" class="rounded-2xl bg-lantern px-8 py-3 text-lg font-extrabold text-night hover:bg-lantern-light">لعبة جديدة</a>
                    <a href="{{ route('home') }}" class="rounded-2xl border border-white/15 px-8 py-3 text-lg font-bold hover:bg-white/5">الرئيسية</a>
                </div>
            </div>
        </div>
    @endif
</main>
