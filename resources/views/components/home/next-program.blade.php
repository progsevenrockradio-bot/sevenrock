@props(['program'])

@php
    use App\Models\MasterProgram;
    use App\Support\PublicMediaUrl;
    use Illuminate\Support\Facades\Schema;

    $masterDescriptionsByName = [];
    try {
        if (Schema::hasTable('master_programs')) {
            $masterDescriptionsByName = MasterProgram::query()
                ->get(['nombre', 'descripcion', 'comentario_predeterminado'])
                ->mapWithKeys(function (MasterProgram $mp): array {
                    $key = mb_strtolower(trim((string) $mp->nombre));
                    $desc = trim(implode(' ', array_filter([
                        trim((string) $mp->descripcion),
                        trim((string) $mp->comentario_predeterminado),
                    ])));
                    return $key !== '' ? [$key => $desc] : [];
                })
                ->all();
        }
    } catch (\Throwable) {
        $masterDescriptionsByName = [];
    }

    $cleanEditorialText = static function (string $text): string {
        $cleaned = trim(strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $cleaned = (string) preg_replace('/\*{1,2}([^*]+)\*{1,2}/u', '$1', $cleaned);
        $cleaned = trim((string) preg_replace('/\s+/u', ' ', $cleaned));

        // Descartar textos autogenerados de relleno para no inventar contenido
        if (
            $cleaned === '' ||
            preg_match('/^El siguiente bloque programado es\b/iu', $cleaned) ||
            preg_match('/^Programación destacada de\b/iu', $cleaned) ||
            preg_match('/^La próxima emisión en vivo es\b/iu', $cleaned)
        ) {
            return '';
        }

        return $cleaned;
    };

    $resolveProgramAsset = static function (array $item) use ($masterDescriptionsByName, $cleanEditorialText): array {
        $image = (string) data_get($item, 'image', '');
        $resolvedImage = PublicMediaUrl::normalizePublicUrl($image);

        if ($resolvedImage === '') {
            $resolvedImage = '';
        }

        $titleKey = mb_strtolower(trim((string) data_get($item, 'show', data_get($item, 'title', ''))));
        $rawSummary = $cleanEditorialText((string) data_get($item, 'summary', ''));
        $programDesc = $cleanEditorialText((string) ($masterDescriptionsByName[$titleKey] ?? ''));
        $finalSummary = $rawSummary !== '' ? $rawSummary : $programDesc;

        return array_merge($item, [
            'image' => $resolvedImage !== '' ? $resolvedImage : ($image !== '' ? asset($image) : asset('assets/lucille/pedalboard-1511069_1920.jpg')),
            'summary' => $finalSummary,
        ]);
    };

    $heroProgram = $resolveProgramAsset($program);
    $upcomingPrograms = collect(data_get($program, 'upcoming', []))
        ->map(fn (array $slot) => $resolveProgramAsset($slot))
        ->values()
        ->all();

    $programIdentity = static function (array $slot): string {
        return implode('|', [
            mb_strtolower(trim((string) ($slot['title'] ?? ''))),
            mb_strtolower(trim((string) ($slot['host'] ?? ''))),
            mb_strtolower(trim((string) ($slot['schedule'] ?? ($slot['time'] ?? '')))),
        ]);
    };

    $heroIdentity = $programIdentity($heroProgram);
    $allProgramSlots = collect([$heroProgram])
        ->concat($upcomingPrograms)
        ->unique($programIdentity)
        ->values()
        ->all();
@endphp

<div
    class="mx-auto mt-8 max-w-[1200px] px-6 md:mt-10"
    x-data="{
        activeProgram: @js($heroProgram),
        originalProgram: @js($heroProgram),
        programSlots: @js($allProgramSlots),
        descExpanded: false,
        restoreTimer: null,
        formatTimeClean(val) {
            if (!val) return '';
            return String(val).replace(/\b(\d{1,2}:\d{2}):\d{2}\b/g, '$1');
        },
        formatearTituloJS(text) {
            if (!text) return '';
            let cleanText = text.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
            if (!cleanText) return '';
            let words = cleanText.split(' ').filter(Boolean);
            let totalWords = words.length;
            if (totalWords === 1) {
                let word = words[0];
                let len = word.length;
                if (len <= 1) return `<span class='text-white'>${word}</span>`;
                let half = Math.ceil(len / 2);
                return `<span class='text-white'>${word.substring(0, half)}</span><span class='text-lucille-accent'>${word.substring(half)}</span>`;
            }
            if (totalWords === 2) {
                return `<span class='text-white'>${words[0]}</span> <span class='text-lucille-accent'>${words[1]}</span>`;
            }
            let half = Math.ceil(totalWords / 2);
            return `<span class='text-white'>${words.slice(0, half).join(' ')}</span> <span class='text-lucille-accent'>${words.slice(half).join(' ')}</span>`;
        },
        programKey(program) {
            return [program?.title || '', program?.host || '', program?.schedule || program?.time || '', program?.image || ''].join('|');
        },
        isActiveProgram(program) {
            return this.programKey(this.activeProgram) === this.programKey(program);
        },
        isOriginalProgram(program) {
            return this.programKey(this.originalProgram) === this.programKey(program);
        },
        normalizeBadgeLabel(badge) {
            const raw = String(badge || '').trim();
            if (!raw || raw.toLowerCase() === 'on deck') return 'A continuación';
            if (raw.toLowerCase() === 'on air') return 'En vivo';
            if (raw.toLowerCase() === 'up next') return 'En breve';
            return raw;
        },
        activeStatusLabel() {
            if (this.isOriginalProgram(this.activeProgram)) {
                return this.normalizeBadgeLabel(this.activeProgram?.badge);
            }
            return 'A continuación en parrilla';
        },
        hasLongSummary() {
            const text = String(this.activeProgram?.summary || '').trim();
            return text.length > 180;
        },
        visibleSummary() {
            const text = String(this.activeProgram?.summary || '').trim();
            if (!text) return '';
            if (this.descExpanded || text.length <= 180) return text;
            const sliced = text.slice(0, 180);
            const lastSpace = sliced.lastIndexOf(' ');
            return (lastSpace > 120 ? sliced.slice(0, lastSpace) : sliced) + '…';
        },
        selectProgram(program) {
            this.activeProgram = program;
            this.descExpanded = false;
            window.clearTimeout(this.restoreTimer);
            if (!this.isOriginalProgram(program)) {
                this.restoreTimer = window.setTimeout(() => {
                    this.activeProgram = this.originalProgram;
                    this.descExpanded = false;
                }, 12000);
            }
        },
        restoreOriginalProgram() {
            window.clearTimeout(this.restoreTimer);
            this.activeProgram = this.originalProgram;
            this.descExpanded = false;
        },
    }"
