<div class="space-y-6">
    {{-- Encabezado de sección limpio --}}
    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between pb-3 border-b border-white/5">
        <div>
            <h2 class="font-display text-lg sm:text-xl font-bold uppercase tracking-wide text-white">
                Datos del programa y emisión
            </h2>
            <p class="text-xs text-gray-400 font-sans">
                Selecciona el programa maestro y define los metadatos esenciales de la emisión.
            </p>
        </div>
        <span class="text-[11px] font-mono text-gray-500 uppercase tracking-wider">
            Paso 1 de 3
        </span>
    </div>

    {{-- Selector de Día y Programa Maestro --}}
    <div class="space-y-3">
        <label class="block text-xs font-medium text-gray-300 font-sans">
            Programa maestro <span class="text-[#c32720]">*</span>
        </label>

        {{-- Selector de pestañas por día: navegación segmentada ligera --}}
        <div class="flex flex-wrap gap-1 rounded-xl border border-white/5 bg-black/40 p-1.5" role="tablist">
            @foreach ($dayTabs as $dayKey => $dayLabel)
                @php $dayPrograms = $programsByDay->get($dayKey, collect()); @endphp
                <button
                    type="button"
                    @click="activeDay = '{{ $dayKey }}'"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-sans transition-all"
                    :class="activeDay === '{{ $dayKey }}'
                        ? 'bg-[#c32720] text-white font-medium shadow-sm shadow-red-950/50'
                        : 'text-gray-400 hover:text-white hover:bg-white/5'"
                    role="tab"
                    :aria-selected="activeDay === '{{ $dayKey }}'"
                    aria-label="Ver programas de {{ $dayLabel }}"
                >
                    <span>{{ $dayLabel }}</span>
                    <span
                        class="text-[10px] px-1 rounded"
                        :class="activeDay === '{{ $dayKey }}' ? 'bg-black/30 text-white/90' : 'text-gray-500'"
                    >({{ $dayPrograms->count() }})</span>
                </button>
            @endforeach
        </div>

        {{-- Desplegable por día activo --}}
        @foreach ($dayTabs as $dayKey => $dayLabel)
            @php $dayPrograms = $programsByDay->get($dayKey, collect()); @endphp
            <div
                x-cloak
                x-show="activeDay === '{{ $dayKey }}'"
                x-transition.opacity.duration.150ms
                data-day-panel="{{ $dayKey }}"
                class="mt-2"
            >
                <select
                    name="master_program_id"
                    class="h-10 w-full rounded-lg border border-white/10 bg-black/50 px-3 text-xs sm:text-sm text-gray-200 focus:border-[#c32720] focus:ring-1 focus:ring-[#c32720] focus:outline-none transition-colors"
                    :disabled="activeDay !== '{{ $dayKey }}'"
                    @change="updateEpisodeNumber($event)"
                >
                    <option value="">-- Seleccionar programa maestro de {{ $dayLabel }} --</option>
                    @forelse ($dayPrograms as $masterProgram)
                        <option
                            value="{{ $masterProgram->id }}"
                            data-next-episode="{{ $masterProgram->next_episode_suggested }}"
                            @selected((string) old('master_program_id') === (string) $masterProgram->id)
                        >
                            {{ $masterProgram->name }} (Siguiente sugerido: Ep. {{ $masterProgram->next_episode_suggested }})
                        </option>
                    @empty
                        <option value="" disabled>No hay programas registrados para {{ $dayLabel }}</option>
                    @endforelse
                </select>
            </div>
        @endforeach

        @error('master_program_id')
            <p class="text-xs text-rose-400 mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- Cuadrícula de Campos: Título, Episodio, Fecha e Invitado --}}
    <div class="grid gap-5 sm:grid-cols-2 pt-2 border-t border-white/5">
        {{-- Título de la emisión --}}
        <div>
            <label class="block text-xs font-medium text-gray-300 font-sans mb-1.5">
                Título de la emisión <span class="text-[#c32720]">*</span>
            </label>
            <input
                type="text"
                name="live_title"
                value="{{ old('live_title') }}"
                class="h-10 w-full rounded-lg border border-white/10 bg-black/50 px-3.5 text-xs sm:text-sm text-white placeholder-gray-500 focus:border-[#c32720] focus:ring-1 focus:ring-[#c32720] focus:outline-none transition-colors"
                placeholder="Ej. Especial Retrospectiva Grunge"
                required
            >
            <p class="mt-1 text-[11px] text-gray-500 font-sans">
                Obligatorio. Se utiliza en la web, el archivo y las notificaciones.
            </p>
            @error('live_title')
                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Capítulo / número de episodio --}}
        <div>
            <label class="block text-xs font-medium text-gray-300 font-sans mb-1.5">
                Capítulo / número de episodio
            </label>
            <input
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                name="numero_episodio"
                value="{{ old('numero_episodio', $suggestedEpisodeNumber ?? '') }}"
                class="h-10 w-full rounded-lg border border-white/10 bg-black/50 px-3.5 text-xs sm:text-sm text-white placeholder-gray-500 focus:border-[#c32720] focus:ring-1 focus:ring-[#c32720] focus:outline-none transition-colors font-mono"
                placeholder="Sugerido automático"
            >
            <p class="mt-1 text-[11px] text-gray-500 font-sans">
                Déjalo vacío para asignar el siguiente correlativo automáticamente.
            </p>
            @error('numero_episodio')
                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Fecha de emisión --}}
        <div>
            <label class="block text-xs font-medium text-gray-300 font-sans mb-1.5">
                Fecha de emisión
            </label>
            <input
                type="date"
                name="fecha_emision"
                x-model="fecha_emision"
                class="h-10 w-full rounded-lg border border-white/10 bg-black/50 px-3.5 text-xs sm:text-sm text-gray-200 focus:border-[#c32720] focus:ring-1 focus:ring-[#c32720] focus:outline-none transition-colors"
            >
            <template x-if="dateSuggestion">
                <p
                    class="mt-1 text-[11px] font-sans"
                    :class="{
                        'text-emerald-400': dateSuggestionType === 'today',
                        'text-amber-300': dateSuggestionType === 'future' || dateSuggestionType === 'next',
                        'text-orange-300': dateSuggestionType === 'past',
                    }"
                    x-text="dateSuggestionType === 'today' ? '✅ ' + dateSuggestion : '📅 ' + dateSuggestion"
                ></p>
            </template>
            @error('fecha_emision')
                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Invitado especial --}}
        <div>
            <label class="block text-xs font-medium text-gray-300 font-sans mb-1.5">
                Invitado especial o conductor invitado
            </label>
            <input
                type="text"
                name="biografia_invitado"
                value="{{ old('biografia_invitado') }}"
                class="h-10 w-full rounded-lg border border-white/10 bg-black/50 px-3.5 text-xs sm:text-sm text-white placeholder-gray-500 focus:border-[#c32720] focus:ring-1 focus:ring-[#c32720] focus:outline-none transition-colors"
                placeholder="Opcional"
            >
            <p class="mt-1 text-[11px] text-gray-500 font-sans">
                Información sobre colaboradores o invitados del episodio.
            </p>
        </div>
    </div>

    {{-- Reseña o Descripción --}}
    <div class="pt-2 border-t border-white/5">
        <div class="flex items-center justify-between mb-1.5">
            <label class="block text-xs font-medium text-gray-300 font-sans">
                Descripción / resumen editorial
            </label>
            <span class="text-[11px] text-gray-500 font-sans">Texto descriptivo</span>
        </div>
        <textarea
            name="resena"
            rows="4"
            class="w-full rounded-lg border border-white/10 bg-black/50 p-3.5 text-xs sm:text-sm text-white placeholder-gray-500 leading-relaxed focus:border-[#c32720] focus:ring-1 focus:ring-[#c32720] focus:outline-none transition-colors"
            placeholder="Sinopsis del contenido, temas tratados o tracklist de la sesión..."
        >{{ old('resena') }}</textarea>
        @error('resena')
            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
        @enderror
    </div>
</div>
