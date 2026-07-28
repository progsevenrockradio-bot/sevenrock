<x-layouts.admin :title="'Parrilla de Programación - '.(optional($themeSettings)->site_name ?? config('app.name'))">
    @php $admin = $themeAppearance['admin_texts']; @endphp

    <!-- Cargamos html2canvas para la exportación a PNG -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8 no-print-section">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-[#242424] pb-6">
            <div>
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Parrilla de Programación</h1>
                <p class="mt-1 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Vista global de todos los horarios de transmisión</p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.master-programs.index') }}" class="lucille-button">Volver a programas</a>
                <button onclick="exportarAPNG()" class="lucille-button-solid bg-[#2b2b2b] hover:bg-[#3b3b3b] flex items-center gap-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    <span>Exportar a PNG</span>
                </button>
                <button onclick="window.print()" class="lucille-button-solid bg-[var(--lucille-accent)] hover:opacity-90 flex items-center gap-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    <span>Exportar a PDF / Imprimir</span>
                </button>
            </div>
        </div>

        <!-- Contenedor con scroll para pantallas pequeñas -->
        <div class="mt-8 overflow-x-auto print-overflow-visible">
            <!-- Este es el contenedor que se captura para la imagen y que se imprime -->
            <div id="parrilla-container" class="min-w-[1000px] bg-[#101012] p-6 rounded-lg border border-[#2b2b2b]">
                
                <!-- Encabezado del reporte dentro de la captura -->
                <div class="text-center mb-6 print-header">
                    <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ optional($themeSettings)->site_name ?? config('app.name') }}</h2>
                    <p class="mt-1 text-xs uppercase tracking-[.18em] text-[var(--lucille-accent)]">Parrilla Oficial de Programación</p>
                </div>

                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr>
                            <th class="border border-[#2b2b2b] bg-[#18181a] p-3 font-display text-xs uppercase tracking-[.12em] text-[#9d9d9d] w-12 text-center">BLOQUE</th>
                            <th class="border border-[#2b2b2b] bg-[#18181a] p-3 font-display text-xs uppercase tracking-[.12em] text-[#9d9d9d] w-20 text-center">HORA</th>
                            @foreach ($dayTabs as $dayKey => $dayLabel)
                                <th class="border border-[#2b2b2b] bg-[#18181a] p-3 font-display text-xs uppercase tracking-[.12em] text-[#dcdcdc] text-center w-[12%]">{{ $dayLabel }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($blocks as $blockName => $times)
                            @foreach ($times as $index => $time)
                                <tr>
                                    @if ($index === 0)
                                        <td rowspan="{{ count($times) }}" class="border border-[#2b2b2b] bg-[#141416] p-2 text-center align-middle">
                                            <!-- Texto rotado -->
                                            <div class="font-display text-xs uppercase tracking-[.2em] text-[#7b7b7b] [writing-mode:vertical-rl] mx-auto flex items-center justify-center" style="height: 100px;">
                                                {{ $blockName }}
                                            </div>
                                        </td>
                                    @endif
                                    <td class="border border-[#2b2b2b] bg-[#141416] p-3 text-center align-top">
                                        <div class="font-mono font-bold text-[#dcdcdc]">{{ Carbon\Carbon::parse($time)->format('g:i a') }}</div>
                                    </td>
                                    @foreach ($dayTabs as $dayKey => $dayLabel)
                                        <td class="border border-[#2b2b2b] p-2 align-top bg-[#101012] hover:bg-[#151515] transition-colors">
                                            @if (isset($programsGrid[$time][$dayKey]))
                                                <div class="space-y-2">
                                                    @foreach ($programsGrid[$time][$dayKey] as $program)
                                                        <div class="flex flex-col border-l-2 border-[var(--lucille-accent)] bg-[#18181a] p-2 text-xs h-full">
                                                            <strong class="font-display uppercase tracking-[.06em] text-[#dcdcdc] text-[11px] leading-tight mb-1">{{ $program->name }}</strong>
                                                            @if($program->conductor)
                                                                <span class="text-[#7b7b7b] text-[9px] uppercase leading-tight truncate" title="{{ $program->conductor }}">{{ $program->conductor }}</span>
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
                    <div class="border border-dashed border-[#2b2b2b] p-12 text-center text-[#7b7b7b] rounded-lg mt-4">
                        No hay programas de transmisión en vivo activos con hora asignada.
                    </div>
                @endif
            </div>
        </div>
    </section>

    <!-- Script para exportar a PNG usando html2canvas -->
    <script>
        function exportarAPNG() {
            const container = document.getElementById('parrilla-container');
            const originalBorder = container.style.border;
            
            html2canvas(container, {
                backgroundColor: '#101012', // Forzar el fondo oscuro
                scale: 2, // Mayor calidad
                useCORS: true // Por si hay imágenes externas
            }).then(canvas => {
                // Crear un enlace temporal para descargar la imagen
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

    <!-- Print styling overrides -->
    <style>
        @media print {
            /* Forzar a imprimir fondos */
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            @page {
                size: landscape; /* Forzar modo apaisado para la parrilla */
                margin: 10mm;
            }

            body {
                background: #101012 !important;
                color: #dcdcdc !important;
            }
            
            /* Ocultar elementos de UI que no van en el reporte */
            #admin-sidebar, header, footer, .no-print-section > div:first-child {
                display: none !important;
            }
            
            /* Reiniciar márgenes del main layout */
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

            /* Mostrar solo el contenedor de la parrilla */
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

            /* Asegurarnos de que los textos rotados funcionen en PDF */
            td[rowspan] div {
                transform: rotate(180deg);
                writing-mode: vertical-lr;
            }
        }
    </style>
</x-layouts.admin>
