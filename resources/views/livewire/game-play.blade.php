@php
    $turn = $game->current_team;
    $finished = $game->isFinished();
@endphp

<main class="mx-auto flex min-h-screen max-w-[1600px] flex-col px-3 py-4 sm:px-6">

    {{-- ============ Scoreboard ============ --}}
    <header class="grid grid-cols-[1fr_auto_1fr] items-stretch gap-3">
        @foreach (['a', 'b'] as $team)
            @php $isTurn = ! $finished && $turn === $team; @endphp
            <section @class([
                'relative rounded-3xl border-2 p-3 transition sm:p-4',
                'order-1' => $team === 'a',
                'order-3' => $team === 'b',
                'border-teamA bg-teamA/10 shadow-[0_0_40px_-10px] shadow-teamA' => $isTurn && $team === 'a',
                'border-teamB bg-teamB/10 shadow-[0_0_40px_-10px] shadow-teamB' => $isTurn && $team === 'b',
                'border-white/10 bg-night-800/70' => ! $isTurn,
            ])>
                <div class="flex items-center justify-between gap-2">
                    <h2 class="flex items-center gap-2 truncate text-lg font-extrabold sm:text-2xl">
                        <span @class(['h-3 w-3 shrink-0 rounded-full', 'bg-teamA' => $team === 'a', 'bg-teamB' => $team === 'b'])></span>
                        <span class="truncate">{{ $game->teamName($team) }}</span>
                    </h2>
                    @if ($isTurn)
                        <span @class([
                            'shrink-0 rounded-full px-2.5 py-0.5 text-xs font-black text-night sm:text-sm',
                            'bg-teamA' => $team === 'a',
                            'bg-teamB' => $team === 'b',
                        ])>دورهم</span>
                    @endif
                </div>

                <div class="mt-1 flex items-end justify-between gap-2">
                    <div class="text-4xl font-black tabular-nums sm:text-6xl">{{ $game->score($team) }}</div>
                    @unless ($finished)
                        <div class="flex gap-1 opacity-40 transition hover:opacity-100">
                            <button wire:click="adjust('{{ $team }}', -100)" class="h-8 w-10 rounded-lg bg-white/10 text-sm font-bold hover:bg-white/20" title="تصحيح النتيجة">−١٠٠</button>
                            <button wire:click="adjust('{{ $team }}', 100)" class="h-8 w-10 rounded-lg bg-white/10 text-sm font-bold hover:bg-white/20" title="تصحيح النتيجة">+١٠٠</button>
                        </div>
                    @endunless
                </div>

                <div class="mt-2 flex gap-1.5">
                    @foreach ($helpers as $key => $helper)
                        @php $available = $game->helperAvailable($team, $key); @endphp
                        <span title="{{ $helper['label'] }} — {{ $helper['hint'] }}"
                              @class([
                                  'flex h-8 min-w-8 items-center justify-center rounded-lg px-1.5 text-sm font-black',
                                  'bg-white/10 text-white' => $available,
                                  'bg-white/5 text-white/25 line-through' => ! $available,
                              ])>{{ $helper['icon'] }}</span>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="order-2 flex flex-col items-center justify-center gap-2 px-1">
            <a href="{{ route('home') }}" class="text-center text-lg font-black leading-tight sm:text-2xl">مين<br><span class="text-lantern">الأسبق</span></a>
            @unless ($finished)
                <button wire:click="finish" wire:confirm="متأكد تبي تنهي اللعبة الحين؟"
                        class="rounded-lg px-2 py-1 text-xs text-white/40 hover:bg-white/5 hover:text-white/80">إنهاء</button>
            @endunless
        </div>
    </header>

    {{-- ============ Flash ============ --}}
    @if ($flash)
        <div wire:key="flash-{{ md5($flash.microtime()) }}"
             x-data="{ show: true }" x-init="setTimeout(() => show = false, 2500)" x-show="show" x-transition.opacity.duration.400ms
             class="pointer-events-none fixed inset-x-0 top-6 z-50 flex justify-center">
            <div class="animate-pop rounded-2xl border border-lantern/40 bg-night-700/95 px-6 py-3 text-lg font-extrabold text-lantern-light shadow-2xl">
                {{ $flash }}
            </div>
        </div>
    @endif

    {{-- ============ Board ============ --}}
    <section class="mt-4 grid flex-1 grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        @foreach ($columns as $category)
            @php $columnCells = ($cells[$category->id] ?? collect())->sortBy([['points', 'asc'], ['slot', 'asc']]); @endphp
            <div class="flex flex-col gap-2" wire:key="col-{{ $category->id }}">
                <div class="relative flex min-h-24 flex-col items-center justify-center overflow-hidden rounded-2xl border border-white/10 bg-night-700 p-3 text-center">
                    @if ($category->imageUrl())
                        <img src="{{ $category->imageUrl() }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-25">
                    @endif
                    <span @class([
                        'absolute right-2 top-2 h-2 w-2 rounded-full',
                        'bg-teamA' => $category->pivot->team === 'a',
                        'bg-teamB' => $category->pivot->team === 'b',
                    ])></span>
                    <span class="relative text-3xl">{{ $category->icon ?: '❓' }}</span>
                    <span class="relative mt-1 text-base font-extrabold leading-tight sm:text-lg">{{ $category->name }}</span>
                </div>

                @foreach ($levels as $points)
                    <div class="grid flex-1 grid-cols-2 gap-2">
                        @foreach ($columnCells->where('points', $points) as $cell)
                            @php $pending = $cell->status === 'pending'; @endphp
                            <button type="button"
                                    wire:key="cell-{{ $cell->id }}"
                                    @if ($pending && ! $finished) wire:click="open({{ $cell->id }})" @else disabled @endif
                                    @class([
                                        'flex min-h-14 items-center justify-center rounded-xl text-xl font-black tabular-nums transition sm:text-2xl',
                                        'bg-lantern text-night hover:-translate-y-0.5 hover:bg-lantern-light hover:shadow-lg hover:shadow-lantern/30' => $pending,
                                        'bg-teamA/20 text-teamA/60' => $cell->answered_by === 'a',
                                        'bg-teamB/20 text-teamB/60' => $cell->answered_by === 'b',
                                        'bg-white/5 text-white/20' => $cell->answered_by === 'n',
                                    ])>
                                @if ($pending)
                                    {{ $points }}
                                @elseif ($cell->answered_by === 'n')
                                    ✕
                                @else
                                    ✓
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endforeach
    </section>

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
             class="fixed inset-0 z-40 overflow-y-auto bg-night/95 backdrop-blur-sm">
            <div class="mx-auto flex min-h-full max-w-5xl flex-col px-4 py-6 sm:py-10">

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
                         x-data="questionTimer({{ \App\Models\Game::MAIN_SECONDS }}, {{ \App\Models\Game::STEAL_SECONDS }}, @js(! $locked))"
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
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-night/90 p-4 backdrop-blur">
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
