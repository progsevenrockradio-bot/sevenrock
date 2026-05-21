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
        selectProgram(program) {
            this.activeProgram = program;
            window.clearTimeout(this.restoreTimer);
            this.restoreTimer = window.setTimeout(() => {
                this.activeProgram = this.originalProgram;
            }, 7000);
        },
        heroStyle(program) {
            return `background-image: url('${program.image}')`;
        },
    }"
>
    <article class="home-panel group overflow-hidden">
        <div class="home-program-hero" :style="heroStyle(activeProgram)">
            <div class="home-program-hero-overlay"></div>
            <div class="home-program-hero-content">
                <span class="home-badge" x-text="activeProgram.badge || 'On deck'"></span>
                <p class="mt-4 text-xs uppercase tracking-[.26em] text-[#bfbfbf]" x-text="activeProgram.subtitle || ''"></p>
                <h3 class="mt-3 font-display text-[30px] uppercase leading-none tracking-[.12em] md:text-[42px]" x-html="activeProgram.title_html || activeProgram.title || ''"></h3>
                <div class="mt-4 text-sm uppercase tracking-[.22em] text-[#dcdcdc]" x-text="activeProgram.schedule || ''"></div>
                <div class="mt-3 font-display text-sm uppercase tracking-[.16em] text-lucille-accent" x-text="activeProgram.host || ''"></div>
                <p class="mt-5 max-w-2xl text-[15px] leading-8 text-[#d8d8d8]" x-text="activeProgram.summary || ''"></p>
                <a
                    class="lucille-button-solid mt-7"
                    :href="activeProgram.button?.url || '{{ route('events') }}'"
                    x-text="activeProgram.button?.label || 'Ver programación'"
                ></a>
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
                    class="flex flex-col gap-2 px-4 py-4 text-left transition-colors duration-300 hover:bg-[rgba(255,255,255,.03)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent/80"
                    @click="selectProgram(@js($slot))"
                >
                    <div class="overflow-hidden border border-[#2b2b2b] bg-[#111]">
                        <img src="{{ $slot['image'] }}" alt="{{ $slot['title'] }}" class="h-24 w-full object-cover transition duration-500 ease-out hover:scale-[1.02]">
                    </div>

                    <div class="min-w-0">
                        <div class="text-xs uppercase tracking-[.2em] text-[#7b7b7b]">
                            {{ $slot['time'] }}
                        </div>
                        <div class="mt-1 font-display text-[18px] uppercase tracking-[.1em] text-[#dcdcdc]">
                            {!! $slot['title_html'] !!}
                        </div>
                        <div class="mt-1 text-sm text-[#7b7b7b]">
                            {{ $slot['host'] }}
                        </div>
                    </div>
                </button>
            @endforeach
        </div>
    </aside>
</div>
