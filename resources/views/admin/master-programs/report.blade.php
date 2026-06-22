<x-layouts.admin :title="'Reporte de Programación - '.(optional($themeSettings)->site_name ?? config('app.name'))">
    @php $admin = $themeAppearance['admin_texts']; @endphp

    <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8 no-print-section">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-[#242424] pb-6">
            <div>
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Reporte de Programación</h1>
                <p class="mt-1 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Consulta y exportación de horarios de transmisión en vivo</p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.master-programs.index') }}" class="lucille-button">Volver a programas</a>
                <button onclick="window.print()" class="lucille-button-solid bg-[var(--lucille-accent)] hover:opacity-90 flex items-center gap-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    <span>Imprimir Reporte</span>
                </button>
            </div>
        </div>

        <div class="mt-8 space-y-12">
            @foreach ($dayTabs as $dayKey => $dayLabel)
                @php $dayPrograms = $programsByDay->get($dayKey, collect())->where('activo', true); @endphp
                
                @if ($dayPrograms->isNotEmpty())
                    <div class="space-y-4">
                        <div class="flex items-center gap-3 border-b border-[#2b2b2b] pb-2">
                            <span class="h-6 w-1 bg-[var(--lucille-accent)] rounded-full"></span>
                            <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $dayLabel }}</h2>
                            <span class="text-xs uppercase tracking-[.18em] text-[#7b7b7b] ml-auto">
                                {{ $dayPrograms->count() }} programa{{ $dayPrograms->count() === 1 ? '' : 's' }}
                            </span>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            @foreach ($dayPrograms as $program)
                                <div class="flex gap-4 border border-[#242424] bg-[#131313]/60 p-4 rounded-lg hover:border-gray-700 transition-colors">
                                    <div class="h-20 w-20 shrink-0 overflow-hidden border border-[#2b2b2b] bg-[#111] rounded">
                                        @if ($program->cover_url)
                                            <img src="{{ $program->cover_url }}" alt="{{ $program->name }}" class="h-full w-full object-cover" loading="lazy">
                                        @else
                                            <div class="flex h-full w-full items-center justify-center text-[10px] uppercase tracking-wider text-[#555] font-display text-center">
                                                Sin Foto
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0 flex flex-col justify-between">
                                        <div>
                                            <h3 class="font-display text-base uppercase tracking-[.06em] text-[#dcdcdc] truncate">{{ $program->name }}</h3>
                                            <p class="text-xs text-[#9d9d9d] mt-0.5 truncate">
                                                <span class="text-[#7b7b7b] uppercase tracking-wider text-[10px]">Conductor:</span> {{ $program->conductor }}
                                            </p>
                                        </div>
                                        <div class="mt-2 flex items-center gap-2 text-xs font-mono text-[var(--lucille-accent)]">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>
                                                {{ $program->hora_transmision ? Carbon\Carbon::parse($program->hora_transmision)->format('H:i') : 'Sin hora' }} 
                                                <span class="text-[10px] text-gray-500">({{ $program->timezone }})</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach

            @if ($masterPrograms->where('activo', true)->isEmpty())
                <div class="border border-dashed border-[#2b2b2b] p-12 text-center text-[#7b7b7b] rounded-lg">
                    No hay programas de transmisión en vivo activos registrados actualmente.
                </div>
            @endif
        </div>
    </section>

    <!-- Printable Version (Hidden on Screen, visible on print) -->
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

    <!-- Print styling overrides -->
    <style>
        @media print {
            body {
                background: white !important;
                color: black !important;
            }
            /* Hide general admin navigation and sidebar */
            #admin-sidebar, header, footer, .no-print-section {
                display: none !important;
            }
            /* Ensure the main layout margin removes the sidebar space */
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