>
    <div class="grid items-start gap-7 lg:grid-cols-[1.2fr_0.8fr] lg:gap-10">
        {{-- 1. Columna izquierda: Área de portada con mayor presencia visual, manteniendo imagen íntegra y proporción original sin bandas artificiales --}}
        <div class="lucille-flat-surface overflow-hidden bg-[#0e0e11]">
            <img
                :src="activeProgram.image"
                x-on:error="if (!$el.dataset.fallbackApplied) { $el.dataset.fallbackApplied = '1'; $el.src = '{{ asset('assets/lucille/pedalboard-1511069_1920.jpg') }}'; }"
                :alt="activeProgram.title || 'Próximo programa'"
                class="block h-auto max-h-[420px] w-full object-contain"
                loading="lazy"
            >
        </div>

        {{-- 2. Columna derecha: Arriba lista compacta con altura equilibrada, debajo ficha y descripción del programa seleccionado --}}
        <div class="flex min-w-0 flex-col gap-3.5">
            {{-- Arriba: Lista compacta de programas (altura visible ajustada para equilibrar columnas) --}}
            <div>
                <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2 border-b border-white/10 pb-1">
                    <span class="font-display text-xs uppercase tracking-[.22em] text-[#dcdcdc]">Parrilla de programación</span>
                    <span class="text-[11px] text-[#9a9a9a]">Zona horaria: {{ $program['timezone'] ?? 'America/Caracas' }}</span>
                </div>

                <div class="custom-scrollbar max-h-[132px] divide-y divide-white/8 overflow-y-auto border-t border-b border-white/10" role="list" aria-label="Programas en parrilla">
                    @foreach ($allProgramSlots as $idx => $slot)
                        @php
                            $isPrimarySlot = $programIdentity($slot) === $heroIdentity;
                            $slotBadgeRaw = (string) ($slot['badge'] ?? ($isPrimarySlot ? ($heroProgram['badge'] ?? 'A continuación') : 'A continuación'));
                            $slotBadge = match (strtolower(trim($slotBadgeRaw))) {
                                'on air', 'en vivo' => 'En vivo',
                                'up next', 'en breve' => 'En breve',
                                default => 'A continuación',
                            };
                        @endphp
                        <button
                            type="button"
                            role="listitem"
                            class="lucille-flat-row flex w-full items-center justify-between gap-3 px-3 py-1.5 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent"
                            :class="isActiveProgram(@js($slot)) ? 'lucille-flat-row--active' : ''"
                            :aria-pressed="isActiveProgram(@js($slot)) ? 'true' : 'false'"
                            @click="selectProgram(@js($slot))"
                        >
                            <div class="flex min-w-0 flex-1 items-center gap-2.5">
                                <span
                                    class="h-2 w-2 shrink-0 rounded-full transition-colors"
                                    :class="isActiveProgram(@js($slot)) ? 'bg-lucille-accent' : 'bg-white/20'"
                                    aria-hidden="true"
                                ></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                        <span class="font-display text-[13px] uppercase tracking-[.1em] text-[#e5e5e5]">
                                            {!! $slot['title_html'] ?? e($slot['title'] ?? 'Programa') !!}
                                        </span>
                                        @if ($isPrimarySlot)
                                            <span class="inline-flex items-center border border-lucille-accent/60 bg-lucille-accent/15 px-1.5 py-0.5 font-display text-[9px] uppercase tracking-[.14em] text-[#ff7a73]">
                                                {{ $slotBadge }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap items-center gap-x-2 text-[11px] text-[#9e9e9e]">
                                        <span>{{ $slot['host'] ?? 'Seven Rock Radio' }}</span>
                                        @if (!empty($slot['schedule']) || !empty($slot['time']))
                                            <span class="text-white/25" aria-hidden="true">·</span>
                                            <span class="text-[#bdbdbd]">{{ preg_replace('/\b(\d{1,2}:\d{2}):\d{2}\b/', '$1', (string) ($slot['time'] ?? ($slot['schedule'] ?? ''))) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <span class="shrink-0 font-display text-[10px] uppercase tracking-[.16em] text-[#9a9a9a]" x-text="isActiveProgram(@js($slot)) ? 'Seleccionado' : 'Ver ficha'"></span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Debajo de la lista: Información y descripción visible del programa seleccionado --}}
            <article class="lucille-flat-surface p-3.5 md:p-4" aria-live="polite">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex min-h-[20px] items-center border px-2 font-display text-[10px] uppercase tracking-[.15em]"
                            :class="isOriginalProgram(activeProgram)
                                ? (activeStatusLabel() === 'En vivo' ? 'border-lucille-accent bg-lucille-accent text-white' : 'border-lucille-accent/60 bg-lucille-accent/10 text-white')
                                : 'border-white/20 bg-white/5 text-[#d4d4d4]'"
                            x-text="activeStatusLabel()"
                        ></span>

                        <template x-if="isOriginalProgram(activeProgram) && activeProgram.tipo_emision === 'en_vivo'">
                            <span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-[0.14em] text-[#ff5b52]">
                                <span class="relative flex h-2 w-2">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-lucille-accent opacity-75"></span>
                                    <span class="relative inline-flex h-2 w-2 rounded-full bg-lucille-accent"></span>
                                </span>
                                Directo
                            </span>
                        </template>

                        <template x-if="activeProgram.tipo_emision === 'retransmision'">
                            <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-[#b5b5b5]">
                                Retransmisión
                            </span>
                        </template>
                    </div>

                    <div class="flex items-center gap-3">
                        <template x-if="!isOriginalProgram(activeProgram)">
                            <button
                                type="button"
                                class="text-[11px] font-medium text-[#ff7a73] underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent"
                                @click="restoreOriginalProgram()"
                            >
                                Volver al horario actual
                            </button>
                        </template>

                        <a
                            class="lucille-button-solid !min-h-[30px] !px-3 !text-[10px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                            :href="activeProgram.button?.url || '{{ route('programs') }}'"
                            x-text="activeProgram.button?.label || 'Ver programación'"
                        ></a>
                    </div>
                </div>

                <div class="mt-2 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <h3
                        class="font-display text-[19px] uppercase leading-[1.05] tracking-[.1em] text-white md:text-[22px]"
                        x-html="activeProgram.title_html || formatearTituloJS(activeProgram.title || '')"
                    ></h3>

                    <div class="flex flex-wrap items-center gap-x-2 text-[12px]">
                        <span class="font-medium text-[#dcdcdc]" x-text="formatTimeClean(activeProgram.schedule || activeProgram.time || 'Programación continua')"></span>
                        <span class="text-white/25" aria-hidden="true">·</span>
                        <span class="font-display text-[12px] uppercase tracking-[.12em] text-[var(--lucille-accent-readable,#ff5b52)]" x-text="activeProgram.host || 'Seven Rock Radio'"></span>
                    </div>
                </div>

                {{-- Descripción editorial visible automáticamente (con extracto + Leer más si es larga) --}}
                <template x-if="activeProgram.summary">
                    <div class="mt-2.5 border-t border-white/10 pt-2.5">
                        <p class="text-[13px] leading-5 text-[#c4c4c4]" x-text="visibleSummary()"></p>
                        <template x-if="hasLongSummary()">
                            <button
                                type="button"
                                class="mt-1 inline-flex items-center gap-1 font-display text-[11px] uppercase tracking-[.14em] text-[var(--lucille-accent-readable,#ff5b52)] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent"
                                :aria-expanded="descExpanded ? 'true' : 'false'"
                                @click="descExpanded = !descExpanded"
                            >
                                <span x-text="descExpanded ? 'Mostrar menos' : 'Leer más'"></span>
                            </button>
                        </template>
                    </div>
                </template>

                <template x-if="isOriginalProgram(activeProgram) && activeProgram.enlace_en_vivo">
                    <div class="mt-2.5 border-t border-white/10 pt-2.5">
                        <a
                            :href="activeProgram.enlace_en_vivo"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex min-h-[30px] items-center border border-white/20 px-3 font-display text-[10px] uppercase tracking-[.12em] text-[#dcdcdc] transition-colors hover:border-lucille-accent hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent"
                        >
                            Enlace de transmisión
                        </a>
                    </div>
                </template>
            </article>
        </div>
    </div>
</div>
