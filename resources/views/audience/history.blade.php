<x-layouts.site
    :title="'Archivo Histórico de Informes de Audiencia · Seven Rock Radio'"
    description="Histórico de informes semanales consolidados de audiencia de Seven Rock Radio elaborados con métricas de RadioBOSS y Cloudflare."
>
    <div class="relative min-h-screen bg-[#121215] text-[#dcdcdc] font-sans">
        {{-- Textura sutil Lucille --}}
        <div class="fixed inset-0 z-0 bg-cover bg-center bg-no-repeat pointer-events-none opacity-20 mix-blend-luminosity" style="background-image: -webkit-image-set(url('{{ asset('assets/lucille/dark-background.webp') }}') 1x, url('{{ asset('assets/lucille/dark-background.jpg') }}') 1x); background-image: image-set(url('{{ asset('assets/lucille/dark-background.webp') }}') type('image/webp'), url('{{ asset('assets/lucille/dark-background.jpg') }}') type('image/jpeg'));"></div>
        <div class="fixed inset-0 z-0 bg-gradient-to-b from-[#121215]/80 via-[#121215]/92 to-[#121215] pointer-events-none"></div>

        <main class="relative z-10 mx-auto max-w-[1080px] px-4 sm:px-6 pt-28 md:pt-36 pb-20 space-y-8">
            
            {{-- Migas de pan --}}
            <nav aria-label="Ruta de navegación" class="flex flex-wrap items-center justify-between gap-3 text-xs text-[#8b8b8b] border-b border-white/10 pb-3">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('home') }}" class="hover:text-white transition-colors">Inicio</a>
                    <span>/</span>
                    <a href="{{ route('audience.report.latest') }}" class="hover:text-white transition-colors">Audiencia</a>
                    <span>/</span>
                    <span class="text-[#dcdcdc] font-semibold">Histórico de informes</span>
                </div>
                <a href="{{ route('audience.report.latest') }}" class="inline-flex items-center gap-1.5 text-xs font-display uppercase tracking-[.12em] text-[#dcdcdc] hover:text-[var(--lucille-accent)] transition-colors">
                    <span>← Último informe semanal</span>
                </a>
            </nav>

            {{-- Encabezado --}}
            <header class="space-y-3 border-b border-white/10 pb-6">
                <h1 class="text-2xl sm:text-3xl md:text-4xl font-display uppercase font-bold tracking-tight text-white leading-tight">
                    Archivo Histórico de Audiencia
                </h1>
                <p class="text-sm text-[#8e8e93] max-w-2xl">
                    Registro cronológico de los informes semanales de audiencia auditados de Seven Rock Radio. Cada informe contiene las métricas íntegras de emisión en streaming (RadioBOSS) y tráfico web perimetral (Cloudflare).
                </p>
            </header>

            {{-- Listado de informes --}}
            @if($reports->isEmpty())
                <div class="rounded-sm border border-white/10 bg-[#16161a] p-8 text-center space-y-3">
                    <p class="text-sm text-[#8e8e93]">No hay informes archivados registrados todavía.</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($reports as $item)
                        <div class="rounded-sm border border-white/10 bg-[#16161a] p-5 hover:border-white/20 transition-colors">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-mono font-bold text-[var(--lucille-accent)] bg-red-500/10 border border-red-500/20 px-2 py-0.5 rounded">
                                            {{ $item['id'] }}
                                        </span>
                                        <span class="text-xs text-[#71717a]">
                                            Generado: {{ \Carbon\Carbon::parse($item['generated_at'])->format('d/m/Y H:i') }}
                                        </span>
                                    </div>
                                    <h2 class="text-base sm:text-lg font-display uppercase font-bold text-white tracking-wide">
                                        {{ $item['period_label'] }}
                                    </h2>
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-[#8e8e93]">
                                        <span>📻 Streaming: <strong class="text-[#dcdcdc]">{{ number_format($item['radio_sessions'], 0, ',', '.') }}</strong> sesiones ({{ number_format($item['radio_tlh'], 0, ',', '.') }} h)</span>
                                        <span>•</span>
                                        <span>🌐 Web: <strong class="text-[#dcdcdc]">{{ number_format($item['web_page_views'], 0, ',', '.') }}</strong> páginas vistas ({{ number_format($item['web_uniques'], 0, ',', '.') }} únicos)</span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 shrink-0">
                                    <a href="{{ route('audience.report.show', ['year' => $item['year'], 'week' => $item['week']]) }}" class="rounded-sm border border-white/20 px-3.5 py-1.5 text-xs font-display uppercase font-semibold text-white hover:bg-white/5 transition-colors">
                                        Ver informe
                                    </a>
                                    @if($item['has_pdf'])
                                        <a href="{{ route('audience.report.pdf', ['year' => $item['year'], 'week' => $item['week']]) }}" class="rounded-sm bg-[var(--lucille-accent)] px-3.5 py-1.5 text-xs font-display uppercase font-bold text-white hover:bg-red-700 transition-colors inline-flex items-center gap-1.5">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                            <span>PDF</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Paginador --}}
                <div class="pt-4">
                    {{ $reports->links() }}
                </div>
            @endif

        </main>
    </div>
</x-layouts.site>

