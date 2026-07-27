{{--
    Vista: PWA En Vivo
    Layout: layouts.pwa
    Datos: $streamUrl, $artworkUrl, $stationLogo, $stationId
--}}
<x-layouts.pwa title="En Vivo — Seven Rock Radio">

<div class="min-h-screen flex flex-col" style="background: linear-gradient(180deg, #1a0000 0%, #0e0000 30%, #121212 100%);">

    {{-- ═══════════════════════════════════════════════
         HERO — SEÑAL EN VIVO
    ═══════════════════════════════════════════════ --}}
    <div class="flex-1 flex flex-col items-center justify-center px-6 pt-8 pb-4">

        {{-- Artwork / Logo de la estación --}}
        <div class="relative mb-8" x-data>
            {{-- Anillo exterior animado --}}
            <div class="absolute inset-0 rounded-full border-2 border-red-600/30 animate-ping"
                 style="animation-duration: 2s;"></div>
            <div class="absolute inset-[-8px] rounded-full border border-red-600/20"
                 x-bind:class="$store?.pwa?.isPlaying ? 'animate-pulse' : ''"></div>

            {{-- Artwork principal --}}
            <div class="relative w-52 h-52 rounded-full overflow-hidden border-4 border-red-600/40 shadow-2xl shadow-red-900/40">
                <img id="live-artwork"
                     src="{{ $artworkUrl }}"
                     alt="Seven Rock Radio — En Vivo"
                     class="w-full h-full object-cover"
                     onerror="this.src='{{ asset('assets/lucille/album3.jpg') }}'">
                {{-- Overlay oscuro --}}
                <div class="absolute inset-0 bg-black/20"></div>
            </div>

            {{-- Badge LIVE --}}
            <div class="absolute -bottom-3 left-1/2 -translate-x-1/2 bg-red-600 text-white text-xs font-bold px-4 py-1 rounded-full uppercase tracking-widest shadow-lg">
                🔴 En Vivo
            </div>
        </div>

        {{-- Info Now Playing (actualizable via JS) --}}
        <div class="text-center mb-6 w-full max-w-xs">
            <h1 class="font-display text-2xl font-bold text-white leading-tight mb-1"
                id="live-title"
                x-text="(isLive && currentTrack.title) ? currentTrack.title : 'Seven Rock Radio'">
                Seven Rock Radio
            </h1>
            <p class="text-base text-gray-400"
               id="live-artist"
               x-text="(isLive && currentTrack.artist) ? currentTrack.artist : 'En el aire · 24/7'">
               En el aire · 24/7
            </p>
        </div>

        {{-- Visualizador de olas --}}
        <div class="flex items-end gap-1 h-12 mb-8">
            @for($i = 0; $i < 20; $i++)
            <div class="wave-bar rounded"
                 style="
                    height: {{ rand(12, 48) }}px;
                    animation-delay: {{ number_format($i * 0.07, 2) }}s;
                    animation-duration: {{ number_format(0.6 + ($i % 5) * 0.12, 2) }}s;
                    opacity: {{ $i < 4 || $i > 15 ? '0.3' : '1' }};
                 ">
            </div>
            @endfor
        </div>

        {{-- Botón Play/Pause central --}}
        <button @click="isLive && currentTrack.src ? togglePlay() : playLive()"
                class="btn-accent w-20 h-20 rounded-full flex items-center justify-center mb-6 shadow-2xl shadow-red-900/50 hover:scale-105 active:scale-95 transition-transform">
            <svg x-show="isPlaying && isLive" class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 24 24">
                <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
            </svg>
            <svg x-show="!(isPlaying && isLive)" class="w-10 h-10 text-white ml-1.5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M8 5v14l11-7z"/>
            </svg>
        </button>

        {{-- Cargando indicador --}}
        <div x-show="isLoading" class="text-sm text-red-400 mb-2 animate-pulse">
            Conectando señal...
        </div>

        {{-- Info de la emisora --}}
        <p class="text-xs text-gray-600 text-center">
            Señal digital · Seven Rock Radio © {{ date('Y') }}
        </p>
    </div>

    {{-- ═══════════════════════════════════════════════
         HISTORIAL DE TRACKS RECIENTES
    ═══════════════════════════════════════════════ --}}
    <div class="px-4 pb-4">
        <h2 class="section-heading mb-3 text-sm">Reproducido Recientemente</h2>

        <div class="rounded-xl overflow-hidden border border-[#2a2a2a]">
            {{-- Script de RadioBoss para "recent tracks" --}}
            <div id="recent-tracks-container" class="space-y-0">
                {{-- Placeholder rows --}}
                @for($i = 0; $i < 4; $i++)
                <div class="flex items-center gap-3 px-4 py-3 {{ $i < 3 ? 'border-b border-[#1e1e1e]' : '' }} bg-[#1a1a1a]">
                    <div class="w-9 h-9 rounded skeleton shrink-0"></div>
                    <div class="flex-1 min-w-0 space-y-1.5">
                        <div class="skeleton h-3 rounded w-3/4"></div>
                        <div class="skeleton h-2.5 rounded w-1/2"></div>
                    </div>
                    <div class="skeleton h-3 w-10 rounded"></div>
                </div>
                @endfor
            </div>
        </div>

        {{-- Historial real de RadioBoss — Alpine.js polling (/app/api/recent-tracks) --}}
        <div class="rounded-xl overflow-hidden border border-[#2a2a2a]"
             x-data="{
                tracks: [],
                loading: true,
                async fetchTracks() {
                    try {
                        const res  = await fetch('/app/api/recent-tracks');
                        const data = await res.json();
                        this.tracks  = data.tracks || [];
                    } catch {}
                    this.loading = false;
                }
             }"
             x-init="fetchTracks(); setInterval(() => fetchTracks(), 30000)">

            {{-- Skeleton mientras carga --}}
            <template x-if="loading">
                <div>
                    @for($i = 0; $i < 4; $i++)
                    <div class="flex items-center gap-3 px-4 py-3 {{ $i < 3 ? 'border-b border-[#1e1e1e]' : '' }} bg-[#1a1a1a]">
                        <div class="w-9 h-9 rounded skeleton shrink-0"></div>
                        <div class="flex-1 min-w-0 space-y-1.5">
                            <div class="skeleton h-3 rounded w-3/4"></div>
                            <div class="skeleton h-2.5 rounded w-1/2"></div>
                        </div>
                        <div class="skeleton h-3 w-10 rounded"></div>
                    </div>
                    @endfor
                </div>
            </template>

            {{-- Tracks reales --}}
            <template x-if="!loading && tracks.length > 0">
                <div>
                    <template x-for="(track, index) in tracks" :key="index">
                        <div class="flex items-center gap-3 px-4 py-3 bg-[#1a1a1a]"
                             :class="index < tracks.length - 1 ? 'border-b border-[#1e1e1e]' : ''">
                            <div class="w-9 h-9 rounded overflow-hidden bg-[#252525] shrink-0 flex items-center justify-center">
                                <svg class="w-4 h-4 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-white font-semibold truncate" x-text="track.title"></p>
                                <p class="text-xs text-gray-500 truncate" x-text="track.artist"></p>
                            </div>
                            <span class="text-[10px] text-gray-600 shrink-0" x-text="track.played_at"></span>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Sin datos --}}
            <template x-if="!loading && tracks.length === 0">
                <div class="px-4 py-6 text-center text-sm text-gray-600 bg-[#1a1a1a]">
                    El historial de reproducción no está disponible en este momento.
                </div>
            </template>
        </div>
    </div>

</div>

</x-layouts.pwa>

