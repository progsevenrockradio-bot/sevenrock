@php
    $formattedDate = $report['formatted_date'] ?? ($fecha ? \Carbon\Carbon::parse($fecha)->format('d/m/Y') : '');
    $pageTitle = $report ? 'Audiencia Diaria de Radio (' . $formattedDate . ') · Seven Rock Radio' : 'Consulta de Audiencia Diaria · Seven Rock Radio';
    $pageDesc = 'Consulta las métricas oficiales de streaming y emisión en directo de Seven Rock Radio para días completos cerrados.';
@endphp

<x-layouts.site
    :title="$pageTitle"
    :description="$pageDesc"
>
    <div class="relative min-h-screen bg-[#121215] text-[#dcdcdc] font-sans">
        {{-- Textura Lucille --}}
        <div class="fixed inset-0 z-0 bg-cover bg-center bg-no-repeat pointer-events-none opacity-20 mix-blend-luminosity" style="background-image: -webkit-image-set(url('{{ asset('assets/lucille/dark-background.webp') }}') 1x, url('{{ asset('assets/lucille/dark-background.jpg') }}') 1x); background-image: image-set(url('{{ asset('assets/lucille/dark-background.webp') }}') type('image/webp'), url('{{ asset('assets/lucille/dark-background.jpg') }}') type('image/jpeg'));"></div>
        <div class="fixed inset-0 z-0 bg-gradient-to-b from-[#121215]/80 via-[#121215]/92 to-[#121215] pointer-events-none"></div>

        {{-- Margen superior holgado para que la barra de navegación fija nunca tape el contenido --}}
        <main class="relative z-10 mx-auto max-w-[1080px] px-4 sm:px-6 pt-36 sm:pt-40 md:pt-44 pb-20 space-y-8">
            
            {{-- Migas de pan y enlaces rápidos --}}
            <nav aria-label="Ruta de navegación" class="flex flex-wrap items-center justify-between gap-3 text-xs text-[#8b8b8b] border-b border-white/10 pb-3">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('home') }}" class="hover:text-white transition-colors">Inicio</a>
                    <span>/</span>
                    <a href="{{ route('multimedia') }}#podcasts" class="hover:text-white transition-colors">Podcasts</a>
                    <span>/</span>
                    <span class="text-[#dcdcdc] font-semibold">Audiencia Diaria</span>
                </div>
                <div class="flex items-center gap-4">
                    <a href="{{ route('audience.report.latest') }}" class="inline-flex items-center gap-1.5 text-xs font-display uppercase tracking-[.12em] text-[#dcdcdc] hover:text-[var(--lucille-accent)] transition-colors">
                        <span>📊 Informe semanal consolidado</span>
                    </a>
                </div>
            </nav>

            {{-- Encabezado Principal --}}
            <header class="space-y-3 border-b border-white/10 pb-6">
                <div class="inline-flex items-center gap-2 rounded-full border border-red-500/30 bg-red-500/10 px-3 py-1 text-xs font-semibold tracking-wider text-red-400 uppercase">
                    <span>Emisión en Streaming RadioBOSS</span>
                    <span class="h-1 w-1 rounded-full bg-red-400"></span>
                    <span>Días Completos Cerrados</span>
                </div>

                <h1 class="text-2xl sm:text-3xl md:text-4xl font-display uppercase font-bold tracking-tight text-white leading-tight">
                    Consulta Diaria de Audiencia de la Radio
                </h1>
                
                <p class="text-sm text-[#a3a3a8] max-w-2xl leading-relaxed">
                    Consulta los datos de sintonización y permanencia registrados por RadioBOSS Cloud para cualquier día completo ya cerrado.
                </p>
            </header>

            {{-- AVISO: SOBRE ESTOS DATOS (Simplificado) --}}
            <div class="rounded-sm border border-amber-500/30 bg-amber-500/10 p-4 text-xs sm:text-sm text-amber-200/90 leading-relaxed shadow-sm">
                <div class="flex items-start gap-2.5">
                    <span class="text-base leading-none text-amber-400">ℹ️</span>
                    <div>
                        <strong class="font-display uppercase tracking-wider text-amber-100 block text-xs mb-1">
                            Sobre estos datos
                        </strong>
                        <p class="font-medium text-amber-100/95">
                            Audiencia global de Seven Rock Radio durante el día seleccionado. No corresponde exclusivamente a un programa.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Formulario con Selector de Fecha --}}
            <section class="rounded-sm border border-white/10 bg-[#16161a] p-5 sm:p-6 shadow-md">
                <form method="GET" action="{{ route('audience.daily') }}" class="flex flex-col sm:flex-row sm:items-end gap-4">
                    <div class="flex-1 space-y-1.5">
                        <label for="fecha" class="block text-xs font-display uppercase tracking-wider text-[#b0b0b5]">
                            Seleccionar fecha (días completos cerrados)
                        </label>
                        <input 
                            type="date" 
                            id="fecha" 
                            name="fecha" 
                            value="{{ $fecha }}" 
                            min="{{ $minDate }}" 
                            max="{{ $maxDate }}"
                            required
                            class="w-full rounded-sm border border-white/15 bg-[#101012] px-3.5 py-2.5 text-sm text-white shadow-inner focus:border-[var(--lucille-accent)] focus:outline-none focus:ring-1 focus:ring-[var(--lucille-accent)] [color-scheme:dark]"
                        >
                        <span class="block text-[11px] text-[#78787f]">
                            Disponible desde {{ \Carbon\Carbon::parse($minDate)->format('d/m/Y') }} hasta ayer ({{ \Carbon\Carbon::parse($maxDate)->format('d/m/Y') }}).
                        </span>
                    </div>

                    <div class="flex items-center gap-3">
                        <button 
                            type="submit" 
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-sm bg-[var(--lucille-accent)] px-6 py-2.5 text-xs font-display uppercase font-bold tracking-wider text-white shadow-lg shadow-red-950/40 hover:bg-red-700 transition-colors"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            <span>Consultar</span>
                        </button>

                        @if($status === 'ready' && $report)
                            <a 
                                href="{{ route('audience.daily.pdf', ['fecha' => $fecha]) }}" 
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-sm border border-white/20 bg-white/5 px-5 py-2.5 text-xs font-display uppercase font-bold tracking-wider text-white hover:bg-white/10 hover:border-white/30 transition-colors"
                                title="Descargar informe oficial en PDF para esta fecha"
                            >
                                <svg class="h-4 w-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <span>Descargar PDF</span>
                            </a>
                        @endif
                    </div>
                </form>
            </section>

            {{-- ESTADO: ERROR DE VALIDACIÓN DE FECHA --}}
            @if($status === 'error')
                <div class="rounded-sm border border-red-500/40 bg-red-950/20 p-5 text-sm text-red-200 space-y-2">
                    <div class="flex items-center gap-2 font-display uppercase font-bold tracking-wider text-red-400 text-xs">
                        <span>⚠️</span>
                        <span>No se pudo procesar la consulta</span>
                    </div>
                    <p>{{ $error }}</p>
                </div>
            @endif

            {{-- ESTADO: PREPARANDO INFORME (PENDING / PROCESSING) CON SONDEO Y LÍMITE DE ESPERA --}}
            @if(in_array($status, ['pending', 'processing'], true))
                <div 
                    x-data="{
                        currentStatus: '{{ $status }}',
                        dots: '.',
                        elapsedSec: 0,
                        errorMessage: '',
                        isTimeout: false,
                        isFailed: false,
                        init() {
                            const dotInterval = setInterval(() => {
                                this.dots = this.dots.length >= 3 ? '.' : this.dots + '.';
                                this.elapsedSec += 1;
                            }, 1000);

                            const checker = setInterval(async () => {
                                try {
                                    const res = await fetch('{{ route('audience.daily.status', ['fecha' => $fecha]) }}');
                                    const data = await res.json();
                                    
                                    if (data.status === 'ready') {
                                        clearInterval(checker);
                                        clearInterval(dotInterval);
                                        window.location.reload();
                                        return;
                                    }

                                    if (data.status === 'processing') {
                                        this.currentStatus = 'processing';
                                    }

                                    if (data.status === 'failed') {
                                        clearInterval(checker);
                                        clearInterval(dotInterval);
                                        this.isFailed = true;
                                        this.errorMessage = data.error || 'Ocurrió un fallo durante la obtención de datos.';
                                    }

                                    if (data.status === 'timeout' || this.elapsedSec > 35) {
                                        clearInterval(checker);
                                        clearInterval(dotInterval);
                                        this.isTimeout = true;
                                        this.errorMessage = data.error || 'El tiempo límite de espera ha sido superado.';
                                    }
                                } catch (e) {
                                    console.error('Error comprobando estado:', e);
                                }
                            }, 2000);
                        }
                    }"
                    class="rounded-sm border border-white/10 bg-[#16161a] p-8 text-center space-y-5"
                >
                    <template x-if="!isTimeout && !isFailed">
                        <div class="space-y-4">
                            <div class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-amber-500/10 text-amber-400 text-2xl animate-pulse">
                                ⏳
                            </div>
                            <h2 class="text-xl sm:text-2xl font-display uppercase tracking-wider text-white">
                                <span x-show="currentStatus === 'pending'">En cola de preparación</span>
                                <span x-show="currentStatus === 'processing'">Generando informe</span><span x-text="dots">...</span>
                            </h2>
                            <p class="text-sm text-[#a3a3a8] max-w-lg mx-auto leading-relaxed">
                                Estamos recopilando las métricas oficiales de RadioBOSS para el día <strong>{{ $fecha }}</strong>. 
                                Este proceso en segundo plano previene saturación y bloqueos en la API.
                            </p>
                            <div class="text-xs text-[#8e8e93] font-mono">
                                Tiempo transcurrido: <span x-text="elapsedSec"></span>s (límite: 35s)
                            </div>
                        </div>
                    </template>

                    <template x-if="isTimeout || isFailed">
                        <div class="space-y-4 text-left border border-red-500/30 bg-red-950/20 p-5 rounded-sm">
                            <div class="flex items-center gap-2 font-display uppercase font-bold tracking-wider text-red-400 text-xs">
                                <span>⚠️</span>
                                <span x-show="isTimeout">Tiempo de espera superado</span>
                                <span x-show="isFailed && !isTimeout">Fallo en la generación</span>
                            </div>
                            <p class="text-sm text-red-200" x-text="errorMessage"></p>
                            <div class="pt-2 flex flex-wrap gap-3">
                                <a 
                                    href="{{ route('audience.daily', ['fecha' => $fecha, 'reintentar' => 1]) }}"
                                    class="inline-flex items-center gap-2 rounded-sm bg-[var(--lucille-accent)] px-4 py-2 text-xs font-display uppercase font-bold tracking-wider text-white shadow-md hover:bg-red-700 transition-colors"
                                >
                                    <span>🔄 Reintentar obtención</span>
                                </a>
                                <a 
                                    href="{{ route('audience.daily') }}"
                                    class="inline-flex items-center gap-2 rounded-sm border border-white/20 bg-white/5 px-4 py-2 text-xs font-display uppercase font-bold tracking-wider text-[#dcdcdc] hover:bg-white/10 transition-colors"
                                >
                                    <span>Volver al selector</span>
                                </a>
                            </div>
                        </div>
                    </template>
                </div>
            @endif

            {{-- ESTADO: FALLIDO O TIMEOUT DIRECTO DESDE CONTROLADOR --}}
            @if(in_array($status, ['failed', 'timeout'], true))
                <div class="rounded-sm border border-red-500/30 bg-[#16161a] p-6 space-y-4">
                    <div class="flex items-center gap-2 font-display uppercase font-bold tracking-wider text-red-400 text-xs">
                        <span>⚠️</span>
                        <span>{{ $status === 'timeout' ? 'Tiempo de espera superado' : 'Fallo en la obtención de datos' }}</span>
                    </div>
                    <p class="text-sm text-red-200">
                        {{ $error ?: ($status === 'timeout' ? 'La obtención del informe tardó más del tiempo esperado.' : 'No fue posible completar la generación del informe para esta fecha.') }}
                    </p>
                    <div class="pt-2 flex flex-wrap gap-3">
                        <a 
                            href="{{ route('audience.daily', ['fecha' => $fecha, 'reintentar' => 1]) }}"
                            class="inline-flex items-center gap-2 rounded-sm bg-[var(--lucille-accent)] px-4 py-2 text-xs font-display uppercase font-bold tracking-wider text-white shadow-md hover:bg-red-700 transition-colors"
                        >
                            <span>🔄 Reintentar obtención</span>
                        </a>
                        <a 
                            href="{{ route('audience.daily') }}"
                            class="inline-flex items-center gap-2 rounded-sm border border-white/20 bg-white/5 px-4 py-2 text-xs font-display uppercase font-bold tracking-wider text-[#dcdcdc] hover:bg-white/10 transition-colors"
                        >
                            <span>Volver al selector</span>
                        </a>
                    </div>
                </div>
            @endif

            {{-- ESTADO: INFORME DISPONIBLE Y CONSOLIDADO (READY) --}}
            @if($status === 'ready' && $report)
                <div class="space-y-8 animate-fadeIn">
                    
                    {{-- Barra de información de la fecha consultada --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 pb-4">
                        <div>
                            <span class="text-xs font-display uppercase tracking-wider text-[var(--lucille-accent-readable,#ff5b52)] font-semibold">
                                Informe Oficial Cerrado
                            </span>
                            <h2 class="text-xl sm:text-2xl font-display uppercase font-bold text-white tracking-wide mt-0.5">
                                {{ $report['formatted_date'] }}
                            </h2>
                        </div>
                        <div class="text-xs text-[#8e8e93]">
                            <span>Fuente: <strong>{{ $report['radioboss']['source'] }}</strong></span>
                            <span class="mx-1.5">•</span>
                            <span>Zona horaria: <strong>{{ $report['radioboss']['timezone'] }}</strong></span>
                        </div>
                    </div>

                    {{-- Tarjetas KPI Principales de RadioBOSS --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        {{-- KPI 1: Sesiones --}}
                        <div class="rounded-sm border border-white/10 bg-[#16161a] p-5 space-y-1">
                            <span class="text-[11px] font-display uppercase tracking-wider text-[#8e8e93] block">
                                Sesiones de streaming
                            </span>
                            <div class="text-3xl sm:text-4xl font-display font-bold text-white">
                                {{ number_format($report['radioboss']['total_sessions'], 0, ',', '.') }}
                            </div>
                            <p class="text-[11px] text-[#71717a]">
                                Conexiones totales iniciadas al stream de audio
                            </p>
                        </div>

                        {{-- KPI 2: Horas de escucha (TLH) --}}
                        <div class="rounded-sm border border-white/10 bg-[#16161a] p-5 space-y-1">
                            <span class="text-[11px] font-display uppercase tracking-wider text-[#8e8e93] block">
                                Horas de escucha (TLH)
                            </span>
                            <div class="text-3xl sm:text-4xl font-display font-bold text-white">
                                {{ number_format($report['radioboss']['total_tlh'], 0, ',', '.') }} <span class="text-lg font-normal text-[#8e8e93]">h</span>
                            </div>
                            <p class="text-[11px] text-[#71717a]">
                                Total Listening Hours acumuladas durante el día
                            </p>
                        </div>

                        {{-- KPI 3: Oyentes únicos diarios --}}
                        <div class="rounded-sm border border-white/10 bg-[#16161a] p-5 space-y-1">
                            <span class="text-[11px] font-display uppercase tracking-wider text-[#8e8e93] block">
                                Oyentes únicos del día
                            </span>
                            <div class="text-3xl sm:text-4xl font-display font-bold text-white">
                                {{ number_format($report['radioboss']['total_unique'], 0, ',', '.') }}
                            </div>
                            <p class="text-[11px] text-[#71717a]">
                                Oyentes únicos consolidados según RadioBOSS Cloud
                            </p>
                        </div>
                    </div>

                    {{-- Tablas de Países --}}
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        
                        {{-- Tabla 1: Países por Permanencia (TLH) y Sesiones --}}
                        <div class="rounded-sm border border-white/10 bg-[#16161a] p-5 space-y-4">
                            <div class="flex items-center justify-between border-b border-white/10 pb-2">
                                <h3 class="text-sm font-display uppercase font-bold tracking-wider text-white">
                                    Principales países por permanencia
                                </h3>
                                <span class="text-[10px] text-[#71717a] uppercase font-mono">RadioBOSS</span>
                            </div>

                            @php
                                $countriesTlh = $report['radioboss']['countries_tlh'] ?? [];
                                $countriesSessions = collect($report['radioboss']['countries'] ?? [])->keyBy('country_iso');
                            @endphp

                            @if(empty($countriesTlh))
                                <p class="text-xs text-[#71717a] py-4 text-center">No hay desglose geográfico registrado para este día.</p>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-xs">
                                        <thead>
                                            <tr class="border-b border-white/10 text-[10px] uppercase font-display text-[#8e8e93]">
                                                <th class="py-2 font-semibold">País</th>
                                                <th class="py-2 text-right font-semibold">Horas (TLH)</th>
                                                <th class="py-2 text-right font-semibold">Sesiones</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-white/5">
                                            @foreach($countriesTlh as $c)
                                                @php
                                                    $iso = $c['country_iso'] ?? '';
                                                    $sessions = $countriesSessions->get($iso)['cnt'] ?? null;
                                                @endphp
                                                <tr class="hover:bg-white/5 transition-colors">
                                                    <td class="py-2 text-[#dcdcdc] font-medium">
                                                        {{ $c['country'] ?? 'Desconocido' }}
                                                        @if(!empty($iso))
                                                            <span class="text-[10px] text-[#71717a] uppercase">({{ $iso }})</span>
                                                        @endif
                                                    </td>
                                                    <td class="py-2 text-right font-mono text-white font-semibold">
                                                        {{ number_format($c['tlh'] ?? 0, 0, ',', '.') }} h
                                                    </td>
                                                    <td class="py-2 text-right font-mono text-[#a3a3a8]">
                                                        {{ $sessions !== null ? number_format($sessions, 0, ',', '.') : '—' }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>

                        {{-- Tabla 2: Países por Oyentes Únicos --}}
                        <div class="rounded-sm border border-white/10 bg-[#16161a] p-5 space-y-4">
                            <div class="flex items-center justify-between border-b border-white/10 pb-2">
                                <h3 class="text-sm font-display uppercase font-bold tracking-wider text-white">
                                    Distribución de oyentes únicos
                                </h3>
                                <span class="text-[10px] text-[#71717a] uppercase font-mono">RadioBOSS</span>
                            </div>

                            @php
                                $countriesUnique = $report['radioboss']['countries_unique'] ?? [];
                            @endphp

                            @if(empty($countriesUnique))
                                <p class="text-xs text-[#71717a] py-4 text-center">No hay datos de distribución disponibles.</p>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-xs">
                                        <thead>
                                            <tr class="border-b border-white/10 text-[10px] uppercase font-display text-[#8e8e93]">
                                                <th class="py-2 font-semibold">País</th>
                                                <th class="py-2 text-right font-semibold">Oyentes Únicos</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-white/5">
                                            @foreach($countriesUnique as $cu)
                                                <tr class="hover:bg-white/5 transition-colors">
                                                    <td class="py-2 text-[#dcdcdc] font-medium">
                                                        {{ $cu['country'] ?? 'Desconocido' }}
                                                        @if(!empty($cu['country_iso']))
                                                            <span class="text-[10px] text-[#71717a] uppercase">({{ $cu['country_iso'] }})</span>
                                                        @endif
                                                    </td>
                                                    <td class="py-2 text-right font-mono text-white font-semibold">
                                                        {{ number_format($cu['uniq'] ?? 0, 0, ',', '.') }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Pie con recordatorio metodológico --}}
                    <footer class="rounded-sm border border-white/10 bg-[#101012] p-4 text-xs text-[#8e8e93] space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/5 pb-2">
                            <span>Registro oficial: <code class="text-white bg-white/5 px-1 py-0.5 rounded">{{ $report['id'] }}</code></span>
                            <span>Generado: {{ \Carbon\Carbon::parse($report['generated_at'])->format('d/m/Y H:i') }} (Madrid)</span>
                        </div>
                        <p class="text-[11px] leading-relaxed">
                            {{ $report['metadata']['disclaimer'] }} Métricas agregadas de streaming de Seven Rock Radio emitidas desde RadioBOSS Cloud. Datos consolidados para consulta transparente de conductores y oyentes.
                        </p>
                    </footer>

                </div>
            @endif

        </main>
    </div>
</x-layouts.site>
