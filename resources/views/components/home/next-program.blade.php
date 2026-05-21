@props(['program'])

@php
    use App\Support\PublicMediaUrl;

    $resolveProgramAsset = static function (array $item): array {
        $image = (string) data_get($item, 'image', '');
        $resolvedImage = PublicMediaUrl::normalizePublicUrl($image);

        if ($resolvedImage === '') {
            $resolvedImage = '';
        }

        return array_merge($item, [
            'image' => $resolvedImage !== '' ? $resolvedImage : ($image !== '' ? asset($image) : asset('assets/lucille/pedalboard-1511069_1920.jpg')),
        ]);
    };

    $heroProgram = $resolveProgramAsset($program);
    $upcomingPrograms = collect(data_get($program, 'upcoming', []))
        ->map(fn (array $slot) => $resolveProgramAsset($slot))
        ->values()
        ->all();
@endphp

<div
    class="mt-[60px] grid gap-6 lg:grid-cols-[1.15fr_.85fr]"
    x-data="{
        activeProgram: @js($heroProgram),
        originalProgram: @js($heroProgram),
        restoreTimer: null,
        programKey(program) {
            return [program?.title || '', program?.host || '', program?.schedule || '', program?.image || ''].join('|');
        },
        isActiveProgram(program) {
            return this.programKey(this.activeProgram) === this.programKey(program);
        },
        selectProgram(program) {
            this.activeProgram = program;
            window.clearTimeout(this.restoreTimer);
            this.restoreTimer = window.setTimeout(() => {
                this.activeProgram = this.originalProgram;
            }, 7000);
        },
        infoModalOpen: false,
        openInfoModal() {
            this.infoModalOpen = true;
        },
        closeInfoModal() {
            this.infoModalOpen = false;
        },
    }"
>
    <article class="home-panel group overflow-hidden">
        <div class="p-4 md:p-6">
            <div class="overflow-hidden border border-[#2b2b2b] bg-[#111]">
                <img
                    :src="activeProgram.image"
                    :alt="activeProgram.title || 'Próximo programa'"
                    class="block h-[220px] w-full bg-[#111] object-contain p-3 sm:h-[250px] md:h-[300px]"
                >
            </div>

            <div class="mt-4 flex items-center gap-3">
                <span class="h-px w-16 bg-lucille-accent/90"></span>
                <span class="h-px w-12 bg-lucille-accent/90"></span>
            </div>

            <div class="mt-4 max-w-[620px]">
                <span class="home-badge" x-text="activeProgram.badge || 'On deck'"></span>
                <p class="mt-3 text-[11px] uppercase tracking-[.28em] text-[#bfbfbf]" x-text="activeProgram.subtitle || ''"></p>
                <h3 class="mt-3 font-display text-[24px] uppercase leading-[.95] tracking-[.12em] md:text-[34px]" x-html="activeProgram.title_html || activeProgram.title || ''"></h3>
                <div class="mt-3 text-[12px] uppercase tracking-[.24em] text-[#dcdcdc]" x-text="activeProgram.schedule || ''"></div>
                <div class="mt-2 font-display text-[11px] uppercase tracking-[.18em] text-lucille-accent" x-text="activeProgram.host || ''"></div>
                <div class="mt-5 flex flex-wrap gap-3">
                    <a
                        class="lucille-button-solid"
                        :href="activeProgram.button?.url || '{{ route('events') }}'"
                        x-text="activeProgram.button?.label || 'Ver programación'"
                    ></a>
                    <button
                        type="button"
                        class="lucille-button-solid"
                        @click="openInfoModal()"
                    >
                        Info
                    </button>
                </div>
            </div>
        </div>
    </article>

    <aside class="home-panel p-0">
        <div class="border-b border-[#2b2b2b] px-6 py-5">
            <div class="font-display text-sm uppercase tracking-[.22em] text-[#dcdcdc]">On deck</div>
            <div class="mt-2 text-sm text-[#7b7b7b]">{{ $program['timezone'] }}</div>
        </div>

        <div class="grid gap-0 divide-y divide-[#2b2b2b]">
            @foreach ($upcomingPrograms as $slot)
                <button
                    type="button"
                    class="flex items-center gap-3 px-4 py-3 text-left transition-colors duration-300 hover:bg-[rgba(255,255,255,.03)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent/80"
                    :class="isActiveProgram(@js($slot)) ? 'bg-[rgba(195,39,32,.08)] ring-1 ring-lucille-accent/50' : ''"
                    @click="selectProgram(@js($slot))"
                >
                    <div class="h-16 w-16 shrink-0 overflow-hidden border border-[#2b2b2b] bg-[#111] md:h-18 md:w-18">
                        <img src="{{ $slot['image'] }}" alt="{{ $slot['title'] }}" class="h-full w-full object-cover transition duration-500 ease-out hover:scale-[1.02]">
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="text-[10px] uppercase tracking-[.22em] text-[#7b7b7b]">
                            {{ $slot['time'] }}
                        </div>
                        <div class="mt-1 font-display text-[14px] uppercase tracking-[.12em] text-[#dcdcdc] md:text-[15px]">
                            {!! $slot['title_html'] !!}
                        </div>
                        <div class="mt-1 text-[12px] text-[#7b7b7b] md:text-sm">
                            {{ $slot['host'] }}
                        </div>
                    </div>
                </button>
            @endforeach
        </div>
    </aside>

    <div
        x-show="infoModalOpen"
        x-cloak
        class="fixed inset-0 z-[120] flex items-center justify-center bg-black/70 px-4 py-8"
        @keydown.escape.window="closeInfoModal()"
        @click.self="closeInfoModal()"
    >
        <div class="w-full max-w-lg border border-[#2b2b2b] bg-[#111] p-5 shadow-[0_24px_80px_rgba(0,0,0,.65)]">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="home-badge" x-text="activeProgram.badge || 'On deck'"></div>
                    <h4 class="mt-3 font-display text-[22px] uppercase leading-none tracking-[.12em]" x-html="activeProgram.title_html || activeProgram.title || ''"></h4>
                    <p class="mt-2 text-xs uppercase tracking-[.24em] text-[#bfbfbf]" x-text="activeProgram.schedule || ''"></p>
                    <p class="mt-1 font-display text-[11px] uppercase tracking-[.18em] text-lucille-accent" x-text="activeProgram.host || ''"></p>
                </div>

                <button
                    type="button"
                    class="h-10 w-10 border border-[#2b2b2b] text-[#dcdcdc] transition-colors hover:bg-white/5"
                    @click="closeInfoModal()"
                    aria-label="Cerrar"
                >
                    ×
                </button>
            </div>

            <div class="mt-4 space-y-3 border-t border-[#2b2b2b] pt-4">
                <p class="text-sm leading-7 text-[#d8d8d8]" x-text="activeProgram.summary || ''"></p>
                <div class="flex flex-wrap gap-3">
                    <a
                        class="lucille-button-solid"
                        :href="activeProgram.button?.url || '{{ route('events') }}'"
                        x-text="activeProgram.button?.label || 'Ver programación'"
                    ></a>
                    <button
                        type="button"
                        class="lucille-button-solid"
                        @click="closeInfoModal()"
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
