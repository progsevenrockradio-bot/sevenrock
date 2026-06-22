<x-layouts.admin :title="'Master Programs - '.(optional($themeSettings)->site_name ?? config('app.name'))">
    @php $admin = $themeAppearance['admin_texts']; @endphp

    <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Master programs</h1>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.master-programs.report') }}" class="lucille-button">Reporte de Horarios</a>
                <a href="{{ route('admin.programs.index') }}" class="lucille-button">Panel códigos</a>
                <a href="{{ route('admin.master-programs.create') }}" class="lucille-button-solid">Nuevo programa</a>
            </div>
        </div>

        <form method="GET" class="mt-6 flex flex-wrap gap-3">
            <input name="search" value="{{ $search }}" class="lucille-product-field min-w-[260px] flex-1" placeholder="Buscar por nombre, código, productor o email">
            <button type="submit" class="lucille-button-solid">Filtrar</button>
            <a href="{{ route('admin.master-programs.index') }}" class="lucille-button">Limpiar</a>
        </form>

        <div class="mt-8" x-data='{"activeDay": "{{ $activeDay }}"}'>
            <div class="flex flex-wrap gap-3 border border-[#242424] bg-[#131313] p-3">
                @foreach ($dayTabs as $dayKey => $dayLabel)
                    @php $dayPrograms = $programsByDay->get($dayKey, collect()); @endphp
                    <button
                        type="button"
                        @click="activeDay = '{{ $dayKey }}'"
                        data-day-tab="{{ $dayKey }}"
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
                <section
                    x-cloak
                    x-show="activeDay === '{{ $dayKey }}'"
                    x-transition.opacity.duration.150ms
                    data-day-panel="{{ $dayKey }}"
                    class="pt-6"
                >
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $dayLabel }}</h2>
                            <p class="mt-1 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $dayPrograms->count() }} programa{{ $dayPrograms->count() === 1 ? '' : 's' }}</p>
                        </div>
                        <div class="border border-[#2b2b2b] px-3 py-2 text-[11px] uppercase tracking-[.2em] text-[#9d9d9d]">
                            Ordenado por hora de transmisión
                        </div>
                    </div>

                    @if ($dayPrograms->isEmpty())
                        <div class="border border-[#242424] bg-[#111] px-6 py-10 text-center text-[#7b7b7b]">
                            No hay programas asignados para este día.
                        </div>
                    @else
                        <div class="overflow-x-auto border border-[#242424]">
                            <table class="min-w-full divide-y divide-[#242424] text-left text-sm">
                                <thead class="bg-[#131313] text-[#7b7b7b]">
                                    <tr>
                                        <th class="px-4 py-3 font-display uppercase tracking-[.18em]">Imagen</th>
                                        <th class="px-4 py-3 font-display uppercase tracking-[.18em]">Programa</th>
                                        <th class="px-4 py-3 font-display uppercase tracking-[.18em]">Código</th>
                                        <th class="px-4 py-3 font-display uppercase tracking-[.18em]">Horario</th>
                                        <th class="px-4 py-3 font-display uppercase tracking-[.18em]">Estado</th>
                                        <th class="px-4 py-3 font-display uppercase tracking-[.18em]">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#242424]">
                                    @foreach ($dayPrograms as $masterProgram)
                                        <tr class="align-top">
                                            <td class="px-4 py-4">
                                                @if ($masterProgram->cover_url)
                                                    <img
                                                        src="{{ $masterProgram->cover_url }}"
                                                        loading="lazy"
                                                        alt="{{ $masterProgram->name }}"
                                                        class="h-20 w-20 border border-[#2b2b2b] object-cover"
                                                    >
                                                @else
                                                    <div class="flex h-20 w-20 items-center justify-center border border-[#2b2b2b] bg-[#111] text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">
                                                        Sin imagen
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4">
                                                <div class="font-display text-base uppercase tracking-[.08em] text-[#dcdcdc]">{{ $masterProgram->name }}</div>
                                                <div class="mt-1 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $masterProgram->conductor }}</div>
                                            </td>
                                            <td class="px-4 py-4 text-[#9d9d9d]">
                                                <div class="font-mono text-sm text-[#dcdcdc]">{{ $masterProgram->program_code ?: 'Sin código' }}</div>
                                                <div class="mt-1 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $masterProgram->code_prefix ?: 'Base auto' }}</div>
                                            </td>
                                            <td class="px-4 py-4 text-[#9d9d9d]">
                                                <div>{{ $masterProgram->dia_transmision }}</div>
                                                <div>{{ $masterProgram->hora_transmision ?: 'Sin hora' }}</div>
                                                <div class="mt-1 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $masterProgram->timezone }}</div>
                                            </td>
                                            <td class="px-4 py-4">
                                                <span class="inline-flex items-center border border-[#2b2b2b] px-3 py-1 text-[11px] uppercase tracking-[.18em] {{ $masterProgram->activo ? 'text-[#dcdcdc]' : 'text-[#7b7b7b]' }}">
                                                    {{ $masterProgram->activo ? 'Activo' : 'Inactivo' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-4">
                                                <div class="flex flex-wrap gap-2">
                                                    <a
                                                        href="{{ route('admin.master-programs.edit', $masterProgram) }}"
                                                        class="inline-flex h-10 w-10 items-center justify-center border border-[#2b2b2b] text-[#dcdcdc] transition-colors hover:border-[var(--color-lucille-accent)] hover:bg-[var(--color-lucille-accent)] hover:text-white"
                                                        title="Editar"
                                                        aria-label="Editar programa"
                                                    >
                                                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                            <path d="M12 20h9" />
                                                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
                                                        </svg>
                                                    </a>
                                                    <form
                                                        action="{{ route('admin.master-programs.destroy', $masterProgram) }}"
                                                        method="POST"
                                                        data-confirm="¿Eliminar este programa maestro?"
                                                        data-confirm-title="Eliminar programa maestro"
                                                        data-confirm-action="Eliminar"
                                                        data-confirm-tone="danger"
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        <button
                                                            type="submit"
                                                            class="inline-flex h-10 w-10 items-center justify-center border border-[#2b2b2b] text-[#dcdcdc] transition-colors hover:border-red-500 hover:bg-red-500 hover:text-white"
                                                            title="Eliminar"
                                                            aria-label="Eliminar programa"
                                                        >
                                                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                <path d="M3 6h18" />
                                                                <path d="M8 6V4h8v2" />
                                                                <path d="M19 6l-1 14H6L5 6" />
                                                                <path d="M10 11v6" />
                                                                <path d="M14 11v6" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    </section>
</x-layouts.admin>
