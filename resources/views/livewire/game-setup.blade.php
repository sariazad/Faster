<main class="mx-auto max-w-6xl px-4 py-8 sm:py-12">
    <header class="mb-8 flex items-center justify-between">
        <a href="{{ route('home') }}" class="text-2xl font-black">مين <span class="text-lantern">الأسبق</span></a>
        <button wire:click="shuffle" type="button"
                class="rounded-xl border border-white/15 px-4 py-2 font-bold text-white/80 transition hover:border-lantern/50 hover:text-lantern">
            🎲 اختيار عشوائي
        </button>
    </header>

    {{-- Teams --}}
    <div class="grid gap-4 md:grid-cols-2">
        @foreach (['a' => ['teamA', 'selectedA'], 'b' => ['teamB', 'selectedB']] as $team => [$nameProp, $selProp])
            @php $active = $picking === $team; @endphp
            <div wire:click="pickFor('{{ $team }}')"
                 @class([
                     'cursor-pointer rounded-3xl border-2 p-5 transition',
                     'border-teamA bg-teamA/10 shadow-[0_0_30px_-8px] shadow-teamA' => $active && $team === 'a',
                     'border-teamB bg-teamB/10 shadow-[0_0_30px_-8px] shadow-teamB' => $active && $team === 'b',
                     'border-white/10 bg-night-800/60 hover:border-white/25' => ! $active,
                 ])>
                <div class="flex items-center gap-3">
                    <span @class([
                        'h-4 w-4 shrink-0 rounded-full',
                        'bg-teamA' => $team === 'a',
                        'bg-teamB' => $team === 'b',
                    ])></span>
                    <input type="text" wire:model.blur="{{ $nameProp }}" maxlength="40"
                           wire:click.stop
                           class="w-full rounded-xl border-0 bg-white/5 px-3 py-2 text-xl font-extrabold text-white placeholder-white/30 focus:ring-2 focus:ring-lantern">
                </div>
                @error($nameProp) <p class="mt-2 text-sm text-teamB">{{ $message }}</p> @enderror

                <div class="mt-4 flex items-center justify-between text-sm">
                    <span class="text-white/60">
                        @if ($active) يختار الآن — @endif
                        {{ count($this->{$selProp}) }} من {{ $perTeam }} فئات
                    </span>
                    <div class="flex gap-1.5">
                        @for ($i = 0; $i < $perTeam; $i++)
                            <span @class([
                                'h-2 w-8 rounded-full',
                                'bg-teamA' => $i < count($this->{$selProp}) && $team === 'a',
                                'bg-teamB' => $i < count($this->{$selProp}) && $team === 'b',
                                'bg-white/10' => $i >= count($this->{$selProp}),
                            ])></span>
                        @endfor
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($notice)
        <div class="mt-6 animate-pop rounded-2xl border border-lantern/40 bg-lantern/10 px-5 py-3 text-center font-bold text-lantern-light">
            {{ $notice }}
        </div>
    @endif

    {{-- Categories --}}
    <h2 class="mb-4 mt-10 text-xl font-extrabold text-white/80">الفئات</h2>

    @if ($this->categories->count() < $perTeam * 2)
        <div class="rounded-2xl border border-white/10 bg-night-800/60 p-8 text-center text-white/70">
            ما فيه فئات كافية للعب. لازم {{ $perTeam * 2 }} فئات على الأقل، وكل فئة فيها سؤالين لكل مستوى.
            <a href="/admin" class="mt-3 block font-bold text-lantern">أضف أسئلة من لوحة التحكم ←</a>
        </div>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($this->categories as $category)
                @php $inA = in_array($category->id, $selectedA, true); @endphp
                @php $inB = in_array($category->id, $selectedB, true); @endphp
                <button type="button" wire:click="toggle({{ $category->id }})" wire:key="cat-{{ $category->id }}"
                        @class([
                            'group relative flex aspect-[4/3] flex-col items-center justify-center gap-2 overflow-hidden rounded-2xl border-2 p-3 text-center transition',
                            'border-teamA bg-teamA/15' => $inA,
                            'border-teamB bg-teamB/15' => $inB,
                            'border-white/10 bg-night-800/70 hover:-translate-y-0.5 hover:border-white/30' => ! $inA && ! $inB,
                        ])>
                    @if ($category->imageUrl())
                        <img src="{{ $category->imageUrl() }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-30 transition group-hover:opacity-40">
                    @endif
                    <span class="relative text-4xl">{{ $category->icon ?: '❓' }}</span>
                    <span class="relative text-lg font-extrabold leading-tight">{{ $category->name }}</span>

                    @if ($inA || $inB)
                        <span @class([
                            'absolute left-2 top-2 rounded-full px-2 py-0.5 text-xs font-black text-night',
                            'bg-teamA' => $inA,
                            'bg-teamB' => $inB,
                        ])>{{ $inA ? $teamA : $teamB }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    @endif

    <div class="sticky bottom-4 mt-10 flex justify-center">
        <button wire:click="start" type="button" @disabled(! $ready)
                class="rounded-2xl bg-lantern px-12 py-4 text-xl font-extrabold text-night shadow-2xl shadow-lantern/20 transition enabled:hover:bg-lantern-light disabled:cursor-not-allowed disabled:opacity-40">
            <span wire:loading.remove wire:target="start">يلا نبدأ</span>
            <span wire:loading wire:target="start">لحظة…</span>
        </button>
    </div>
</main>
