<section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-[10px] uppercase tracking-[.28em] text-[#8b8b8b]">Bloque 2</p>
            <h2 class="mt-2 font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Información Editorial</h2>
            <p class="mt-3 max-w-3xl text-sm leading-7 text-[#7b7b7b]">
                Define el contexto del episodio: programa maestro, título, fecha, número, invitado y resumen editorial.
            </p>
        </div>
        <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] px-4 py-3 text-xs uppercase tracking-[.18em] text-[#b8e6c3]">
            Metadatos del episodio
        </div>
    </div>

    <div class="mt-6 space-y-6">
        <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Programa maestro</label>

            <div class="flex flex-wrap gap-2 border border-[#242424] bg-[#131313] p-3">
                @foreach ($dayTabs as $dayKey => $dayLabel)
                    @php $dayPrograms = $programsByDay->get($dayKey, collect()); @endphp
                    <button
                        type="button"
                        @click="activeDay = '{{ $dayKey }}'"
                        class="inline-flex min-w-[8rem] items-center justify-between gap-3 border px-4 py-3 text-sm uppercase tracking-[.18em] transition-colors"
                        :class="activeDay === '{{ $dayKey }}'
                            ? 'border-[var(--color-lucille-accent)] bg-[rgba(255,255,255,.04)] text-[#f2f2f2]'
                            : 'border-[#2b2b2b] text-[#7b7b7b] hover:border-[#505050] hover:text-[#dcdcdc]'"
                        aria-label="Ver programas de {{ $dayLabel }}"
                    >
                        <span>{{ $dayLabel }}</span>
                        <span class="text-[11px] tracking-[.2em] text-[#9d9d9d]">{{ $dayPrograms->count() }}</span>
                    </button>
                @endforeach
            </div>

            @foreach ($dayTabs as $dayKey => $dayLabel)
                @php $dayPrograms = $programsByDay->get($dayKey, collect()); @endphp
                <div
                    x-cloak
                    x-show="activeDay === '{{ $dayKey }}'"
                    x-transition.opacity.duration.150ms
                    data-day-panel="{{ $dayKey }}"
                    class="mt-4 space-y-2"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $dayLabel }}</div>
                        <div class="text-[11px] uppercase tracking-[.18em] text-[#9d9d9d]">{{ $dayPrograms->count() }} programa{{ $dayPrograms->count() === 1 ? '' : 's' }}</div>
                    </div>

                    <select
                        name="master_program_id"
                        class="lucille-product-field lucille-select-field w-full"
                        :disabled="activeDay !== '{{ $dayKey }}'"
                    >
                        <option value="">-- seleccionar --</option>
                        @forelse ($dayPrograms as $masterProgram)
                            <option value="{{ $masterProgram->id }}" @selected((string) old('master_program_id') === (string) $masterProgram->id)>
                                {{ $masterProgram->name }}
                            </option>
                        @empty
                            <option value="" disabled>No hay programas para este día</option>
                        @endforelse
                    </select>
                </div>
            @endforeach

            @error('master_program_id')
                <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Título del episodio</label>
                <input name="live_title" value="{{ old('live_title') }}" class="lucille-product-field w-full" placeholder="Episodio especial">
                <p class="mt-2 text-xs uppercase tracking-[.16em] text-[#7b7b7b]">
                    Obligatorio. Este título se usa en la ficha, el archivo y la notificación.
                </p>
                @error('live_title')
                    <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Capítulo / episodio</label>
                <input
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    name="numero_episodio"
                    value="{{ old('numero_episodio', $suggestedEpisodeNumber ?? '') }}"
                    class="lucille-product-field w-full"
                    placeholder="Automático"
                >
                <p class="mt-2 text-xs uppercase tracking-[.16em] text-[#7b7b7b]">
                    Déjalo vacío para usar el siguiente correlativo automático.
                </p>
                @error('numero_episodio')
                    <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Fecha de emisión</label>
                <input
                    type="date"
                    name="fecha_emision"
                    x-model="fecha_emision"
                    class="lucille-product-field w-full"
                >

                <template x-if="dateSuggestion">
                    <p
                        class="mt-2 text-xs uppercase tracking-[.12em]"
                        :class="{
                            'text-[#b8e6c3]': dateSuggestionType === 'today',
                            'text-[#f2d89b]': dateSuggestionType === 'future' || dateSuggestionType === 'next',
                            'text-[#e6c8b8]': dateSuggestionType === 'past',
                        }"
                        x-text="dateSuggestionType === 'today' ? '✅ ' + dateSuggestion : '📅 ' + dateSuggestion"
                    ></p>
                </template>
                @error('fecha_emision')
                    <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Invitado</label>
                <input name="biografia_invitado" value="{{ old('biografia_invitado') }}" class="lucille-product-field w-full" placeholder="Opcional">
            </div>
        </div>

        <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
            <div class="flex items-center justify-between gap-3">
                <label class="block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Descripción / resumen</label>
                <span class="text-[10px] uppercase tracking-[.18em] text-[#8b8b8b]">Texto amplio</span>
            </div>
            <textarea name="resena" rows="7" class="lucille-product-field mt-3 w-full">{{ old('resena') }}</textarea>
            <p class="mt-2 text-xs text-[#7b7b7b]">No hay campos de tags o categorías en este formulario.</p>
            @error('resena')
                <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>
</section>
