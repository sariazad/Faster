<div class="min-h-screen bg-paper pb-56 text-night sm:pb-36">

    {{-- ============ Top bar ============ --}}
    <header class="sticky top-0 z-30 bg-night text-white shadow-lg shadow-night/20">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3">
            <a href="{{ route('home') }}" class="text-2xl font-black">مين <span class="text-lantern">الأسبق</span></a>

            <div class="flex items-center gap-2">
                <button wire:click="shuffle" type="button"
                        class="rounded-full border border-white/20 px-4 py-1.5 text-sm font-bold transition hover:border-lantern hover:text-lantern">
                    🎲 عشوائي
                </button>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 pt-6">

        {{-- ============ Team names + search ============ --}}
        <div class="grid gap-3 md:grid-cols-[1fr_1fr_1.2fr]">
            @foreach (['a' => 'teamA', 'b' => 'teamB'] as $team => $prop)
                <label @class([
                    'flex items-center gap-3 rounded-2xl border-2 bg-white px-4 py-2.5 shadow-sm transition',
                    'border-teamA' => $picking === $team && $team === 'a',
                    'border-teamB' => $picking === $team && $team === 'b',
                    'border-transparent' => $picking !== $team,
                ])>
                    <span @class([
                        'h-3.5 w-3.5 shrink-0 rounded-full',
                        'bg-teamA' => $team === 'a',
                        'bg-teamB' => $team === 'b',
                    ])></span>
                    <input type="text" wire:model.blur="{{ $prop }}" maxlength="40"
                           wire:focus="pickFor('{{ $team }}')"
                           placeholder="{{ $team === 'a' ? 'اسم الفريق الأول' : 'اسم الفريق الثاني' }}"
                           class="w-full border-0 bg-transparent p-0 text-lg font-extrabold text-night placeholder-night/30 focus:ring-0">
                </label>
            @endforeach

            <div class="relative">
                <input type="search" wire:model.live.debounce.250ms="search" placeholder="ابحث عن فئة…"
                       class="h-full w-full rounded-2xl border-0 bg-white py-2.5 pe-4 ps-11 text-lg font-bold text-night shadow-sm placeholder-night/35 focus:ring-2 focus:ring-lantern">
                <svg class="pointer-events-none absolute start-4 top-1/2 h-5 w-5 -translate-y-1/2 text-night/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/></svg>
            </div>
        </div>

        @error('teamA') <p class="mt-2 text-sm font-bold text-teamB-dark">{{ $message }}</p> @enderror
        @error('teamB') <p class="mt-2 text-sm font-bold text-teamB-dark">{{ $message }}</p> @enderror

        @if ($notice)
            <div class="mt-4 animate-pop rounded-2xl bg-lantern/20 px-5 py-3 text-center font-bold text-night">
                {{ $notice }}
            </div>
        @endif

        <p class="mb-4 mt-6 text-center text-base font-bold text-night/60">
            يختار الآن:
            <span @class([
                'rounded-full px-3 py-0.5 text-white',
                'bg-teamA-dark' => $picking === 'a',
                'bg-teamB-dark' => $picking === 'b',
            ])>{{ $picking === 'a' ? $teamA : $teamB }}</span>
        </p>

        {{-- ============ Category cards ============ --}}
        @if ($this->categories->count() < $perTeam * 2)
            <div class="rounded-3xl bg-white p-10 text-center text-night/70 shadow-sm">
                ما فيه فئات كافية للعب. لازم {{ $perTeam * 2 }} فئات على الأقل، وكل فئة فيها سؤالين لكل مستوى.
                <a href="/admin" class="mt-3 block font-extrabold text-lantern-dark">أضف أسئلة من لوحة التحكم ←</a>
            </div>
        @elseif ($this->visibleCategories->isEmpty())
            <div class="rounded-3xl bg-white p-10 text-center font-bold text-night/60 shadow-sm">ما فيه فئة بهالاسم.</div>
        @else
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                @foreach ($this->visibleCategories as $category)
                    @php
                        $owner = in_array($category->id, $selectedA, true) ? 'a' : (in_array($category->id, $selectedB, true) ? 'b' : null);
                    @endphp
                    <div wire:key="cat-{{ $category->id }}"
                         x-data="{ info: false }"
                         wire:click="toggle({{ $category->id }})"
                         role="button" tabindex="0"
                         @keydown.enter="$wire.toggle({{ $category->id }})"
                         @class([
                             'group relative cursor-pointer overflow-hidden rounded-3xl bg-card shadow-sm transition duration-200',
                             'ring-4 ring-teamA' => $owner === 'a',
                             'ring-4 ring-teamB' => $owner === 'b',
                             'hover:-translate-y-1 hover:shadow-xl hover:shadow-night/10' => ! $owner,
                         ])>

                        {{-- Badges --}}
                        <div class="absolute inset-x-0 top-0 z-10 flex items-start justify-between p-2.5">
                            @if ($owner)
                                <span @class([
                                    'max-w-[60%] truncate rounded-full px-2.5 py-1 text-xs font-black text-white shadow',
                                    'bg-teamA-dark' => $owner === 'a',
                                    'bg-teamB-dark' => $owner === 'b',
                                ])>{{ $owner === 'a' ? $teamA : $teamB }} ✓</span>
                            @else
                                <span class="rounded-full bg-night/80 px-2.5 py-1 text-xs font-black tabular-nums text-white"
                                      title="عدد الأسئلة">{{ $category->playableQuestionsCount() }}</span>
                            @endif

                            @if ($category->description)
                                <button type="button" @click.stop="info = ! info"
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-white/85 font-serif text-lg font-black italic text-lantern-dark shadow-sm transition hover:bg-white"
                                        title="عن الفئة">i</button>
                            @endif
                        </div>

                        {{-- Picture --}}
                        <div class="relative aspect-[4/5] overflow-hidden bg-gradient-to-b from-card to-lantern/25">
                            @if ($category->imageUrl())
                                <img src="{{ $category->imageUrl() }}" alt="{{ $category->name }}" loading="lazy"
                                     class="h-full w-full object-cover object-top transition duration-300 group-hover:scale-105">
                            @else
                                <div class="flex h-full items-center justify-center text-7xl transition duration-300 group-hover:scale-110">
                                    {{ $category->icon ?: '❓' }}
                                </div>
                            @endif

                            {{-- Description overlay --}}
                            @if ($category->description)
                                <div x-show="info" x-transition.opacity @click.stop="info = false"
                                     class="absolute inset-0 z-20 flex items-center justify-center bg-night/90 p-4 text-center text-sm font-bold leading-relaxed text-white"
                                     style="display: none">
                                    {{ $category->description }}
                                </div>
                            @endif
                        </div>

                        {{-- Name --}}
                        <div class="bg-white px-2 py-3 text-center">
                            <span class="block truncate text-base font-extrabold sm:text-lg">{{ $category->name }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </main>

    {{-- ============ Bottom bar: team slots + start ============ --}}
    <div class="fixed inset-x-0 bottom-0 z-30 px-3 pb-3 sm:px-4 sm:pb-4">
        <div class="mx-auto grid max-w-6xl grid-cols-2 items-center gap-2 sm:grid-cols-[1fr_auto_1fr] sm:gap-4">
            @foreach (['a', 'b'] as $team)
                @php
                    $name = $team === 'a' ? $teamA : $teamB;
                    $chosen = $slots[$team];
                    $active = $picking === $team;
                @endphp
                <div wire:click="pickFor('{{ $team }}')"
                     @class([
                         'flex cursor-pointer flex-col items-stretch gap-2 rounded-3xl bg-white/90 p-2.5 shadow-xl shadow-night/10 ring-2 backdrop-blur transition sm:flex-row sm:items-center sm:justify-between sm:p-3',
                         'order-1' => $team === 'a',
                         'order-2 sm:order-3' => $team === 'b',
                         'ring-teamA' => $active && $team === 'a',
                         'ring-teamB' => $active && $team === 'b',
                         'ring-transparent' => ! $active,
                     ])>
                    <div class="flex min-w-0 items-baseline justify-between gap-2 px-1 sm:block">
                        <div @class([
                            'truncate text-sm font-black sm:text-base',
                            'text-teamA-dark' => $team === 'a',
                            'text-teamB-dark' => $team === 'b',
                        ])>{{ $name }}</div>
                        <div class="text-xs font-bold tabular-nums text-night/50">{{ $chosen->count() }}/{{ $perTeam }}</div>
                    </div>

                    <div class="flex shrink-0 justify-center gap-1.5">
                        @for ($i = 0; $i < $perTeam; $i++)
                            @php $slot = $chosen->get($i); @endphp
                            @if ($slot)
                                <button type="button" wire:key="slot-{{ $team }}-{{ $slot->id }}"
                                        wire:click.stop="remove('{{ $team }}', {{ $slot->id }})"
                                        title="شيل {{ $slot->name }}"
                                        @class([
                                            'group/slot relative h-11 w-11 overflow-hidden rounded-xl border-2 bg-card sm:h-14 sm:w-14',
                                            'border-teamA' => $team === 'a',
                                            'border-teamB' => $team === 'b',
                                        ])>
                                    @if ($slot->imageUrl())
                                        <img src="{{ $slot->imageUrl() }}" alt="" class="h-full w-full object-cover object-top">
                                    @else
                                        <span class="flex h-full items-center justify-center text-2xl">{{ $slot->icon ?: '❓' }}</span>
                                    @endif
                                    <span class="absolute inset-0 hidden items-center justify-center bg-night/70 text-xl font-black text-white group-hover/slot:flex">✕</span>
                                </button>
                            @else
                                <span @class([
                                    'flex h-11 w-11 items-center justify-center rounded-xl border-2 border-dashed text-2xl font-black sm:h-14 sm:w-14',
                                    'border-teamA-dark/40 text-teamA-dark' => $team === 'a',
                                    'border-teamB-dark/40 text-teamB-dark' => $team === 'b',
                                ])>+</span>
                            @endif
                        @endfor
                    </div>
                </div>
            @endforeach

            <button wire:click="start" type="button" @disabled(! $ready)
                    class="order-3 col-span-2 rounded-full bg-night px-10 py-3.5 text-xl sm:py-4 font-extrabold text-lantern shadow-xl shadow-night/30 transition enabled:hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-40 sm:order-2 sm:col-span-1">
                <span wire:loading.remove wire:target="start">يلا نبدأ</span>
                <span wire:loading wire:target="start">لحظة…</span>
            </button>
        </div>
    </div>
</div>
