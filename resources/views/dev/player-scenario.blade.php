<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seven Rock Radio - Comprobación Local del Reproductor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ $themeAppearance['google_fonts_url'] }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;700&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --lucille-accent: #c32720;
            --lucille-nav: #081a24;
            --lucille-surface: #101012;
            --lucille-body: #151515;
            --lucille-heading: #dcdcdc;
            --lucille-line: #222222;
            --lucille-body-font: 'Open Sans', ui-sans-serif, system-ui;
            --lucille-heading-font: 'Oswald', ui-sans-serif, system-ui;
        }
        body {
            background-color: #0d0d0f;
            color: #dcdcdc;
            font-family: var(--lucille-body-font);
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        .dev-header {
            background: linear-gradient(180deg, #18181c 0%, #101012 100%);
            border-bottom: 2px solid #c32720;
            padding: 16px 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
        }
        .dev-container {
            max-width: 1240px;
            margin: 0 auto;
            padding: 24px 16px 140px 16px;
        }
        .scenario-badge {
            display: inline-block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 4px 10px;
            border-radius: 9999px;
            font-weight: 700;
        }
        .scenario-card {
            background: #141418;
            border: 1px solid #24242a;
            border-radius: 12px;
            padding: 18px;
            transition: all 0.2s ease;
            cursor: pointer;
            position: relative;
        }
        .scenario-card:hover {
            border-color: #c32720;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(195, 39, 32, 0.15);
        }
        .scenario-card.is-active {
            border-color: #c32720;
            background: #1a1617;
            box-shadow: 0 0 0 2px #c32720;
        }
        .status-monitor-box {
            background: #101013;
            border: 1px solid #2b2b36;
            border-radius: 12px;
            padding: 18px;
        }
    </style>
</head>
<body x-data="playerScenarioTester()">
    <header class="dev-header">
        <div class="max-w-[1240px] mx-auto flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <span class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
                    <h1 class="text-xl font-bold tracking-wider font-display uppercase text-white">Panel de Simulación de Reproductor</h1>
                    <span class="text-xs bg-[#c32720] text-white px-2.5 py-0.5 rounded font-bold">LOCAL TEST</span>
                </div>
                <p class="text-xs text-neutral-400 mt-1">
                    Comprobación determinista de los 6 estados. Los cambios se propagan de inmediato al dock y popup.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="px-4 py-2 rounded-lg text-xs font-semibold border border-[#c32720]/60 bg-[#c32720]/20 text-white hover:bg-[#c32720]/40 transition flex items-center gap-2"
                    @click="window.open('/player/popup', 'SevenRockPlayer', 'width=460,height=740,scrollbars=no')"
                >
                    <span>⧉</span>
                    <span>Abrir Ventana Popup</span>
                </button>
            </div>
        </div>
    </header>

    <main class="dev-container">
        <!-- Nota explicativa de fuentes de audio reales -->
        <div class="mb-6 p-4 rounded-xl border border-neutral-700 bg-[#16161a] flex items-start gap-3">
            <span class="text-xl">ℹ️</span>
            <div class="text-xs text-neutral-300 leading-relaxed">
                <strong class="text-white uppercase font-display tracking-wider">Fuentes de Audio Reales y Simulación de Metadatos:</strong><br>
                Las tarjetas de escenario simulan los metadatos y estados del temporizador en tiempo real (canción, anticipo, programa, final, pérdida de datos).
                Para la reproducción real de sonido, los escenarios en directo conectan con la transmisión disponible (<code class="text-amber-400">https://radios.mipanel.stream:6796/;</code>) con respaldo local alternativo; cambiar de tarjeta no altera el contenido transmitido por la emisora.
                El podcast conecta con un episodio real y verificado de Archive.org (<code class="text-blue-400">001.- PODCAST SHOW</code>) y la muestra local (<code class="text-emerald-400">Paint It Black</code>) permite verificar controles y reproducción offline sin depender de redes externas.
            </div>
        </div>

        <!-- Panel de Diagnóstico y Telemetría del Reproductor -->
        <div class="status-monitor-box mb-8">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-neutral-800 pb-3 mb-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-neutral-400 font-display">Monitor de Estado en Tiempo Real</span>
                    <span class="text-[10px] bg-neutral-800 text-neutral-300 px-2 py-0.5 rounded font-mono">DOCK &amp; POPUP SYNC</span>
                </div>
                <div class="flex items-center gap-4 text-xs">
                    <div>
                        <span class="text-neutral-500">Escenario Seleccionado:</span>
                        <strong class="text-amber-400 uppercase font-mono ml-1" x-text="activeScenario"></strong>
                    </div>
                    <div>
                        <span class="text-neutral-500">Última Petición:</span>
                        <span class="text-neutral-300 font-mono ml-1" x-text="lastRequestTime || 'Ninguna'"></span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-center">
                <!-- Miniatura actual -->
                <div class="flex items-center gap-3 md:col-span-2">
                    <div class="w-14 h-14 rounded-lg bg-neutral-900 border border-neutral-700 overflow-hidden shrink-0 flex items-center justify-center">
                        <template x-if="appliedTrack.cover">
                            <img :src="appliedTrack.cover" alt="" class="w-full h-full object-cover">
                        </template>
                        <template x-if="!appliedTrack.cover">
                            <span class="text-xs text-neutral-600">Sin cover</span>
                        </template>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-[10px] uppercase font-bold text-neutral-400">Recepción Efectiva en Reproductor:</div>
                        <div class="text-sm font-bold text-white truncate" x-text="appliedTrack.title || 'Cargando...' "></div>
                        <div class="text-xs text-neutral-400 truncate" x-text="appliedTrack.artist || '—'"></div>
                    </div>
                </div>

                <!-- Flags de estado -->
                <div class="flex flex-col gap-1 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-neutral-500">Bloque de programa:</span>
                        <span class="font-mono font-bold" :class="appliedTrack.es_bloque_programa ? 'text-emerald-400' : 'text-neutral-400'" x-text="appliedTrack.es_bloque_programa ? 'TRUE' : 'FALSE'"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-neutral-500">Anticipo (5 min):</span>
                        <span class="font-mono font-bold" :class="appliedTrack.es_programa_anticipo ? 'text-amber-400' : 'text-neutral-400'" x-text="appliedTrack.es_programa_anticipo ? 'TRUE' : 'FALSE'"></span>
                    </div>
                </div>

                <!-- Próxima pista -->
                <div class="flex flex-col gap-1 text-xs border-l border-neutral-800 pl-4">
                    <span class="text-neutral-500">Próxima Canción (`next_track`):</span>
                    <span class="font-semibold" :class="appliedNextTrack ? 'text-blue-400' : 'text-neutral-500'" x-text="appliedNextTrack ? (appliedNextTrack.artist + ' - ' + appliedNextTrack.title) : '(Ausente)'"></span>
                    <span class="text-[10px] text-neutral-500" x-text="appliedMode ? ('Instancia reportando: ' + appliedMode) : ''"></span>
                </div>
            </div>
        </div>

        <!-- Selector de Escenarios -->
        <div class="mb-8">
            <h2 class="text-sm font-bold uppercase tracking-wider text-neutral-400 mb-4 font-display">
                Selecciona un Escenario para aplicar al Reproductor:
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- 1. Canción -->
                <div
                    class="scenario-card"
                    :class="{ 'is-active': activeScenario === 'song' }"
                    @click="setScenario('song')"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="scenario-badge bg-blue-500/20 text-blue-400 border border-blue-500/40">Estado 1</span>
                        <span class="text-xs text-blue-400 font-bold" x-show="activeScenario === 'song'">● Activo</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <img src="/assets/dev/covers/scenario_1_trooper.svg" class="w-10 h-10 rounded border border-neutral-700 object-cover" alt="">
                        <div>
                            <h3 class="text-sm font-bold text-white leading-tight">Canción Ordinaria</h3>
                            <p class="text-xs text-neutral-400">Iron Maiden - The Trooper</p>
                        </div>
                    </div>
                    <div class="text-[11px] text-neutral-400 leading-relaxed">
                        • Portada Azul específica.<br>
                        • Info de banda: biografía de Iron Maiden y letras.<br>
                        • Ventana de programa inactiva.
                    </div>
                </div>

                <!-- 2. 5 min antes -->
                <div
                    class="scenario-card"
                    :class="{ 'is-active': activeScenario === 'preview' }"
                    @click="setScenario('preview')"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="scenario-badge bg-amber-500/20 text-amber-400 border border-amber-500/40">Estado 2</span>
                        <span class="text-xs text-amber-400 font-bold" x-show="activeScenario === 'preview'">● Activo</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <img src="/assets/dev/covers/scenario_2_preview.svg" class="w-10 h-10 rounded border border-neutral-700 object-cover" alt="">
                        <div>
                            <h3 class="text-sm font-bold text-white leading-tight">Aviso 5 min Antes</h3>
                            <p class="text-xs text-neutral-400">Anticipo sin cortar canción</p>
                        </div>
                    </div>
                    <div class="text-[11px] text-neutral-400 leading-relaxed">
                        • Portada Ámbar con reloj de anticipo.<br>
                        • La canción sigue activa; el banner avisa la emisión próxima.<br>
                        • La pestaña programa muestra el show que comenzará.
                    </div>
                </div>

                <!-- 3. Programa específico -->
                <div
                    class="scenario-card"
                    :class="{ 'is-active': activeScenario === 'program_specific' }"
                    @click="setScenario('program_specific')"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="scenario-badge bg-emerald-500/20 text-emerald-400 border border-emerald-500/40">Estado 3</span>
                        <span class="text-xs text-emerald-400 font-bold" x-show="activeScenario === 'program_specific'">● Activo</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <img src="/assets/dev/covers/scenario_3_program_spec.svg" class="w-10 h-10 rounded border border-neutral-700 object-cover" alt="">
                        <div>
                            <h3 class="text-sm font-bold text-white leading-tight">Programa: Emisión Específica</h3>
                            <p class="text-xs text-neutral-400">La Hora del Metal (Episodio #12)</p>
                        </div>
                    </div>
                    <div class="text-[11px] text-neutral-400 leading-relaxed">
                        • Portada Verde con estrella y badge LIVE.<br>
                        • Título y reseña específica del episodio.<br>
                        • Conduce: Carlos Metalhead.
                    </div>
                </div>

                <!-- 4. Programa genérico -->
                <div
                    class="scenario-card"
                    :class="{ 'is-active': activeScenario === 'program_generic' }"
                    @click="setScenario('program_generic')"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="scenario-badge bg-purple-500/20 text-purple-400 border border-purple-500/40">Estado 4</span>
                        <span class="text-xs text-purple-400 font-bold" x-show="activeScenario === 'program_generic'">● Activo</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <img src="/assets/dev/covers/scenario_4_program_gen.svg" class="w-10 h-10 rounded border border-neutral-700 object-cover" alt="">
                        <div>
                            <h3 class="text-sm font-bold text-white leading-tight">Programa: Máster General</h3>
                            <p class="text-xs text-neutral-400">El Garaje del Rock (Laura Garaje)</p>
                        </div>
                    </div>
                    <div class="text-[11px] text-neutral-400 leading-relaxed">
                        • Portada Púrpura con icono de radio.<br>
                        • Programa sin episodio específico → Datos del máster.<br>
                        • Título y conductor claramente distintos al Estado 3.
                    </div>
                </div>

                <!-- 5. Fin de programa -->
                <div
                    class="scenario-card"
                    :class="{ 'is-active': activeScenario === 'program_ended' }"
                    @click="setScenario('program_ended')"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="scenario-badge bg-rose-500/20 text-rose-400 border border-rose-500/40">Estado 5</span>
                        <span class="text-xs text-rose-400 font-bold" x-show="activeScenario === 'program_ended'">● Activo</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <img src="/assets/dev/covers/scenario_5_dio.svg" class="w-10 h-10 rounded border border-neutral-700 object-cover" alt="">
                        <div>
                            <h3 class="text-sm font-bold text-white leading-tight">Final del Programa</h3>
                            <p class="text-xs text-neutral-400">Dio - Holy Diver</p>
                        </div>
                    </div>
                    <div class="text-[11px] text-neutral-400 leading-relaxed">
                        • Portada Roja de Dio.<br>
                        • Cierre automático de ventana de programa y regreso a canción.<br>
                        • Limpieza total de datos del programa finalizado.
                    </div>
                </div>

                <!-- 6. Emisión sin tiempos / Tiempos incoherentes («EN DIRECTO») -->
                <div
                    class="scenario-card"
                    :class="{ 'is-active': activeScenario === 'missing_time' }"
                    @click="setScenario('missing_time')"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="scenario-badge bg-lucille-accent/20 text-lucille-accent border border-lucille-accent/40">Sin Tiempos</span>
                        <span class="text-xs text-lucille-accent font-bold" x-show="activeScenario === 'missing_time'">● Activo</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 rounded bg-[#1c1c22] border border-neutral-700 flex items-center justify-center text-lucille-accent">
                            <span class="w-2.5 h-2.5 rounded-full bg-lucille-accent animate-ping"></span>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white leading-tight">Tiempos No Disponibles</h3>
                            <p class="text-xs text-neutral-400">Emisión en Directo</p>
                        </div>
                    </div>
                    <div class="text-[11px] text-neutral-400 leading-relaxed">
                        • Sin duración fija ni transcurrido confiable.<br>
                        • Oculta los contadores `00:00 / 00:00` y barra vacía.<br>
                        • Muestra insignia pulsante <strong>«EN DIRECTO»</strong>.
                    </div>
                </div>

                <!-- 7. Podcast bajo demanda (Archive.org) -->
                <div
                    class="scenario-card"
                    :class="{ 'is-active': activeScenario === 'podcast' }"
                    @click="setScenario('podcast')"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="scenario-badge bg-amber-500/20 text-amber-400 border border-amber-500/40">Podcast Archive</span>
                        <span class="text-xs text-amber-400 font-bold" x-show="activeScenario === 'podcast'">● Activo</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <img src="/assets/dev/covers/scenario_3_program_spec.svg" class="w-10 h-10 rounded border border-neutral-700 object-cover" alt="">
                        <div>
                            <h3 class="text-sm font-bold text-white leading-tight">Podcast Bajo Demanda</h3>
                            <p class="text-xs text-neutral-400">001.- PODCAST SHOW</p>
                        </div>
                    </div>
                    <div class="text-[11px] text-neutral-400 leading-relaxed">
                        • Archivo real verificado de Archive.org (HTTP 200 OK).<br>
                        • Barra interactiva con hover thumb para salto temporal.<br>
                        • Contador y barra sincronizados con el audio reproducido.
                    </div>
                </div>

                <!-- 8. Muestra MP3 Local Offline -->
                <div
                    class="scenario-card"
                    :class="{ 'is-active': activeScenario === 'local_sample' }"
                    @click="setScenario('local_sample')"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="scenario-badge bg-emerald-500/20 text-emerald-400 border border-emerald-500/40">Muestra Local Offline</span>
                        <span class="text-xs text-emerald-400 font-bold" x-show="activeScenario === 'local_sample'">● Activo</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <img src="/assets/dev/covers/scenario_1_trooper.svg" class="w-10 h-10 rounded border border-neutral-700 object-cover" alt="">
                        <div>
                            <h3 class="text-sm font-bold text-white leading-tight">Muestra Local MP3</h3>
                            <p class="text-xs text-neutral-400">The Rolling Stones - Paint It Black</p>
                        </div>
                    </div>
                    <div class="text-[11px] text-neutral-400 leading-relaxed">
                        • Audio local /assets/dev/audio/sample.mp3 (160 KB).<br>
                        • Comprobación de reproducción y controles sin internet.<br>
                        • Control interactivo de salto, volumen y pausa.
                    </div>
                </div>

                <!-- 8. Dos canciones de duración similar con widget retrasado -->
                <div
                    class="scenario-card"
                    :class="{ 'is-active': activeScenario === 'delayed_widget' }"
                    @click="setScenario('delayed_widget')"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="scenario-badge bg-cyan-500/20 text-cyan-400 border border-cyan-500/40">Duración Similar</span>
                        <span class="text-xs text-cyan-400 font-bold" x-show="activeScenario === 'delayed_widget'">● Activo</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <img src="/assets/dev/covers/scenario_5_dio.svg" class="w-10 h-10 rounded border border-neutral-700 object-cover" alt="">
                        <div>
                            <h3 class="text-sm font-bold text-white leading-tight">Widget Desfasado</h3>
                            <p class="text-xs text-neutral-400">Dio - Rainbow in the Dark (255s)</p>
                        </div>
                    </div>
                    <div class="text-[11px] text-neutral-400 leading-relaxed">
                        • Duración similar a The Trooper (252s vs 255s).<br>
                        • El widget aún muestra la canción anterior.<br>
                        • Rechaza la muestra del widget por discrepancia de identidad.
                    </div>
                </div>

                <!-- 9. Pérdida de datos de tiempo sin cambio de pista -->
                <div
                    class="scenario-card"
                    :class="{ 'is-active': activeScenario === 'data_loss_same_track' }"
                    @click="setScenario('data_loss_same_track')"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="scenario-badge bg-orange-500/20 text-orange-400 border border-orange-500/40">Pérdida de Datos</span>
                        <span class="text-xs text-orange-400 font-bold" x-show="activeScenario === 'data_loss_same_track'">● Activo</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <img src="/assets/dev/covers/scenario_1_trooper.svg" class="w-10 h-10 rounded border border-neutral-700 object-cover" alt="">
                        <div>
                            <h3 class="text-sm font-bold text-white leading-tight">Pérdida sin Cambio de Pista</h3>
                            <p class="text-xs text-neutral-400">The Trooper (Datos en 0)</p>
                        </div>
                    </div>
                    <div class="text-[11px] text-neutral-400 leading-relaxed">
                        • Misma pista, pero la API deja de reportar duración.<br>
                        • Al no haber muestra vigente, limpia el temporizador.<br>
                        • Muestra «EN DIRECTO» automáticamente.
                    </div>
                </div>

                <!-- 10. Cambio de Pista: Próxima entra al aire -->
                <div
                    class="scenario-card"
                    :class="{ 'is-active': activeScenario === 'next_track_started' }"
                    @click="setScenario('next_track_started')"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="scenario-badge bg-purple-500/20 text-purple-400 border border-purple-500/40">Cambio de Pista</span>
                        <span class="text-xs text-purple-400 font-bold" x-show="activeScenario === 'next_track_started'">● Activo</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <img src="/assets/dev/covers/scenario_1_trooper.svg" class="w-10 h-10 rounded border border-neutral-700 object-cover" alt="">
                        <div>
                            <h3 class="text-sm font-bold text-white leading-tight">Próxima Pasa a Sonar</h3>
                            <p class="text-xs text-neutral-400">Judas Priest - Painkiller</p>
                        </div>
                    </div>
                    <div class="text-[11px] text-neutral-400 leading-relaxed">
                        • La pista que estaba prevista ahora suena al aire.<br>
                        • Se retira de «A continuación» y entra la siguiente.<br>
                        • The Trooper se añade al inicio del historial.
                    </div>
                </div>

                <!-- 11. Historial Vacío -->
                <div
                    class="scenario-card"
                    :class="{ 'is-active': activeScenario === 'empty_history' }"
                    @click="setScenario('empty_history')"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="scenario-badge bg-neutral-600/20 text-neutral-300 border border-neutral-600/40">Historial Vacío</span>
                        <span class="text-xs text-neutral-300 font-bold" x-show="activeScenario === 'empty_history'">● Activo</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 rounded bg-[#1c1c22] border border-neutral-700 flex items-center justify-center text-neutral-500">
                            <span class="text-sm font-mono">0</span>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white leading-tight">Sin Pistas Previas</h3>
                            <p class="text-xs text-neutral-400">Estado inicial o reinicio</p>
                        </div>
                    </div>
                    <div class="text-[11px] text-neutral-400 leading-relaxed">
                        • Historial sin registros (`history: []`).<br>
                        • Muestra mensaje amigable de estado vacío.<br>
                        • No rompe la visualización de la pestaña.
                    </div>
                </div>

                <!-- 12. Caída de la Fuente (Historial Stale) -->
                <div
                    class="scenario-card"
                    :class="{ 'is-active': activeScenario === 'source_failure' }"
                    @click="setScenario('source_failure')"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="scenario-badge bg-amber-500/20 text-amber-400 border border-amber-500/40">Caída de Fuente</span>
                        <span class="text-xs text-amber-400 font-bold" x-show="activeScenario === 'source_failure'">● Activo</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 rounded bg-[#1c1c22] border border-amber-500/40 flex items-center justify-center text-amber-400">
                            <span class="text-xs">⚠️</span>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white leading-tight">Historial No Actualizado</h3>
                            <p class="text-xs text-neutral-400">Fallo temporal de API</p>
                        </div>
                    </div>
                    <div class="text-[11px] text-neutral-400 leading-relaxed">
                        • Conserva el último historial válido conocido.<br>
                        • Muestra aviso de «Último historial disponible».<br>
                        • Oculta próxima canción si la fuente no responde.
                    </div>
                </div>

                <!-- 13. Sondeo Duplicado (Deduplicación) -->
                <div
                    class="scenario-card"
                    :class="{ 'is-active': activeScenario === 'duplicate_polling' }"
                    @click="setScenario('duplicate_polling')"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="scenario-badge bg-rose-500/20 text-rose-400 border border-rose-500/40">Deduplicación</span>
                        <span class="text-xs text-rose-400 font-bold" x-show="activeScenario === 'duplicate_polling'">● Activo</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 rounded bg-[#1c1c22] border border-neutral-700 flex items-center justify-center text-rose-400">
                            <span class="text-xs font-mono">⧉</span>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white leading-tight">Sondeo Duplicado</h3>
                            <p class="text-xs text-neutral-400">Filtrado de doble inserción</p>
                        </div>
                    </div>
                    <div class="text-[11px] text-neutral-400 leading-relaxed">
                        • Registros idénticos con 10s de diferencia.<br>
                        • El visor deduplica automáticamente.<br>
                        • Conserva repeticiones reales espaciadas.
                    </div>
                </div>

                <!-- 14. Identidad Completa: Título idéntico con artista distinto -->
                <div
                    class="scenario-card"
                    :class="{ 'is-active': activeScenario === 'same_title_diff_artist' }"
                    @click="setScenario('same_title_diff_artist')"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="scenario-badge bg-cyan-500/20 text-cyan-400 border border-cyan-500/40">Regla 2: Identidad</span>
                        <span class="text-xs text-cyan-400 font-bold" x-show="activeScenario === 'same_title_diff_artist'">● Activo</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 rounded bg-[#1c1c22] border border-neutral-700 flex items-center justify-center text-cyan-400">
                            <span class="text-xs font-mono">≠</span>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white leading-tight">Mismo Título, Artista Distinto</h3>
                            <p class="text-xs text-neutral-400">Changes (Sabbath) vs Changes (Bowie)</p>
                        </div>
                    </div>
                    <div class="text-[11px] text-neutral-400 leading-relaxed">
                        • Suena: Black Sabbath - Changes.<br>
                        • Próxima: David Bowie - Changes.<br>
                        • Comprueba que NO se oculta erróneamente.
                    </div>
                </div>

                <!-- 15. Repetición Auténtica < 5 min -->
                <div
                    class="scenario-card"
                    :class="{ 'is-active': activeScenario === 'short_song_fast_repetition' }"
                    @click="setScenario('short_song_fast_repetition')"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="scenario-badge bg-emerald-500/20 text-emerald-400 border border-emerald-500/40">Regla 4: Repetición</span>
                        <span class="text-xs text-emerald-400 font-bold" x-show="activeScenario === 'short_song_fast_repetition'">● Activo</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 rounded bg-[#1c1c22] border border-neutral-700 flex items-center justify-center text-emerald-400">
                            <span class="text-xs font-mono">↻</span>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white leading-tight">Repetición Rápida &lt; 5m</h3>
                            <p class="text-xs text-neutral-400">Blitzkrieg Bop (Ramones, 72s)</p>
                        </div>
                    </div>
                    <div class="text-[11px] text-neutral-400 leading-relaxed">
                        • Canción de 72s repetida a los 85s.<br>
                        • Conserva ambas emisiones legítimas.<br>
                        • No aplica descarte ciego de 5 minutos.
                    </div>
                </div>

                <!-- 16. Filtro Editorial Estricto -->
                <div
                    class="scenario-card"
                    :class="{ 'is-active': activeScenario === 'jingle_bell_rock_legitimate' }"
                    @click="setScenario('jingle_bell_rock_legitimate')"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="scenario-badge bg-amber-500/20 text-amber-400 border border-amber-500/40">Regla 3: No Falsos Positivos</span>
                        <span class="text-xs text-amber-400 font-bold" x-show="activeScenario === 'jingle_bell_rock_legitimate'">● Activo</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 rounded bg-[#1c1c22] border border-neutral-700 flex items-center justify-center text-amber-400">
                            <span class="text-xs font-mono">★</span>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white leading-tight">Jingle Bell Rock / Cuna</h3>
                            <p class="text-xs text-neutral-400">Canciones legítimas con palabras clave</p>
                        </div>
                    </div>
                    <div class="text-[11px] text-neutral-400 leading-relaxed">
                        • Jingle Bell Rock (Bobby Helms).<br>
                        • Próxima: Cuna de Caín (Mägo de Oz).<br>
                        • Valida que no se filtran como jingles.
                    </div>
                </div>

                <!-- 14. Próxima canción toggle -->
                <div class="scenario-card">
                    <div class="flex items-center justify-between mb-2">
                        <span class="scenario-badge bg-neutral-700 text-neutral-200">Estado 6 (Auxiliar)</span>
                    </div>
                    <h3 class="text-base font-bold text-white mb-1">Próxima Canción</h3>
                    <p class="text-xs text-neutral-400 mb-3">Conmutar disponibilidad de `next_track`</p>
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="px-3 py-1.5 rounded text-xs font-semibold border transition"
                            :class="hasNextTrack ? 'bg-emerald-600 border-emerald-500 text-white' : 'bg-neutral-800 border-neutral-700 text-neutral-400'"
                            @click="toggleNextTrack(true)"
                        >
                            Disponible (Judas Priest)
                        </button>
                        <button
                            type="button"
                            class="px-3 py-1.5 rounded text-xs font-semibold border transition"
                            :class="!hasNextTrack ? 'bg-rose-600 border-rose-500 text-white' : 'bg-neutral-800 border-neutral-700 text-neutral-400'"
                            @click="toggleNextTrack(false)"
                        >
                            Ausente
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Instrucciones y Ayuda -->
        <div class="bg-[#111114] border border-neutral-800 rounded-xl p-5 mb-8">
            <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-2 font-display">Instrucciones de Verificación Visual:</h3>
            <ul class="text-xs text-neutral-400 space-y-1.5 list-disc pl-5">
                <li>Haz clic en cualquiera de las tarjetas de escenarios: la actualización se transmite <strong>instantáneamente</strong> al reproductor dock de esta página y a la ventana popup.</li>
                <li>Si tienes abierta la ventana informativa de <strong>Banda / Letra</strong> o la de <strong>Programa</strong>, se actualiza en vivo al cambiar de tarjeta sin cerrarse, o conmuta ordenadamente según el estado.</li>
                <li>Comprueba en el recuadro superior <em>"Monitor de Estado en Tiempo Real"</em> que el título, carátula y banderas reportadas por el reproductor coinciden de inmediato con tu selección.</li>
            </ul>
        </div>

        <!-- Reproductor Real Embebido (Modo Dock) -->
        <div class="mt-8">
            <h3 class="text-xs font-bold text-neutral-500 uppercase tracking-widest mb-3">Reproductor Real Activo:</h3>
        </div>
    </main>

    <!-- Reproductor Dock Oficial de la Aplicación -->
    <x-radio.player mode="dock" />

    <script>
        function playerScenarioTester() {
            return {
                activeScenario: '{{ $currentScenario }}',
                hasNextTrack: {{ $hasNextTrack ? 'true' : 'false' }},
                lastRequestTime: null,
                appliedTrack: {
                    title: '',
                    artist: '',
                    cover: '',
                    es_bloque_programa: false,
                    es_programa_anticipo: false,
                },
                appliedNextTrack: null,
                appliedMode: '',
                syncChannel: null,

                init() {
                    // Escuchar eventos de actualización efectiva del reproductor
                    window.addEventListener('sr-player-status-applied', (e) => {
                        if (e.detail) {
                            this.appliedTrack = e.detail.track || {};
                            this.appliedNextTrack = e.detail.nextTrack || null;
                            this.appliedMode = e.detail.mode || 'dock';
                        }
                    });

                    // Inicializar BroadcastChannel para sincronizar ventanas
                    if (typeof BroadcastChannel !== 'undefined') {
                        this.syncChannel = new BroadcastChannel('sevenrock_player_sync');
                    }

                    // Solicitar estado inicial
                    this.pollAppliedStatus();
                },

                async pollAppliedStatus() {
                    try {
                        const res = await fetch('/api/player/status?t=' + Date.now());
                        const payload = await res.json();
                        if (payload?.success && payload?.data?.track) {
                            this.appliedTrack = payload.data.track;
                            this.appliedNextTrack = payload.data.next_track || null;
                        }
                    } catch (e) {}
                },

                async notifyAllPlayers() {
                    // 1. Instancia local en la misma página (forzado para actualizar de inmediato)
                    if (window.MusicPlayer && typeof window.MusicPlayer.refreshStatus === 'function') {
                        try {
                            await window.MusicPlayer.refreshStatus(true, false, true);
                        } catch (e) {}
                    }
                    // 2. BroadcastChannel para Popup y otras pestañas
                    if (this.syncChannel) {
                        try {
                            this.syncChannel.postMessage('refresh');
                        } catch (e) {}
                    }
                    // 3. Fallback con localStorage para navegadores que no compartan el canal
                    try {
                        localStorage.setItem('sevenrock_player_refresh_trigger', Date.now().toString());
                    } catch (e) {}
                },

                async setScenario(scenario) {
                    this.activeScenario = scenario;
                    this.lastRequestTime = new Date().toLocaleTimeString();
                    try {
                        const res = await fetch('/dev/player-scenario/set', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                scenario: scenario,
                                has_next_track: this.hasNextTrack
                            })
                        });
                        await res.json();
                        await this.notifyAllPlayers();
                        setTimeout(() => this.pollAppliedStatus(), 150);
                    } catch (e) {
                        console.error('Error al cambiar de escenario:', e);
                    }
                },

                async toggleNextTrack(available) {
                    this.hasNextTrack = available;
                    this.lastRequestTime = new Date().toLocaleTimeString();
                    try {
                        await fetch('/dev/player-scenario/toggle-next-track', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                has_next_track: available
                            })
                        });
                        await this.notifyAllPlayers();
                        setTimeout(() => this.pollAppliedStatus(), 150);
                    } catch (e) {
                        console.error('Error al cambiar next_track:', e);
                    }
                }
            };
        }
    </script>
</body>
</html>
