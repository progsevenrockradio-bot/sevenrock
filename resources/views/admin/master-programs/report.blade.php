<x-layouts.admin :title="'Reporte de programación - '.(optional($themeSettings)->site_name ?? config('app.name'))">
    @php $admin = $themeAppearance['admin_texts'] ?? []; @endphp

    <div class="space-y-6 no-print-section">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="font-display text-2xl font-bold uppercase tracking-wider text-white">Reporte de horarios</h1>
                <p class="mt-1 text-sm text-zinc-400">Consulta e impresión de horarios de transmisión activos organizados por día.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.master-programs.index') }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3.5 text-xs font-medium text-zinc-300 transition hover:bg-white/10 hover:text-white">
                    ← Volver a programas
                </a>
                <button type="button" onclick="window.print()" class="inline-flex h-9 items-center gap-2 rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white shadow-sm transition hover:bg-[#d62d26]">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    <span>Imprimir reporte</span>
                </button>
            </div>
        </div>

        <div class="space-y-8">
            @foreach ($dayTabs as $dayKey => $dayLabel)
                @php $dayPrograms = $programsByDay->get($dayKey, collect())->where('activo', true); @endphp

                @if ($dayPrograms->isNotEmpty())
                    <section class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-4">
                        <div class="flex items-center justify-between border-b border-white/10 pb-3">
                            <div class="flex items-center gap-2.5">
                                <span class="h-4 w-1 rounded-full bg-[#c32720]"></span>
                                <h2 class="font-display text-lg font-bold uppercase tracking-wider text-zinc-100">{{ $dayLabel }}</h2>
                            </div>
                            <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-0.5 text-xs font-medium text-zinc-400">
                                {{ $dayPrograms->count() }} programa{{ $dayPrograms->count() === 1 ? '' : 's' }}
                            </span>
                        </div>

                        <div class="grid gap-3.5 md:grid-cols-2 lg:grid-cols-3">
                            @foreach ($dayPrograms as $program)
                                <div class="flex items-center gap-3.5 rounded-lg border border-white/10 bg-[#151519] p-3.5 transition hover:border-white/20">
                                    <div class="h-14 w-14 shrink-0 overflow-hidden rounded-lg border border-white/10 bg-zinc-900">
                                        @if ($program->cover_url)
                                            <img src="{{ $program->cover_url }}" alt="{{ $program->name }}" class="h-full w-full object-cover" loading="lazy">
                                        @else
                                            <div class="flex h-full w-full items-center justify-center text-[10px] font-medium text-zinc-500">
                                                Sin foto
                                            </div>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="truncate font-semibold text-zinc-100 text-sm">{{ $program->name }}</h3>
                                        <p class="mt-0.5 truncate text-xs text-zinc-400">{{ $program->conductor }}</p>
                                        <div class="mt-1.5 flex items-center gap-1.5 font-mono text-xs text-[#e0524b]">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>
                                                {{ $program->hora_transmision ? Carbon\Carbon::parse($program->hora_transmision)->format('H:i') : 'Sin hora' }}
                                                <span class="font-sans text-[11px] text-zinc-500">({{ $program->timezone }})</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach

            @if ($masterPrograms->where('activo', true)->isEmpty())
                <div class="rounded-xl border border-white/10 bg-[#101012] p-12 text-center text-sm text-zinc-400">
                    No hay programas activos registrados actualmente.
                </div>
            @endif
        </div>
    </div>

    {{-- Versión para impresión --}}
    <div class="print-only-container hidden print:block bg-white text-black p-6 font-sans">
        <div class="text-center border-b border-black pb-4 mb-6">
            <h1 class="text-3xl font-bold uppercase tracking-wider">Seven Rock Radio</h1>
            <p class="text-sm uppercase tracking-widest text-gray-600 mt-1">Reporte de Programación y Horarios</p>
            <p class="text-xs text-gray-500 mt-2">Generado el {{ now()->format('d/m/Y H:i') }}</p>
        </div>

        <div class="space-y-8">
            @foreach ($dayTabs as $dayKey => $dayLabel)
                @php $dayPrograms = $programsByDay->get($dayKey, collect())->where('activo', true); @endphp

                @if ($dayPrograms->isNotEmpty())
                    <div class="page-break-inside-avoid">
                        <h2 class="text-xl font-bold uppercase border-b border-gray-400 pb-1 mb-4 text-black">{{ $dayLabel }}</h2>

                        <table class="min-w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="border-b border-black">
                                    <th class="py-2 pr-4 font-bold uppercase text-xs w-24">Carátula</th>
                                    <th class="py-2 px-4 font-bold uppercase text-xs">Programa / Conductor</th>
                                    <th class="py-2 px-4 font-bold uppercase text-xs text-right">Horario</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($dayPrograms as $program)
                                    <tr class="border-b border-gray-200 align-top">
                                        <td class="py-3 pr-4">
                                            @if ($program->cover_url)
                                                <img src="{{ $program->cover_url }}" alt="{{ $program->name }}" class="h-16 w-16 object-cover border border-gray-300 rounded">
                                            @else
                                                <div class="h-16 w-16 border border-gray-300 bg-gray-100 flex items-center justify-center text-[8px] text-gray-500 uppercase text-center rounded">
                                                    Sin imagen
                                                </div>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="font-bold text-base">{{ $program->name }}</div>
                                            <div class="text-xs text-gray-600 mt-0.5">Conduce: {{ $program->conductor }}</div>
                                            @if($program->genero)
                                                <div class="text-[10px] text-gray-500 mt-1 uppercase tracking-wider">{{ $program->genero }}</div>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4 text-right font-mono text-sm font-bold">
                                            {{ $program->hora_transmision ? Carbon\Carbon::parse($program->hora_transmision)->format('H:i') : 'Sin hora' }}
                                            <div class="text-[10px] text-gray-500 font-sans font-normal">{{ $program->timezone }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endforeach
        </div>
    </div>

    <style>
        @media print {
            body {
                background: white !important;
                color: black !important;
            }
            #admin-sidebar, header, footer, .no-print-section {
                display: none !important;
            }
            .md\:pl-64 {
                padding-left: 0 !important;
            }
            main {
                padding: 0 !important;
                max-width: 100% !important;
            }
            .print-only-container {
                display: block !important;
            }
            .page-break-inside-avoid {
                page-break-inside: avoid;
            }
        }
    </style>
</x-layouts.admin>
