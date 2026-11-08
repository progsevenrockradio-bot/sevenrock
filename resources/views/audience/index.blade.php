@php
    $pageTitle = $report ? 'Informe Semanal de Audiencia (' . $report['period']['label'] . ') · Seven Rock Radio' : 'Informe Semanal de Audiencia · Seven Rock Radio';
    $pageDesc = $report ? 'Consulta las métricas consolidadas de emisión en streaming (RadioBOSS) y tráfico web (Cloudflare) de Seven Rock Radio para la ' . $report['period']['label'] . '.' : 'Informes periódicos de audiencia de Seven Rock Radio elaborados con datos verificados de RadioBOSS y Cloudflare.';
@endphp

<x-layouts.site
    :title="$pageTitle"
    :description="$pageDesc"
>
    <div class="relative min-h-screen bg-[#121215] text-[#dcdcdc] font-sans">
        {{-- Textura sutil Lucille --}}
        <div class="fixed inset-0 z-0 bg-cover bg-center bg-no-repeat pointer-events-none opacity-20 mix-blend-luminosity" style="background-image: -webkit-image-set(url('{{ asset('assets/lucille/dark-background.webp') }}') 1x, url('{{ asset('assets/lucille/dark-background.jpg') }}') 1x); background-image: image-set(url('{{ asset('assets/lucille/dark-background.webp') }}') type('image/webp'), url('{{ asset('assets/lucille/dark-background.jpg') }}') type('image/jpeg'));"></div>
        <div class="fixed inset-0 z-0 bg-gradient-to-b from-[#121215]/80 via-[#121215]/92 to-[#121215] pointer-events-none"></div>

        <main class="relative z-10 mx-auto max-w-[1080px] px-4 sm:px-6 pt-28 md:pt-36 pb-20 space-y-8">
            
            {{-- Migas de pan y enlaces rápidos --}}
            <nav aria-label="Ruta de navegación" class="flex flex-wrap items-center justify-between gap-3 text-xs text-[#8b8b8b] border-b border-white/10 pb-3">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('home') }}" class="hover:text-white transition-colors">Inicio</a>
                    <span>/</span>
                    <a href="{{ route('audience.report.latest') }}" class="hover:text-white transition-colors">Audiencia</a>
                    <span>/</span>
                    <span class="text-[#dcdcdc] font-semibold">{{ $isHistorical ? $report['id'] : 'Último informe' }}</span>
                </div>
                <div class="flex items-center gap-4">
                    <a href="{{ route('audience.history') }}" class="inline-flex items-center gap-1.5 text-xs font-display uppercase tracking-[.12em] text-[#dcdcdc] hover:text-[var(--lucille-accent)] transition-colors">
                        <span>📅 Archivo histórico</span>
                    </a>
                </div>
            </nav>

            @if(!$report)
                {{-- Estado vacío si no hay informe aún --}}
                <div class="rounded-sm border border-white/10 bg-[#16161a] p-8 text-center space-y-4">
                    <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-white/5 text-2xl text-[var(--lucille-accent)]">
                        📊
                    </div>
                    <h1 class="text-xl sm:text-2xl font-display uppercase tracking-wider text-white">Informe semanal de audiencia</h1>
                    <p class="text-sm text-[#9e9ea3] max-w-lg mx-auto">
                        Actualmente no hay informes de audiencia consolidados en el sistema. Los informes se generan semanalmente tras el cierre de cada ciclo.
                    </p>
                </div>
            @else
                {{-- Encabezado Principal --}}
                <header class="space-y-4 border-b border-white/10 pb-6">
                    <div class="inline-flex items-center gap-2 rounded-full border border-red-500/30 bg-red-500/10 px-3 py-1 text-xs font-semibold tracking-wider text-red-400 uppercase">
                        <span>Datos Oficiales Consolidados</span>
                        <span class="h-1 w-1 rounded-full bg-red-400"></span>
                        <span>{{ $report['period']['label'] }}</span>
                    </div>

                    <h1 class="text-2xl sm:text-3xl md:text-4xl font-display uppercase font-bold tracking-tight text-white leading-tight">
                        {{ $report['title'] }}
                    </h1>

                    <div class="flex flex-wrap items-center justify-between gap-4 pt-1 text-xs text-[#8e8e93]">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                            <span><strong>Período:</strong> {{ $report['period']['start_date'] }} al {{ $report['period']['end_date'] }}</span>
                            <span>•</span>
                            <span><strong>Generado:</strong> {{ \Carbon\Carbon::parse($report['generated_at'])->format('d/m/Y H:i') }} (Europe/Madrid)</span>
                            <span>•</span>
                            <span><strong>Registro:</strong> <code class="text-[#dcdcdc] bg-white/5 px-1 py-0.5 rounded">{{ $report['id'] }}</code></span>
                        </div>

                        {{-- Botón de descarga PDF --}}
                        <div>
                            @php
                                $downloadRoute = $isHistorical
                                    ? route('audience.report.pdf', ['year' => $report['year'], 'week' => $report['week']])
                                    : route('audience.report.latest.pdf');
                            @endphp
                            <a href="{{ $downloadRoute }}" class="inline-flex items-center gap-2 rounded-sm bg-[var(--lucille-accent)] px-4 py-2 text-xs font-display uppercase font-bold tracking-wider text-white shadow-lg shadow-red-950/40 hover:bg-red-700 transition-colors">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <span>Descargar PDF</span>
                            </a>
                        </div>
                    </div>
                </header>

                {{-- Aviso visible de zonas horarias distintas --}}
                <div class="rounded-sm border border-amber-500/30 bg-amber-500/10 p-4 text-xs text-amber-200/90 leading-relaxed space-y-1">
                    <strong class="text-amber-100 flex items-center gap-1.5 font-display uppercase tracking-wider text-[11px]">
                        <span>ℹ️</span> Aviso metodológico sobre zonas horarias
                    </strong>
                    <p>
                        {{ $report['metadata']['timezone_note'] }}
                        Por ello, el corte diario de ambos canales no coincide exactamente en el mismo instante y presenta un desfase estacional de 1 a 2 horas.
                    </p>
                </div>

                {{-- BLOQUE 1: RADIO STREAMING (RadioBOSS) --}}
                <section class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-l-4 border-[var(--lucille-accent)] pl-3">
                        <div>
                            <h2 class="text-lg sm:text-xl font-display uppercase font-bold text-white tracking-wide">
                                1. Emisión de Radio en Streaming
                            </h2>
                            <p class="text-xs text-[#8e8e93]">
                                Fuente: {{ $report['radioboss']['source'] }} · Zona horaria: <strong>{{ $report['radioboss']['timezone'] }}</strong>
                            </p>
                        </div>
                    </div>

                    {{-- KPIs Radio --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="rounded-sm border border-white/10 bg-[#16161a] p-4">
                            <span class="text-[11px] font-display uppercase tracking-wider text-[#8e8e93]">Horas de escucha (TLH)</span>
                            <div class="text-2xl sm:text-3xl font-display font-bold text-white mt-1">
                                {{ number_format($report['radioboss']['total_tlh'], 0, ',', '.') }} <span class="text-sm font-normal text-[#8e8e93]">h</span>
                            </div>
                            <span class="text-[11px] text-[#71717a] mt-1 block">Total Listening Hours acumuladas</span>
                        </div>

                        <div class="rounded-sm border border-white/10 bg-[#16161a] p-4">
                            <span class="text-[11px] font-display uppercase tracking-wider text-[#8e8e93]">Sesiones de streaming</span>
                            <div class="text-2xl sm:text-3xl font-display font-bold text-white mt-1">
                                {{ number_format($report['radioboss']['total_sessions'], 0, ',', '.') }}
                            </div>
                            <span class="text-[11px] text-[#71717a] mt-1 block">Conexiones al stream iniciadas</span>
                        </div>

                        <div class="rounded-sm border border-white/10 bg-[#16161a] p-4">
                            <span class="text-[11px] font-display uppercase tracking-wider text-[#8e8e93]">Oyentes únicos diarios</span>
                            <div class="text-2xl sm:text-3xl font-display font-bold text-white mt-1">
                                {{ number_format($report['radioboss']['total_unique'], 0, ',', '.') }}
                            </div>
                            <span class="text-[11px] text-[#71717a] mt-1 block">Suma de oyentes únicos por día</span>
                        </div>
                    </div>

                    {{-- Tablas Radio (Desglose diario y Países) --}}
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        {{-- Desglose diario --}}
                        <div class="rounded-sm border border-white/10 bg-[#16161a] overflow-hidden">
                            <div class="px-4 py-3 bg-white/[0.02] border-b border-white/10">
                                <h3 class="text-xs font-display uppercase tracking-wider text-[#dcdcdc] font-semibold">Desglose diario de sintonización (Madrid)</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-white/5 text-[#8e8e93] text-[11px] uppercase font-display border-b border-white/10">
                                        <tr>
                                            <th class="py-2.5 px-4">Fecha</th>
                                            <th class="py-2.5 px-4 text-right">Sesiones</th>
                                            <th class="py-2.5 px-4 text-right">Oyentes únicos día</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/5">
                                        @foreach($report['radioboss']['days'] as $day)
                                            <tr class="hover:bg-white/[0.02] transition-colors">
                                                <td class="py-2.5 px-4 text-[#dcdcdc]">{{ $day['date'] }}</td>
                                                <td class="py-2.5 px-4 text-right font-mono text-white">{{ number_format($day['cnt'], 0, ',', '.') }}</td>
                                                <td class="py-2.5 px-4 text-right font-mono text-white">{{ number_format($day['cnt_unique'], 0, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Top Países Radio --}}
                        <div class="rounded-sm border border-white/10 bg-[#16161a] overflow-hidden">
                            <div class="px-4 py-3 bg-white/[0.02] border-b border-white/10">
                                <h3 class="text-xs font-display uppercase tracking-wider text-[#dcdcdc] font-semibold">Principales países por permanencia</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-white/5 text-[#8e8e93] text-[11px] uppercase font-display border-b border-white/10">
                                        <tr>
                                            <th class="py-2.5 px-4">País</th>
                                            <th class="py-2.5 px-4 text-right">Horas (TLH)</th>
                                            <th class="py-2.5 px-4 text-right">Sesiones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/5">
                                        @php
                                            $tlhMap = [];
                                            foreach($report['radioboss']['countries_tlh'] ?? [] as $ct) {
                                                $tlhMap[$ct['country']] = $ct['tlh'];
                                            }
                                        @endphp
                                        @foreach(array_slice($report['radioboss']['countries'] ?? [], 0, 7) as $country)
                                            <tr class="hover:bg-white/[0.02] transition-colors">
                                                <td class="py-2.5 px-4 text-[#dcdcdc]">
                                                    <span class="text-white font-medium">{{ $country['country'] }}</span>
                                                    <span class="text-[10px] text-[#71717a] ml-1">({{ strtoupper($country['country_iso']) }})</span>
                                                </td>
                                                <td class="py-2.5 px-4 text-right font-mono text-white">{{ number_format($tlhMap[$country['country']] ?? 0, 0, ',', '.') }} h</td>
                                                <td class="py-2.5 px-4 text-right font-mono text-[#8e8e93]">{{ number_format($country['cnt'], 0, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Nota metodológica RadioBOSS --}}
                    <div class="rounded-sm border border-white/5 bg-[#141416] p-3 text-xs text-[#8e8e93]">
                        <strong class="text-[#dcdcdc]">Nota metodológica RadioBOSS:</strong> {{ $report['radioboss']['methodology_note'] }}
                    </div>
                </section>

                {{-- BLOQUE 2: TRAFICO WEB (Cloudflare) --}}
                <section class="space-y-4 pt-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-l-4 border-amber-500 pl-3">
                        <div>
                            <h2 class="text-lg sm:text-xl font-display uppercase font-bold text-white tracking-wide">
                                2. Tráfico en el Portal Web
                            </h2>
                            <p class="text-xs text-[#8e8e93]">
                                Fuente: {{ $report['cloudflare']['source'] }} · Zona horaria: <strong>{{ $report['cloudflare']['timezone'] }}</strong>
                            </p>
                        </div>
                    </div>

                    {{-- KPIs Web --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="rounded-sm border border-white/10 bg-[#16161a] p-4">
                            <span class="text-[11px] font-display uppercase tracking-wider text-[#8e8e93]">Páginas vistas (pageViews)</span>
                            <div class="text-2xl sm:text-3xl font-display font-bold text-white mt-1">
                                {{ number_format($report['cloudflare']['page_views'], 0, ',', '.') }}
                            </div>
                            <span class="text-[11px] text-[#71717a] mt-1 block">Documentos HTML servidos con éxito (HTTP 200)</span>
                        </div>

                        <div class="rounded-sm border border-white/10 bg-[#16161a] p-4">
                            <span class="text-[11px] font-display uppercase tracking-wider text-[#8e8e93]">Visitantes únicos semanales</span>
                            <div class="text-2xl sm:text-3xl font-display font-bold text-white mt-1">
                                {{ number_format($report['cloudflare']['unique_visitors'], 0, ',', '.') }}
                            </div>
                            <span class="text-[11px] text-[#71717a] mt-1 block">Estimación agregada del período reportada por Cloudflare</span>
                        </div>

                        <div class="rounded-sm border border-white/10 bg-[#16161a] p-4">
                            <span class="text-[11px] font-display uppercase tracking-wider text-[#8e8e93]">Peticiones perimetrales (requests)</span>
                            <div class="text-2xl sm:text-3xl font-display font-bold text-white mt-1">
                                {{ number_format($report['cloudflare']['requests'], 0, ',', '.') }}
                            </div>
                            <span class="text-[11px] text-[#71717a] mt-1 block">{{ round($report['cloudflare']['bytes'] / 1073741824, 2) }} GB transferidos</span>
                        </div>
                    </div>

                    {{-- Tabla diaria Cloudflare --}}
                    <div class="rounded-sm border border-white/10 bg-[#16161a] overflow-hidden">
                        <div class="px-4 py-3 bg-white/[0.02] border-b border-white/10">
                            <h3 class="text-xs font-display uppercase tracking-wider text-[#dcdcdc] font-semibold">Desglose diario en red perimetral (Cloudflare UTC)</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-white/5 text-[#8e8e93] text-[11px] uppercase font-display border-b border-white/10">
                                    <tr>
                                        <th class="py-2.5 px-4">Fecha (UTC)</th>
                                        <th class="py-2.5 px-4 text-right">Páginas vistas</th>
                                        <th class="py-2.5 px-4 text-right">Únicos por día</th>
                                        <th class="py-2.5 px-4 text-right">Peticiones totales</th>
                                        <th class="py-2.5 px-4 text-right">Amenazas filtradas</th>
                                        <th class="py-2.5 px-4 text-right">Tráfico transferido</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($report['cloudflare']['days'] as $cfDay)
                                        <tr class="hover:bg-white/[0.02] transition-colors">
                                            <td class="py-2.5 px-4 text-[#dcdcdc]">{{ $cfDay['date'] }}</td>
                                            <td class="py-2.5 px-4 text-right font-mono text-white">{{ number_format($cfDay['page_views'], 0, ',', '.') }}</td>
                                            <td class="py-2.5 px-4 text-right font-mono text-[#8e8e93]">{{ number_format($cfDay['uniques'], 0, ',', '.') }}</td>
                                            <td class="py-2.5 px-4 text-right font-mono text-[#8e8e93]">{{ number_format($cfDay['requests'], 0, ',', '.') }}</td>
                                            <td class="py-2.5 px-4 text-right font-mono text-emerald-400">{{ number_format($cfDay['threats'], 0, ',', '.') }}</td>
                                            <td class="py-2.5 px-4 text-right font-mono text-[#8e8e93]">{{ round($cfDay['bytes'] / 1048576, 1) }} MB</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Nota metodológica Cloudflare --}}
                    <div class="rounded-sm border border-white/5 bg-[#141416] p-3 text-xs text-[#8e8e93]">
                        <strong class="text-[#dcdcdc]">Nota metodológica Cloudflare:</strong> {{ $report['cloudflare']['methodology_note'] }}
                    </div>
                </section>

                {{-- Aviso de transparencia / Disclaimer final --}}
                <footer class="rounded-sm border border-white/10 bg-[#16161a] p-4 text-center space-y-2">
                    <p class="text-xs text-[#8e8e93] leading-relaxed max-w-2xl mx-auto">
                        {{ $report['metadata']['disclaimer'] }}
                    </p>
                    <div class="text-[11px] text-[#71717a]">
                        Seven Rock Radio · Publicación informativa de transparencia · Registro de archivo: {{ $report['id'] }}
                    </div>
                </footer>
            @endif

        </main>
    </div>
</x-layouts.site>
