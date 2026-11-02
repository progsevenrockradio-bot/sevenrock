<x-layouts.admin :title="'Parrilla de programación - '.(optional($themeSettings)->site_name ?? config('app.name'))">
    @php $admin = $themeAppearance['admin_texts'] ?? []; @endphp

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <div class="space-y-6 no-print-section">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="font-display text-2xl font-bold uppercase tracking-wider text-white">Parrilla de programación</h1>
                <p class="mt-1 text-sm text-zinc-400">Vista global semanal de todos los horarios de transmisión activos.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.master-programs.index') }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3.5 text-xs font-medium text-zinc-300 transition hover:bg-white/10 hover:text-white">
                    ← Volver a programas
                </a>
                <button type="button" onclick="exportarAPNG()" class="inline-flex h-9 items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3.5 text-xs font-medium text-zinc-200 transition hover:bg-white/10 hover:text-white">
                    <svg class="h-4 w-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    <span>Exportar a PNG</span>
                </button>
                <button type="button" onclick="window.print()" class="inline-flex h-9 items-center gap-2 rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white shadow-sm transition hover:bg-[#d62d26]">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    <span>Exportar a PDF / Imprimir</span>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto print-overflow-visible">
            <div id="parrilla-container" class="min-w-[1000px] rounded-xl border border-white/10 bg-[#101012] p-6">
                <div class="mb-6 text-center print-header">
                    <h2 class="font-display text-2xl font-bold uppercase tracking-wider text-white">{{ optional($themeSettings)->site_name ?? config('app.name') }}</h2>
                    <p class="mt-1 text-xs font-medium uppercase tracking-widest text-[#c32720]">Parrilla oficial de programación</p>
                </div>

                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr>
                            <th class="w-12 border border-white/10 bg-[#161619] p-3 text-center text-xs font-semibold text-zinc-400">Bloque</th>
                            <th class="w-20 border border-white/10 bg-[#161619] p-3 text-center text-xs font-semibold text-zinc-400">Hora</th>
                            @foreach ($dayTabs as $dayKey => $dayLabel)
                                <th class="w-[12%] border border-white/10 bg-[#161619] p-3 text-center text-xs font-semibold text-zinc-200">{{ $dayLabel }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($blocks as $blockName => $times)
                            @foreach ($times as $index => $time)
                                <tr>
                                    @if ($index === 0)
                                        <td rowspan="{{ count($times) }}" class="border border-white/10 bg-[#131316] p-2 text-center align-middle">
                                            <div class="mx-auto flex items-center justify-center font-display text-xs font-semibold uppercase tracking-widest text-zinc-400 [writing-mode:vertical-rl]" style="height: 100px;">
                                                {{ $blockName }}
                                            </div>
                                        </td>
                                    @endif
                                    <td class="border border-white/10 bg-[#131316] p-3 text-center align-top">
                                        <div class="font-mono text-xs font-semibold text-zinc-200">{{ Carbon\Carbon::parse($time)->format('g:i a') }}</div>
                                    </td>
                                    @foreach ($dayTabs as $dayKey => $dayLabel)
                                        <td class="border border-white/10 bg-[#101012] p-2 align-top transition-colors hover:bg-white/[0.02]">
                                            @if (isset($programsGrid[$time][$dayKey]))
                                                <div class="space-y-2">
                                                    @foreach ($programsGrid[$time][$dayKey] as $program)
                                                        <div class="flex h-full flex-col rounded-r-md border-l-2 border-[#c32720] bg-[#161619] p-2 text-xs">
                                                            <strong class="mb-0.5 font-semibold leading-tight text-zinc-100">{{ $program->name }}</strong>
                                                            @if($program->conductor)
                                                                <span class="truncate text-[11px] leading-tight text-zinc-400" title="{{ $program->conductor }}">{{ $program->conductor }}</span>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>

                @if (empty($blocks))
                    <div class="mt-4 rounded-xl border border-dashed border-white/10 p-10 text-center text-sm text-zinc-400">
                        No hay programas de transmisión activos con hora asignada.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        function exportarAPNG() {
            const container = document.getElementById('parrilla-container');
            html2canvas(container, {
                backgroundColor: '#101012',
                scale: 2,
                useCORS: true
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'Parrilla_SevenRockRadio.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            }).catch(err => {
                console.error('Error al generar la imagen:', err);
                alert('Hubo un error al generar la imagen.');
            });
        }
    </script>

    <style>
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            @page {
                size: landscape;
                margin: 10mm;
            }
            body {
                background: #101012 !important;
                color: #dcdcdc !important;
            }
            #admin-sidebar, header, footer, .no-print-section > div:first-child {
                display: none !important;
            }
            .md\:pl-64 {
                padding-left: 0 !important;
            }
            main {
                padding: 0 !important;
                max-width: 100% !important;
                margin: 0 !important;
            }
            .print-overflow-visible {
                overflow: visible !important;
            }
            .no-print-section {
                display: block !important;
                border: none !important;
                padding: 0 !important;
                background: transparent !important;
            }
            #parrilla-container {
                border: none !important;
                padding: 0 !important;
                width: 100% !important;
                min-width: 0 !important;
                margin: 0 !important;
            }
            table {
                width: 100% !important;
            }
            td[rowspan] div {
                transform: rotate(180deg);
                writing-mode: vertical-lr;
            }
        }
    </style>
</x-layouts.admin>
