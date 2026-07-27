<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    {{-- SEO básico --}}
    <title>{{ $title ?? 'Seven Rock Radio' }}</title>
    <meta name="description" content="{{ $description ?? 'Tu radio de rock online. Noticias, podcasts, artistas y señal en vivo.' }}">

    {{-- PWA: tema e instalación --}}
    <meta name="theme-color" content="#DC2626">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="7RockRadio">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/assets/lucille/logo.png">

    {{-- Fuentes Oswald + Open Sans (sistema Lucille) --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=oswald:400,500,600,700|open-sans:400,500,600&display=swap" rel="stylesheet">

    {{-- Assets compilados de Vite (Tailwind + Alpine) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Estilos PWA exclusivos --}}
    <style>
        /* ── Variables de color ── */
        :root {
            --pwa-bg:      #121212;
            --pwa-surface: #1e1e1e;
            --pwa-card:    #252525;
            --pwa-border:  #2a2a2a;
            --pwa-accent:  #DC2626;
            --pwa-accent2: #ef4444;
            --pwa-text:    #e8e8e8;
            --pwa-muted:   #7b7b7b;
            --pwa-nav-h:   56px;     /* altura bottom nav */
            --pwa-player-h: 68px;    /* altura mini player */
            --safe-bottom: env(safe-area-inset-bottom, 0px);
        }

        /* ── Reseteo base ── */
        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background: var(--pwa-bg);
            color: var(--pwa-text);
            font-family: 'Open Sans', ui-sans-serif, system-ui, sans-serif;
            -webkit-tap-highlight-color: transparent;
            overscroll-behavior: none;
        }

        /* ── Tipografía display ── */
        .font-display { font-family: 'Oswald', ui-sans-serif, system-ui, sans-serif; }

        /* ── Scroll suave ── */
        .pwa-scroll {
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            scroll-behavior: smooth;
        }

        /* ── Scrollbar delgado ── */
        .pwa-scroll::-webkit-scrollbar { width: 3px; }
        .pwa-scroll::-webkit-scrollbar-track { background: transparent; }
        .pwa-scroll::-webkit-scrollbar-thumb { background: var(--pwa-border); border-radius: 3px; }

        /* ── Header ── */
        #pwa-header {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 50;
            height: 58px;
            background: linear-gradient(180deg, rgba(18,18,18,0.97) 0%, rgba(18,18,18,0.85) 100%);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--pwa-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
            padding-top: env(safe-area-inset-top, 0px);
        }

        /* ── Área de contenido principal ── */
        #pwa-main {
            position: absolute;
            top: 58px;
            left: 0; right: 0;
            bottom: calc(var(--pwa-nav-h) + var(--pwa-player-h) + var(--safe-bottom));
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
        }

        /* ── Mini Player ── */
        #pwa-mini-player {
            position: fixed;
            left: 0; right: 0;
            bottom: calc(var(--pwa-nav-h) + var(--safe-bottom));
            z-index: 45;
            height: var(--pwa-player-h);
            background: #1a1a1a;
            border-top: 1px solid var(--pwa-border);
            border-bottom: 1px solid var(--pwa-border);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        /* ── Bottom Nav ── */
        #pwa-bottom-nav {
            position: fixed;
            left: 0; right: 0;
            bottom: 0;
            z-index: 50;
            height: calc(var(--pwa-nav-h) + var(--safe-bottom));
            padding-bottom: var(--safe-bottom);
            background: #0e0e0e;
            border-top: 1px solid var(--pwa-border);
            display: flex;
            align-items: stretch;
        }

        .nav-tab {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
            cursor: pointer;
            transition: color 0.2s;
            color: var(--pwa-muted);
            font-size: 10px;
            font-family: 'Open Sans', sans-serif;
            font-weight: 500;
            text-decoration: none;
            -webkit-tap-highlight-color: transparent;
        }

        .nav-tab.active, .nav-tab:hover { color: var(--pwa-accent); }
        .nav-tab svg { width: 22px; height: 22px; }

        /* ── Carrusel scroll horizontal ── */
        .scroll-snap-x {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 6px;
            scrollbar-width: none;
        }
        .scroll-snap-x::-webkit-scrollbar { display: none; }
        .scroll-snap-x > * { scroll-snap-align: start; flex-shrink: 0; }

        /* ── Cards ── */
        .pwa-card {
            background: var(--pwa-card);
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .pwa-card:active { transform: scale(0.97); }

        /* ── Botón de acento ── */
        .btn-accent {
            background: var(--pwa-accent);
            color: #fff;
            border: none;
            border-radius: 50px;
            font-family: 'Oswald', sans-serif;
            font-weight: 600;
            letter-spacing: 0.05em;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s;
        }
        .btn-accent:hover { background: var(--pwa-accent2); }
        .btn-accent:active { transform: scale(0.96); }

        /* ── Progress bar del mini player ── */
        .player-progress {
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: var(--pwa-border);
        }
        .player-progress-fill {
            height: 100%;
            background: var(--pwa-accent);
            transition: width 1s linear;
        }

        /* ── Full Player Modal ── */
        #pwa-full-player {
            position: fixed;
            inset: 0;
            z-index: 100;
            background: linear-gradient(180deg, #1a0505 0%, #121212 45%);
            display: flex;
            flex-direction: column;
            align-items: center;
            overflow-y: auto;
            transition: transform 0.4s cubic-bezier(0.32, 0.72, 0, 1);
        }

        /* ── Animación de onda (live visualizer) ── */
        @keyframes wave-bar {
            0%, 100% { transform: scaleY(0.3); }
            50%       { transform: scaleY(1); }
        }
        .wave-bar {
            width: 3px;
            border-radius: 3px;
            background: var(--pwa-accent);
            animation: wave-bar 0.8s ease-in-out infinite;
            transform-origin: bottom;
        }

        /* ── Sección headings ── */
        .section-heading {
            font-family: 'Oswald', sans-serif;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--pwa-text);
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        /* ── Avatar circular ── */
        .avatar-circle {
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--pwa-border);
        }

        /* ── Pulse ring para "En Vivo" ── */
        @keyframes pulse-ring {
            0% { box-shadow: 0 0 0 0 rgba(220,38,38,0.5); }
            70% { box-shadow: 0 0 0 14px rgba(220,38,38,0); }
            100% { box-shadow: 0 0 0 0 rgba(220,38,38,0); }
        }
        .live-pulse { animation: pulse-ring 2s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite; }

        /* ── Skeleton loading ── */
        @keyframes shimmer {
            0% { background-position: -200px 0; }
            100% { background-position: 200px 0; }
        }
        .skeleton {
            background: linear-gradient(90deg, #1e1e1e 25%, #2a2a2a 50%, #1e1e1e 75%);
            background-size: 400px 100%;
            animation: shimmer 1.5s infinite;
            border-radius: 6px;
        }
    </style>
</head>

{{--
    x-data="pwaPlayer" expone el reproductor Alpine.js a toda la PWA.
    El audio HTML5 se mantiene en el DOM mientras el usuario navega entre
    pestañas (SPA-lite con fetch + innerHTML swap), evitando cortes de audio.
--}}
<body class="h-full overflow-hidden bg-[#121212]"
      x-data="pwaPlayer"
      x-init="init()">

    {{-- ═══════════════════════════════════════════════
         HEADER FIJO
    ═══════════════════════════════════════════════ --}}
    <header id="pwa-header">
        {{-- Logo --}}
        <a href="/app" class="flex items-center gap-2 pwa-nav-link" data-href="/app">
            <img src="{{ asset('assets/lucille/logo.png') }}" alt="Seven Rock Radio" class="h-7 w-auto">
            <span class="font-display font-bold text-base tracking-wider text-white">
                SEVEN ROCK
            </span>
        </a>

        {{-- Controles de cabecera --}}
        <div class="flex items-center gap-3">
            {{-- Indicador En Vivo --}}
            <button @click="playLive()"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-red-600/50 text-red-500 text-xs font-bold uppercase tracking-wider hover:bg-red-600/10 transition-colors live-indicator"
                    title="Escuchar en vivo">
                <span class="w-2 h-2 bg-red-500 rounded-full" x-bind:class="isPlaying && isLive ? 'live-pulse' : 'opacity-60'"></span>
                <span>En Vivo</span>
            </button>

            {{-- Avatar / Perfil --}}
            <button class="w-8 h-8 rounded-full bg-[#2a2a2a] border border-[#3a3a3a] flex items-center justify-center overflow-hidden">
                <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>
    </header>

    {{-- ═══════════════════════════════════════════════
         ÁREA DE CONTENIDO PRINCIPAL (SPA-lite)
    ═══════════════════════════════════════════════ --}}
    <main id="pwa-main" class="pwa-scroll">
        {{-- El contenido de cada vista se inyecta aquí --}}
        <div id="pwa-content">
            {{ $slot }}
        </div>
        {{-- Padding inferior para que el último elemento no quede tapado --}}
        <div class="h-6"></div>
    </main>

    {{-- ═══════════════════════════════════════════════
         MINI PLAYER (persistente, justo sobre el Bottom Nav)
    ═══════════════════════════════════════════════ --}}
    <div id="pwa-mini-player"
         x-show="currentTrack.src"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-y-full opacity-0"
         x-transition:enter-end="translate-y-0 opacity-100"
         @click="expand()"
         class="cursor-pointer select-none">

        {{-- Barra de progreso (viva para radio) --}}
        <div class="player-progress">
            <div class="player-progress-fill"
                 :style="isLive ? 'width: 100%' : `width: ${progressPercent}%`"></div>
        </div>

        <div class="flex items-center h-full px-4 gap-3">
            {{-- Carátula --}}
            <div class="relative shrink-0">
                <img :src="currentTrack.cover || '{{ asset('assets/lucille/album3.jpg') }}'"
                     class="w-12 h-12 rounded-lg object-cover border border-[#2a2a2a]"
                     alt="Carátula"
                     @error="$el.src='{{ asset('assets/lucille/album3.jpg') }}'">
                {{-- Indicador "Live" --}}
                <span x-show="isLive && isPlaying"
                      class="absolute -top-1 -right-1 bg-red-600 text-white text-[8px] font-bold px-1 py-0.5 rounded uppercase tracking-wider leading-none">
                    Live
                </span>
            </div>

            {{-- Info del track --}}
            <div class="flex-1 min-w-0">
                <p class="font-display text-sm font-semibold text-white truncate leading-tight"
                   x-text="currentTrack.title || 'Seven Rock Radio'"></p>
                <p class="text-xs text-gray-400 truncate leading-tight mt-0.5"
                   x-text="currentTrack.artist || 'En Vivo'"></p>
            </div>

            {{-- Visualizador de ondas (solo en live) / barra de tiempo (podcast) --}}
            <div class="flex items-center gap-2" @click.stop>
                {{-- Olas animadas (radio en vivo) --}}
                <div x-show="isLive && isPlaying" class="flex items-end gap-0.5 h-6">
                    <div class="wave-bar h-3" style="animation-delay: 0s;"></div>
                    <div class="wave-bar h-5" style="animation-delay: 0.15s;"></div>
                    <div class="wave-bar h-2" style="animation-delay: 0.3s;"></div>
                    <div class="wave-bar h-4" style="animation-delay: 0.45s;"></div>
                    <div class="wave-bar h-6" style="animation-delay: 0.1s;"></div>
                </div>

                {{-- Botón Play / Pause --}}
                <button @click.stop="togglePlay()"
                        class="w-10 h-10 rounded-full bg-red-600 flex items-center justify-center shrink-0 hover:bg-red-500 active:scale-95 transition-all">
                    {{-- Pause --}}
                    <svg x-show="isPlaying" class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                    </svg>
                    {{-- Play --}}
                    <svg x-show="!isPlaying" class="w-5 h-5 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                </button>

                {{-- Chevron expand --}}
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
                </svg>
            </div>
        </div>

        {{-- Elemento <audio> oculto — persiste en el DOM durante toda la sesión --}}
        <audio id="pwa-audio"
               x-ref="audio"
               preload="none"
               @timeupdate="onTimeUpdate()"
               @ended="onEnded()"
               style="display:none;"></audio>
    </div>

    {{-- ═══════════════════════════════════════════════
         FULL PLAYER MODAL (expandido)
    ═══════════════════════════════════════════════ --}}
    <div id="pwa-full-player"
         x-show="isExpanded"
         x-transition:enter="transition ease-out duration-400"
         x-transition:enter-start="transform translate-y-full"
         x-transition:enter-end="transform translate-y-0"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="transform translate-y-0"
         x-transition:leave-end="transform translate-y-full"
         style="display:none;">

        {{-- Drag handle --}}
        <div class="w-full flex justify-center pt-3 pb-1">
            <div class="w-10 h-1 rounded-full bg-gray-600 cursor-pointer" @click="collapse()"></div>
        </div>

        {{-- Botón cerrar --}}
        <div class="w-full flex justify-between items-center px-5 pt-2 pb-4">
            <button @click="collapse()" class="text-gray-400 hover:text-white transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <span class="font-display text-xs tracking-widest text-gray-400 uppercase"
                  x-text="isLive ? '— En Vivo —' : '— Reproduciendo —'"></span>
            <div class="w-6"></div>{{-- spacer --}}
        </div>

        {{-- Carátula grande --}}
        <div class="px-8 w-full flex justify-center">
            <div class="relative w-full max-w-xs aspect-square">
                <img :src="currentTrack.cover || '{{ asset('assets/lucille/album3.jpg') }}'"
                     class="w-full h-full rounded-2xl object-cover shadow-[0_20px_60px_rgba(0,0,0,.7)] border border-[#2a2a2a]"
                     alt="Carátula"
                     @error="$el.src='{{ asset('assets/lucille/album3.jpg') }}'">
                {{-- Overlay Live badge --}}
                <div x-show="isLive" class="absolute bottom-3 left-3 bg-red-600 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">
                    🔴 En Vivo
                </div>
            </div>
        </div>

        {{-- Título y Artista --}}
        <div class="w-full px-8 mt-6 text-center">
            <h2 class="font-display text-2xl font-bold text-white leading-tight"
                x-text="currentTrack.title || 'Seven Rock Radio'"></h2>
            <p class="text-base text-gray-400 mt-1"
               x-text="currentTrack.artist || 'En Vivo'"></p>
        </div>

        {{-- Barra de progreso (podcast/canción) --}}
        <div class="w-full px-8 mt-6" x-show="!isLive">
            <input type="range"
                   class="lucille-range-slider w-full"
                   :value="progressPercent"
                   min="0" max="100" step="0.1"
                   @input="seekTo($event.target.value)"
                   @click.stop>
            <div class="flex justify-between text-xs text-gray-500 mt-1">
                <span x-text="formatTime(currentTime)">0:00</span>
                <span x-text="formatTime(duration)">0:00</span>
            </div>
        </div>

        {{-- Indicador de stream vivo --}}
        <div x-show="isLive" class="w-full px-8 mt-6 flex justify-center">
            <div class="flex items-end gap-1 h-10">
                <div class="wave-bar h-4" style="animation-delay: 0s;"></div>
                <div class="wave-bar h-8" style="animation-delay: 0.12s;"></div>
                <div class="wave-bar h-5" style="animation-delay: 0.24s;"></div>
                <div class="wave-bar h-10" style="animation-delay: 0.06s;"></div>
                <div class="wave-bar h-3" style="animation-delay: 0.36s;"></div>
                <div class="wave-bar h-7" style="animation-delay: 0.18s;"></div>
                <div class="wave-bar h-9" style="animation-delay: 0.30s;"></div>
            </div>
        </div>

        {{-- Controles de reproducción --}}
        <div class="w-full px-8 mt-6 flex items-center justify-between" x-show="!isLive">
            {{-- Anterior --}}
            <button class="w-11 h-11 flex items-center justify-center text-gray-400 hover:text-white transition-colors" disabled>
                <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/>
                </svg>
            </button>
            {{-- Play / Pause grande --}}
            <button @click="togglePlay()"
                    class="w-16 h-16 rounded-full bg-red-600 flex items-center justify-center hover:bg-red-500 active:scale-95 transition-all shadow-lg shadow-red-600/30">
                <svg x-show="isPlaying" class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                </svg>
                <svg x-show="!isPlaying" class="w-8 h-8 text-white ml-1" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M8 5v14l11-7z"/>
                </svg>
            </button>
            {{-- Siguiente --}}
            <button class="w-11 h-11 flex items-center justify-center text-gray-400 hover:text-white transition-colors" disabled>
                <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/>
                </svg>
            </button>
        </div>

        {{-- Control Live --}}
        <div class="w-full px-8 mt-6 flex justify-center" x-show="isLive">
            <button @click="togglePlay()"
                    class="w-20 h-20 rounded-full bg-red-600 flex items-center justify-center hover:bg-red-500 active:scale-95 transition-all shadow-xl shadow-red-600/30">
                <svg x-show="isPlaying" class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                </svg>
                <svg x-show="!isPlaying" class="w-10 h-10 text-white ml-1.5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M8 5v14l11-7z"/>
                </svg>
            </button>
        </div>

        {{-- Control de Volumen --}}
        <div class="w-full px-8 mt-6 flex items-center gap-3">
            <svg class="w-4 h-4 text-gray-500 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                <path d="M18.5 12c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM5 9v6h4l5 5V4L9 9H5zm7-.17v6.34L9.83 13H7v-2h2.83L12 8.83z"/>
            </svg>
            <input type="range"
                   class="lucille-range-slider flex-1"
                   :value="volume * 100"
                   min="0" max="100" step="1"
                   @input="setVolume($event.target.value / 100)"
                   @click.stop>
            <svg class="w-5 h-5 text-gray-400 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                <path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/>
            </svg>
        </div>

        <div class="h-16"></div>{{-- Espaciado inferior --}}
    </div>

    {{-- ═══════════════════════════════════════════════
         BOTTOM NAVIGATION BAR
    ═══════════════════════════════════════════════ --}}
    <nav id="pwa-bottom-nav">
        {{-- Inicio --}}
        <a href="/app"
           class="nav-tab pwa-nav-link {{ request()->is('app') && !request()->is('app/*') ? 'active' : '' }}"
           data-href="/app"
           id="nav-index">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            <span>Inicio</span>
        </a>

        {{-- En Vivo --}}
        <a href="/app/live"
           class="nav-tab pwa-nav-link {{ request()->is('app/live') ? 'active' : '' }}"
           data-href="/app/live"
           id="nav-live">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/>
            </svg>
            <span>En Vivo</span>
        </a>

        {{-- Podcasts --}}
        <a href="/app/podcasts"
           class="nav-tab pwa-nav-link {{ request()->is('app/podcasts') ? 'active' : '' }}"
           data-href="/app/podcasts"
           id="nav-podcasts">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
            </svg>
            <span>Podcasts</span>
        </a>

        {{-- Mi Música --}}
        <a href="/app/library"
           class="nav-tab pwa-nav-link {{ request()->is('app/library') ? 'active' : '' }}"
           data-href="/app/library"
           id="nav-library">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
            </svg>
            <span>Mi Música</span>
        </a>
    </nav>

    {{-- ═══════════════════════════════════════════════
         ALPINE.JS — Motor del reproductor PWA
    ═══════════════════════════════════════════════ --}}
    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('pwaPlayer', () => ({
            // ── Estado del reproductor ──────────────────────
            isPlaying:       false,
            isLive:          false,
            isExpanded:      false,
            isLoading:       false,
            volume:          1,
            currentTime:     0,
            duration:        0,
            progressPercent: 0,
            nowPlayingTimer: null,

            currentTrack: {
                src:    '',
                title:  'Seven Rock Radio',
                artist: 'En Vivo',
                cover:  '{{ asset('assets/lucille/album3.jpg') }}',
                type:   'live',   // 'live' | 'podcast' | 'song'
            },

            // ── Inicialización ─────────────────────────────
            init() {
                const audio = this.$refs.audio;

                // Restaurar volumen guardado
                const savedVol = parseFloat(localStorage.getItem('pwa_volume') || '1');
                this.volume = savedVol;
                audio.volume = savedVol;

                // SPA-lite: interceptar clics en los nav tabs
                this.setupNavigation();

                // Auto-play si el usuario venía escuchando
                const savedTrack = this.loadSavedTrack();
                if (savedTrack) {
                    this.currentTrack = savedTrack;
                    // No auto-play por política de autoplay de browsers
                }
            },

            // ── Controles de reproducción ──────────────────
            playLive() {
                const streamUrl = '{{ config('player.streams.listen') }}';
                const cover     = 'https://c30.radioboss.fm/w/artwork/569.jpg?t=' + Date.now();

                this.currentTrack = {
                    src:    streamUrl,
                    title:  'Seven Rock Radio',
                    artist: 'En Vivo',
                    cover:  cover,
                    type:   'live',
                };
                this.isLive = true;
                this.playAudio();
                this.startNowPlayingPolling();
            },

            playEpisode(episode) {
                this.currentTrack = {
                    src:    episode.src || '',
                    title:  episode.title || 'Episodio',
                    artist: episode.program || 'Podcast',
                    cover:  episode.cover  || '{{ asset('assets/lucille/album3.jpg') }}',
                    type:   'podcast',
                };
                this.isLive = false;
                this.stopNowPlayingPolling();
                this.playAudio();
                this.saveCurrentTrack();
            },

            playSong(song) {
                this.currentTrack = {
                    src:    song.src    || song.audio_url || '',
                    title:  song.title  || 'Canción',
                    artist: song.artist || 'Artista',
                    cover:  song.cover  || song.cover_image || '{{ asset('assets/lucille/album3.jpg') }}',
                    type:   'song',
                };
                this.isLive = false;
                this.stopNowPlayingPolling();
                this.playAudio();
                this.saveCurrentTrack();
            },

            playAudio() {
                const audio = this.$refs.audio;
                if (!audio || !this.currentTrack.src) return;

                this.isLoading = true;

                // Para live: evitar caché añadiendo timestamp
                const src = this.isLive
                    ? this.currentTrack.src + '?' + Date.now()
                    : this.currentTrack.src;

                audio.src = src;
                audio.load();
                audio.play()
                    .then(() => { this.isPlaying = true; this.isLoading = false; })
                    .catch(err => {
                        console.warn('[PWA Player] Error al reproducir:', err);
                        this.isLoading = false;
                    });
            },

            togglePlay() {
                const audio = this.$refs.audio;
                if (!audio) return;

                if (!audio.src && this.currentTrack.src) {
                    this.playAudio();
                    return;
                }

                if (this.isPlaying) {
                    audio.pause();
                    this.isPlaying = false;
                } else {
                    if (this.isLive) {
                        // Reload stream para no reproducir desde caché
                        audio.src = this.currentTrack.src + '?' + Date.now();
                        audio.load();
                    }
                    audio.play()
                        .then(() => { this.isPlaying = true; })
                        .catch(err => console.warn('[PWA Player]', err));
                }
            },

            // ── Progreso y tiempo ──────────────────────────
            onTimeUpdate() {
                const audio = this.$refs.audio;
                this.currentTime     = audio.currentTime;
                this.duration        = audio.duration || 0;
                this.progressPercent = this.duration
                    ? (this.currentTime / this.duration) * 100
                    : 0;
            },

            onEnded() {
                if (!this.isLive) {
                    this.isPlaying = false;
                }
            },

            seekTo(percent) {
                const audio = this.$refs.audio;
                if (audio && audio.duration && !this.isLive) {
                    audio.currentTime = (percent / 100) * audio.duration;
                }
            },

            setVolume(val) {
                const audio = this.$refs.audio;
                this.volume = parseFloat(val);
                if (audio) audio.volume = this.volume;
                localStorage.setItem('pwa_volume', this.volume);
            },

            formatTime(seconds) {
                if (!seconds || isNaN(seconds)) return '0:00';
                const m = Math.floor(seconds / 60);
                const s = Math.floor(seconds % 60).toString().padStart(2, '0');
                return `${m}:${s}`;
            },

            // ── Full Player Modal ──────────────────────────
            expand()  { this.isExpanded = true;  document.body.style.overflow = 'hidden'; },
            collapse() { this.isExpanded = false; document.body.style.overflow = ''; },

            // ── Now Playing polling (radio en vivo) ────────
            startNowPlayingPolling() {
                this.stopNowPlayingPolling();
                this.fetchNowPlaying();
                this.nowPlayingTimer = setInterval(() => this.fetchNowPlaying(), 15000);
            },

            stopNowPlayingPolling() {
                if (this.nowPlayingTimer) {
                    clearInterval(this.nowPlayingTimer);
                    this.nowPlayingTimer = null;
                }
            },

            async fetchNowPlaying() {
                try {
                    const res  = await fetch('/app/api/now-playing');
                    const data = await res.json();
                    if (this.isLive) {
                        this.currentTrack.title  = data.title  || this.currentTrack.title;
                        this.currentTrack.artist = data.artist || this.currentTrack.artist;
                        this.currentTrack.cover  = data.cover  || this.currentTrack.cover;
                    }
                } catch { /* Silencioso */ }
            },

            // ── Persistencia de sesión ─────────────────────
            saveCurrentTrack() {
                try {
                    sessionStorage.setItem('pwa_track', JSON.stringify(this.currentTrack));
                    sessionStorage.setItem('pwa_is_live', this.isLive);
                } catch { /* Safari private */ }
            },

            loadSavedTrack() {
                try {
                    const raw = sessionStorage.getItem('pwa_track');
                    return raw ? JSON.parse(raw) : null;
                } catch {
                    return null;
                }
            },

            // ── SPA-lite Navigation (fetch + innerHTML swap) ──
            setupNavigation() {
                document.addEventListener('click', (e) => {
                    const link = e.target.closest('.pwa-nav-link');
                    if (!link) return;

                    const href = link.getAttribute('data-href');
                    if (!href) return;

                    e.preventDefault();

                    // Actualizar tab activo visualmente
                    document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
                    const activeTab = document.querySelector(`[data-href="${href}"]`);
                    if (activeTab) activeTab.classList.add('active');

                    // Fetch del contenido y swap
                    this.loadPage(href);
                });
            },

            async loadPage(url) {
                const content = document.getElementById('pwa-content');
                if (!content) return;

                // Skeleton mientras carga
                content.style.opacity = '0.5';
                content.style.transition = 'opacity 0.15s';

                try {
                    const res  = await fetch(url, {
                        headers: { 'X-PWA-Fragment': '1' }
                    });
                    const html = await res.text();

                    // Extraer solo el contenido del slot (entre los markers)
                    const parser = new DOMParser();
                    const doc    = parser.parseFromString(html, 'text/html');
                    const slot   = doc.getElementById('pwa-content');

                    if (slot) {
                        content.innerHTML = slot.innerHTML;
                    } else {
                        content.innerHTML = doc.body.innerHTML;
                    }

                    window.history.pushState({}, '', url);
                    document.getElementById('pwa-main').scrollTop = 0;
                } catch (err) {
                    console.warn('[PWA Nav]', err);
                    window.location.href = url; // Fallback navegación normal
                }

                content.style.opacity = '1';
            },
        }));
    });

    // Registrar Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js', { scope: '/app' })
                .then(reg => console.log('[PWA] Service Worker registrado:', reg.scope))
                .catch(err => console.warn('[PWA] SW error:', err));
        });
    }
    </script>

</body>
</html>
