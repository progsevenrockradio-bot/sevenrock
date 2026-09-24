@php
    // Obtenemos las fotos para poder extraer la imagen principal si no tiene logo
    $photoFiles = $media->where('type', 'photo')->values();
    
    $bestImage = $talent->logoUrl();
    if (!$bestImage && $photoFiles->isNotEmpty()) {
        $bestImage = $photoFiles->first()->url;
    }

    // Título principal: Solo el nombre de la banda (similar a como Spotify pone el nombre de la canción)
    $shareTitle = $talent->band_name;
    
    // Descripción: Estadísticas y el nombre de la plataforma (similar a "Banda • Canción • Año")
    $shareDesc = "Visitas: " . number_format($viewsCount) . " • Me gusta: " . number_format($likesCount) . " • Muro del Rock";

    // Si pertenece a una agencia (para los perfiles importados)
    if ($talent instanceof \App\Models\RadioArtistTalentFallback && $talent->agency_id) {
        $agency = $talent->agency;
        if ($agency) {
            $shareTitle = $talent->band_name . ' (' . $agency->name . ')';
        }
    }
@endphp

<x-layouts.site :title="$shareTitle"
    :description="$shareDesc"
    :og-image="route('talents.og-image', \Illuminate\Support\Str::slug($talent->band_name))"
    :twitter-card="'summary_large_image'">
    
    @php
        $planKey = strtolower((string) ($talent->plan ?? 'free'));
        $planColors = [
            'premium' => '#d4af37',
            'pro'     => '#3b82f6',
            'basic'   => '#10b981',
            'free'    => '#c32720', // Default lucille red
        ];
        $accentColor = $planColors[$planKey] ?? '#c32720';
    @endphp

    <section class="mx-auto max-w-7xl px-5 py-16" style="padding-top: 150px; --lucille-accent: {{ $accentColor }};">
        <!-- Profile Header -->
        <div class="relative overflow-hidden rounded-[20px] bg-[#070a0d] border border-white/10 p-8 md:p-12 text-center shadow-[0_20px_50px_rgba(0,0,0,0.5)]">
            <!-- Background Banner -->
            <div class="absolute inset-0 z-0 opacity-40 mix-blend-luminosity">
                <img src="{{ \App\Models\ThemeSetting::current()->talents_banner_url ?? asset('assets/lucille/dark-background.jpg') }}" alt="Banner" class="w-full h-full object-cover">
            </div>
            <!-- Overlay Gradient for Readability -->
            <div class="absolute inset-0 z-0 bg-gradient-to-t from-[#10151a] via-[#10151a]/60 to-transparent"></div>
            
            <div class="absolute inset-0 z-0 opacity-40 pointer-events-none bg-[radial-gradient(circle_at_center,var(--lucille-accent),transparent_70%)]"></div>
            <div class="relative z-10 flex flex-col items-center">
                <div class="relative group">
                    <div class="absolute inset-0 rounded-full blur-[15px] opacity-60 bg-[var(--lucille-accent)] group-hover:opacity-85 transition-opacity duration-300"></div>
                    <div class="relative h-[160px] w-[160px] overflow-hidden rounded-full border-4 border-white/15 hover:border-[var(--lucille-accent)] transition-colors duration-300 shadow-2xl">
                        <img src="{{ $bestImage ?? asset('assets/lucille/beatles_t_shirt.jpeg') }}" alt="{{ $talent->band_name }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110" loading="lazy" width="160" height="160">
                    </div>
                </div>
                <h1 class="font-display text-4xl md:text-6xl uppercase tracking-[.18em] mt-6 drop-shadow-[0_4px_8px_rgba(0,0,0,0.8)] bg-clip-text text-transparent" style="background-image: linear-gradient(rgba(255,255,255,0.75), rgba(200,200,200,0.65)), url('{{ asset('assets/lucille/rock-wall.jpg') }}'); background-size: cover; background-position: center; text-shadow: 0 10px 30px rgba(0,0,0,0.5);">{{ $talent->band_name }}</h1>
                <div class="mt-4 flex flex-wrap justify-center items-center gap-3 relative z-10">
                    @php
                        $planBadgeThemes = [
                            'premium' => 'border-[#d4af37]/40 bg-[#d4af37]/15 text-[#d4af37] shadow-[0_0_15px_rgba(212,175,55,0.25)]',
                            'pro'     => 'border-[#3b82f6]/40 bg-[#3b82f6]/15 text-[#60a5fa] shadow-[0_0_15px_rgba(59,130,246,0.25)]',
                            'basic'   => 'border-[#10b981]/40 bg-[#10b981]/15 text-[#34d399] shadow-[0_0_15px_rgba(16,185,129,0.25)]',
                            'free'    => 'border-[#c32720]/40 bg-[#c32720]/15 text-[#ff6b6b] shadow-[0_0_15px_rgba(195,39,32,0.25)]',
                        ];
                        $headerBadgeStyle = $planBadgeThemes[$planKey] ?? $planBadgeThemes['free'];
                    @endphp

                    <span class="inline-flex items-center gap-2 rounded-full border px-4.5 py-1.5 text-xs font-bold uppercase tracking-[.15em] {{ $headerBadgeStyle }}">
                        Plan: {{ ucfirst($talent->plan) }}
                    </span>
                    @if ($talent->is_featured)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-[#d4af37]/40 bg-[#d4af37]/15 px-4.5 py-1.5 text-xs font-bold uppercase tracking-[.15em] text-[#d4af37] shadow-[0_0_15px_rgba(212,175,55,0.35)] animate-pulse">
                            ⭐ Destacado
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="flex justify-center gap-6 md:gap-12 flex-wrap rounded-[16px] border border-white/10 bg-[#10151a]/60 backdrop-blur-md px-6 py-5 shadow-lg max-w-3xl mx-auto -mt-6 relative z-20 items-center">
            <div class="text-center px-4">
                <span class="block font-display text-3xl font-bold text-white like-count">{{ $likesCount }}</span>
                <span class="text-[10px] uppercase tracking-[.2em] text-gray-400">Likes</span>
            </div>
            <div class="h-10 w-[1px] bg-white/10 self-center hidden sm:block"></div>
            <div class="text-center px-4">
                <span class="block font-display text-3xl font-bold text-white">{{ $media->count() }}</span>
                <span class="text-[10px] uppercase tracking-[.2em] text-gray-400">Archivos</span>
            </div>
            <div class="h-10 w-[1px] bg-white/10 self-center hidden sm:block"></div>
            <div class="text-center px-4">
                <span class="block font-display text-3xl font-bold text-white">{{ $viewsCount }}</span>
                <span class="text-[10px] uppercase tracking-[.2em] text-gray-400">Visitas</span>
            </div>
            <div class="h-10 w-[1px] bg-white/10 self-center hidden sm:block"></div>
            
            <!-- Botón Compartir -->
            <button type="button" 
                class="text-center px-4 flex flex-col justify-center items-center cursor-pointer hover:scale-105 transition-transform group"
                x-data="{
                    shareProfile() {
                        if (navigator.share) {
                            navigator.share({
                                title: '{{ addslashes($shareTitle) }}',
                                url: '{{ route('talents.show', \Illuminate\Support\Str::slug($talent->band_name)) }}'
                            }).catch(console.error);
                        } else {
                            navigator.clipboard.writeText('{{ route('talents.show', \Illuminate\Support\Str::slug($talent->band_name)) }}');
                            alert('Enlace copiado al portapapeles');
                        }
                    }
                }"
                @click="shareProfile"
            >
                <div class="flex items-center justify-center w-[36px] h-[36px] rounded-full bg-[var(--lucille-accent)]/20 text-[var(--lucille-accent)] mb-1.5 group-hover:bg-[var(--lucille-accent)] group-hover:text-white transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                </div>
                <span class="text-[10px] uppercase tracking-[.2em] text-[var(--lucille-accent)] font-bold group-hover:text-white transition-colors">Compartir</span>
            </button>
        </div>

        <!-- Main Layout Grid -->
        @php
            $audioFiles = $media->where('type', 'mp3')->values();
            $photoFiles = $media->where('type', 'photo')->values();
            $otherMedia = $media->whereNotIn('type', ['mp3', 'photo'])->values();

            // MOCK DATA PARA PREVISUALIZACIÓN: Si la banda no tiene archivos, mostramos estos de prueba
            if ($audioFiles->isEmpty()) {
                $audioFiles = collect([
                    (object)[
                        'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3',
                        'title' => 'Canción de Rock Demo 1',
                        'filename' => 'demo-1.mp3'
                    ],
                    (object)[
                        'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3',
                        'title' => 'Riff Potente Demo 2',
                        'filename' => 'demo-2.mp3'
                    ],
                    (object)[
                        'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-3.mp3',
                        'title' => 'Balada Acústica Demo 3',
                        'filename' => 'demo-3.mp3'
                    ]
                ]);
            }
            if ($photoFiles->isEmpty()) {
                $photoFiles = collect([
                    (object)[
                        'url' => asset('assets/lucille/muro-del-rock-banner.png'),
                        'title' => 'Foto Demo 1'
                    ],
                    (object)[
                        'url' => asset('assets/lucille/dark-background.jpg'),
                        'title' => 'Foto Demo 2'
                    ],
                    (object)[
                        'url' => asset('assets/lucille/beatles_t_shirt.jpeg'),
                        'title' => 'Foto Demo 3'
                    ],
                    (object)[
                        'url' => asset('assets/lucille/logo.png'),
                        'title' => 'Foto Demo 4'
                    ]
                ]);
            }
        @endphp
        <div class="grid gap-8 lg:grid-cols-[1.2fr_.8fr] mt-12 items-start">
            <!-- Left Column: Biography, Media, Store, etc. -->
            <div class="space-y-8">
                <!-- Biography Panel -->
                <div class="border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-6 md:p-8 shadow-xl">
                    <h3 class="font-display text-xl uppercase tracking-[.18em] text-white border-b border-white/5 pb-3">Biografía</h3>
                    <p class="mt-4 text-sm leading-8 text-gray-300 font-sans whitespace-pre-line">{{ $talent->bio ?: 'Este artista aún no ha escrito su biografía.' }}</p>
                </div>

                <!-- Social Networks Panel -->
                @php($socialLinks = $talent->socialLinkMap())
                @if ($socialLinks !== [])
                    <div class="border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-6 md:p-8 shadow-xl">
                        <h3 class="font-display text-xl uppercase tracking-[.18em] text-white border-b border-white/5 pb-3">Sigue al artista</h3>
                        <div class="mt-5 flex flex-wrap gap-3">
                            @foreach ($socialLinks as $network => $url)
                                <a href="{{ $url }}" target="_blank" rel="noreferrer" class="inline-flex items-center gap-2 px-4 py-2 border border-white/20 text-white hover:border-[var(--lucille-accent)] hover:text-[var(--lucille-accent)] transition-all text-xs font-bold uppercase tracking-wider bg-transparent">
                                    @if ($network === 'instagram')
                                        <svg class="w-4 h-4 opacity-70" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                                    @elseif ($network === 'youtube')
                                        <svg class="w-4 h-4 opacity-70" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                    @elseif ($network === 'spotify')
                                        <svg class="w-4 h-4 opacity-70" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.495 17.29a.625.625 0 0 1-.86.208c-2.355-1.44-5.32-1.765-8.812-.966a.626.626 0 1 1-.295-1.216c3.82-.876 7.108-.501 9.759 1.115a.625.625 0 0 1 .208.859zm1.233-2.766a.78.78 0 0 1-1.074.258c-2.707-1.664-6.848-2.147-10.02-.117a.78.78 0 1 1-.84-1.31c3.633-2.324 8.243-1.786 11.365.132a.78.78 0 0 1 .57 1.037zm.116-2.903c-3.26-1.934-8.636-2.115-11.758-1.17a.974.974 0 1 1-.555-1.867c3.565-1.077 9.52-1.006 13.314 1.246a.976.976 0 0 1-.99 1.792z"/></svg>
                                    @elseif ($network === 'tiktok')
                                        <svg class="w-4 h-4 opacity-70" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.04.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93v7.02c-.01 1.63-.52 3.26-1.51 4.56-1.11 1.46-2.92 2.43-4.72 2.75-1.8.31-3.69.12-5.32-.64-1.66-.77-2.98-2.22-3.61-3.95-.61-1.68-.61-3.6.09-5.26.7-1.66 2.05-3.04 3.75-3.75 1.68-.7 3.63-.71 5.33-.12V12.7c-1.3-.39-2.71-.34-3.94.18-.89.37-1.65 1.01-2.12 1.83-.48.81-.66 1.8-.46 2.74.2 1 .74 1.9 1.5 2.5 1 .79 2.45 1.09 3.69.75 1.11-.29 2.06-1.11 2.53-2.14.46-1 .61-2.13.56-3.2V.02z"/></svg>
                                    @else
                                        <svg class="w-4 h-4 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                    @endif
                                    <span>{{ ucfirst($network) }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Multimedia Content Panel -->
                @if ($otherMedia->isNotEmpty())
                    <div class="border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-6 md:p-8 shadow-xl">
                        <h3 class="font-display text-xl uppercase tracking-[.18em] text-white border-b border-white/5 pb-3">Contenido</h3>
                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            @foreach ($otherMedia as $item)
                                <article class="overflow-hidden rounded-[12px] bg-white/[0.02] border border-white/5 p-4 hover:border-white/15 hover:bg-white/[0.04] transition-all duration-300 group" data-type="{{ $item->type }}">
                                    @if ($item->is_exclusive && !Auth::guard('web')->check() && !Auth::guard('talent')->check())
                                        <div class="p-4 bg-black/40 rounded-[8px] text-center flex flex-col justify-between items-center h-full min-h-[140px]">
                                            <div>
                                                <div class="h-9 w-9 mx-auto rounded-full bg-[var(--lucille-accent)]/10 border border-[var(--lucille-accent)]/20 text-[var(--lucille-accent)] flex items-center justify-center text-base">
                                                    🔒
                                                </div>
                                                <h4 class="mt-3 font-display text-xs font-bold uppercase tracking-wide text-white truncate max-w-full" title="{{ $item->title ?: $item->filename }}">
                                                    {{ $item->title ?: $item->filename }}
                                                </h4>
                                                <p class="mt-1 text-[10px] text-[#7b7b7b] leading-relaxed">
                                                    Exclusivo para Afiliados
                                                </p>
                                            </div>
                                            <a href="{{ route('afiliados.register') }}" class="lucille-button text-[9px] uppercase py-1 px-3 tracking-wider mt-3 rounded-[6px] w-full text-center">
                                                Registrarse Gratis
                                            </a>
                                        </div>
                                    @else
                                        @if ($item->type === 'video')
                                             <div class="relative group aspect-video w-full overflow-hidden rounded-[8px] bg-black/60 border border-white/5 shadow-md" x-data="{
                                                 playing: false,
                                                 video: null,
                                                 init() {
                                                     this.video = this.$refs.videoElement;
                                                 },
                                                 togglePlay() {
                                                     if (this.playing) {
                                                         this.video.pause();
                                                     } else {
                                                         window.dispatchEvent(new CustomEvent('stop-all-audio'));
                                                         this.video.play();
                                                     }
                                                 }
                                             }">
                                                 <video x-ref="videoElement" :controls="playing" @play="playing = true" @pause="playing = false" @ended="playing = false" src="{{ $item->url }}" class="h-full w-full object-cover" preload="none" width="400" height="225"></video>
                                                 
                                                 <!-- Premium Play Button Overlay (hides when playing) -->
                                                 <div x-show="!playing" class="absolute inset-0 flex items-center justify-center bg-black/35 group-hover:bg-black/15 transition-all duration-300">
                                                     <button type="button" @click="togglePlay()" class="h-14 w-14 rounded-full bg-[var(--lucille-accent)] text-white flex items-center justify-center shadow-[0_6px_25px_rgba(195,39,32,0.45)] hover:scale-110 active:scale-95 transition-all duration-300" aria-label="Reproducir video">
                                                         <svg class="h-6 w-6 fill-current ml-1" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                                     </button>
                                                 </div>
                                             </div>
                                             <p class="mt-3 text-sm font-semibold text-white truncate">{{ $item->title ?: $item->filename }}</p>
                                        @else
                                            <div class="flex items-center gap-3 p-2">
                                                <span class="text-2xl">📄</span>
                                                <div class="min-w-0 flex-1">
                                                    <a href="{{ $item->url }}" target="_blank" class="text-sm font-semibold text-white hover:underline truncate block">{{ $item->title ?? $item->filename }}</a>
                                                    <span class="text-[10px] text-gray-400 uppercase tracking-wider">Documento</span>
                                                </div>
                                            </div>
                                        @endif
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Store Panel -->
                @if (($products ?? collect())->isNotEmpty())
                    <div class="border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-6 md:p-8 shadow-xl">
                        <h3 class="font-display text-xl uppercase tracking-[.18em] text-white border-b border-white/5 pb-3">🛒 Tienda de {{ $talent->band_name }}</h3>
                        <p class="mt-2 text-xs text-gray-400 italic">
                            * Las ventas son gestionadas directamente por la banda. Seven Rock Radio actúa solo como escaparate.
                        </p>
                        <div class="mt-6 grid gap-6 sm:grid-cols-2">
                            @foreach ($products as $product)
                                <div class="store-card border border-white/5 bg-white/[0.02] rounded-[12px] p-5 hover:border-[var(--lucille-accent)]/30 hover:bg-white/[0.04] transition-all duration-300 flex flex-col justify-between group">
                                    <div>
                                        @if ($product->image_url)
                                            <div class="aspect-square w-full overflow-hidden rounded-[8px] bg-black/20 mb-4 relative">
                                                <img src="{{ $product->image_url }}" alt="{{ $product->title }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-103" loading="lazy" width="200" height="200">
                                            </div>
                                        @endif
                                        <h4 class="font-display text-lg text-white font-medium uppercase tracking-[.08em]">{{ $product->title }}</h4>
                                        <p class="mt-1 font-display text-xl font-bold text-[var(--lucille-accent)]">{{ number_format((float) $product->price, 2) }} €</p>
                                        <p class="mt-2 text-xs text-gray-400 leading-relaxed line-clamp-3">{{ \Illuminate\Support\Str::limit((string) $product->description, 100) }}</p>
                                    </div>
                                    @if ($product->external_payment_url)
                                        <a href="{{ $product->external_payment_url }}" target="_blank" rel="nofollow noopener" class="mt-4 inline-flex items-center justify-center min-h-[2.4rem] rounded-full bg-[#00d165] text-white text-xs font-bold uppercase tracking-[.12em] hover:bg-[#00b959] transition-all shadow-[0_4px_15px_rgba(0,209,101,0.25)]">
                                            {{ $product->external_payment_label ?: 'Comprar' }} ↗
                                        </a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Payment Methods Panel -->
                @php($paymentLinks = $talent->paymentLinkMap())
                @if ($paymentLinks !== [])
                    <div class="border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-6 md:p-8 shadow-xl">
                        <h3 class="font-display text-xl uppercase tracking-[.18em] text-white border-b border-white/5 pb-3">Acepto pagos vía</h3>
                        <div class="mt-4 flex flex-wrap gap-3">
                            @foreach ($paymentLinks as $label => $url)
                                <a href="{{ $url }}" target="_blank" rel="nofollow noopener" class="lucille-button flex items-center gap-2 hover:border-[var(--lucille-accent)] hover:text-white transition-all">
                                    <span>{{ ucfirst($label) }}</span>
                                    <span class="text-[10px]">↗</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- Right Column: Likes, Comments, Player, Gallery -->
            <div class="space-y-8">
                <!-- Music Player Panel -->
                @if ($audioFiles->isNotEmpty())
                    <div class="border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-6 shadow-xl" x-data="audioPlaylist()">
                        <h3 class="font-display text-xl uppercase tracking-[.18em] text-white border-b border-white/5 pb-3 mb-5">Reproductor</h3>
                        <!-- Player UI -->
                        <div class="bg-black/40 rounded-[12px] p-4 border border-white/5 mb-4">
                            <div class="flex items-center gap-4">
                                <button type="button" @click="togglePlay()" class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[var(--lucille-accent)] text-white hover:scale-105 transition-transform shadow-[0_4px_15px_rgba(195,39,32,0.4)]" aria-label="Reproducir/Pausar">
                                    <template x-if="!playing">
                                        <svg class="h-6 w-6 fill-current ml-1" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                    </template>
                                    <template x-if="playing">
                                        <svg class="h-6 w-6 fill-current" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                                    </template>
                                </button>
                                <div class="min-w-0 flex-1">
                                    <p class="line-clamp-2 text-sm md:text-base font-bold text-white uppercase tracking-wider" x-text="currentTrackTitle">Selecciona una pista</p>
                                    <div class="mt-2 flex items-center gap-3">
                                        <input type="range" min="0" max="100" :value="duration ? (currentTime / duration) * 100 : 0" @input="seek($event)" class="audio-slider flex-1" :disabled="!currentTrackUrl">
                                        <span class="text-[10px] md:text-xs text-gray-400 font-mono shrink-0" x-text="formatTime(currentTime) + ' / ' + (duration ? formatTime(duration) : '0:00')">0:00 / 0:00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Playlist -->
                        <div class="space-y-2 max-h-[250px] overflow-y-auto pr-2 custom-scrollbar">
                            @foreach ($audioFiles as $index => $item)
                                <button type="button" @click="playTrack({{ $index }}, '{{ $item->url }}', '{{ addslashes($item->title ?: $item->filename) }}')" 
                                    class="w-full text-left flex items-center justify-between p-3 rounded-[8px] border transition-all duration-200 group"
                                    :class="currentIndex === {{ $index }} ? 'border-[var(--lucille-accent)]/50 bg-[var(--lucille-accent)]/10' : 'border-transparent hover:bg-white/5'">
                                    <div class="flex items-start gap-3 flex-1 min-w-0 pr-2">
                                        <span class="text-xs font-mono shrink-0 mt-0.5" :class="currentIndex === {{ $index }} ? 'text-[var(--lucille-accent)]' : 'text-gray-500'">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                        <span class="text-sm line-clamp-2" :class="currentIndex === {{ $index }} ? 'text-white font-semibold' : 'text-gray-300 group-hover:text-white'">{{ $item->title ?: $item->filename }}</span>
                                    </div>
                                    <template x-if="currentIndex === {{ $index }} && playing">
                                        <!-- Animated bars -->
                                        <div class="flex items-end gap-[2px] h-4 shrink-0 ml-2">
                                            <div class="w-1 h-2 bg-[var(--lucille-accent)] animate-pulse"></div>
                                            <div class="w-1 h-4 bg-[var(--lucille-accent)] animate-pulse" style="animation-delay: 150ms"></div>
                                            <div class="w-1 h-3 bg-[var(--lucille-accent)] animate-pulse" style="animation-delay: 300ms"></div>
                                        </div>
                                    </template>
                                </button>
                            @endforeach
                        </div>
                        <!-- Audio element (hidden) -->
                        <audio x-ref="audioPlayer" @durationchange="duration = $event.target.duration" @timeupdate="currentTime = $event.target.currentTime" @ended="nextTrack()"></audio>
                    </div>
                @endif

                <!-- Image Gallery Panel -->
                @if ($photoFiles->isNotEmpty())
                    <div class="border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-6 shadow-xl" 
                         x-data="{ 
                             lightboxOpen: false, 
                             activeIndex: 0,
                             photos: {{ json_encode($photoFiles->map(fn($p) => ['url' => $p->url, 'title' => (string) $p->title])->values()->all()) }},
                             next() {
                                 if (this.activeIndex < this.photos.length - 1) this.activeIndex++;
                             },
                             prev() {
                                 if (this.activeIndex > 0) this.activeIndex--;
                             }
                         }">
                        <h3 class="font-display text-xl uppercase tracking-[.18em] text-white border-b border-white/5 pb-3 mb-5">Galería</h3>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-2 xl:grid-cols-3 gap-3">
                            @foreach ($photoFiles as $index => $photo)
                                <button type="button" @click="activeIndex = {{ $index }}; lightboxOpen = true" class="group relative aspect-square overflow-hidden rounded-[8px] bg-black/40 border border-white/5 hover:border-[var(--lucille-accent)]/50 transition-all focus:outline-none">
                                    <img src="{{ $photo->url }}" alt="{{ $photo->title }}" loading="lazy" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110">
                                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors flex items-center justify-center">
                                        <svg class="w-6 h-6 text-white opacity-0 group-hover:opacity-100 transition-opacity drop-shadow-md" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" /></svg>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                        
                        <!-- Lightbox Modal (Coverflow Carousel) -->
                        <template x-teleport="body">
                            <div x-show="lightboxOpen" style="display: none;"
                                 x-transition:enter="transition ease-out duration-300"
                                 x-transition:enter-start="opacity-0"
                                 x-transition:enter-end="opacity-100"
                                 x-transition:leave="transition ease-in duration-300"
                                 x-transition:leave-start="opacity-100"
                                 x-transition:leave-end="opacity-0"
                                 class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/95 backdrop-blur-xl"
                                 @keydown.escape.window="lightboxOpen = false"
                                 @keydown.right.window="if(lightboxOpen) next()"
                                 @keydown.left.window="if(lightboxOpen) prev()">
                                 
                                <button type="button" @click="lightboxOpen = false" class="absolute top-6 right-6 z-50 text-white/50 hover:text-white transition-colors p-2" aria-label="Cerrar">
                                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>

                                <div class="relative w-full max-w-[100vw] h-[75vh] flex items-center justify-center overflow-hidden">
                                    <template x-for="(photo, index) in photos" :key="index">
                                        <div class="absolute transition-all duration-500 ease-out flex flex-col items-center justify-center"
                                             :class="{
                                                 'z-50 scale-100 opacity-100 translate-x-0': index === activeIndex,
                                                 'z-40 scale-75 opacity-50 -translate-x-[60%] md:-translate-x-[50%] blur-[2px] hover:opacity-75 cursor-pointer': index === activeIndex - 1,
                                                 'z-40 scale-75 opacity-50 translate-x-[60%] md:translate-x-[50%] blur-[2px] hover:opacity-75 cursor-pointer': index === activeIndex + 1,
                                                 'z-30 scale-50 opacity-0 -translate-x-[120%] pointer-events-none': index < activeIndex - 1,
                                                 'z-30 scale-50 opacity-0 translate-x-[120%] pointer-events-none': index > activeIndex + 1
                                             }"
                                             @click="
                                                 if (index === activeIndex - 1) prev();
                                                 else if (index === activeIndex + 1) next();
                                             ">
                                            <img :src="photo.url" :alt="photo.title" class="max-h-[75vh] max-w-[85vw] md:max-w-[70vw] object-contain rounded-[12px] shadow-[0_20px_50px_rgba(0,0,0,0.5)] border border-white/10">
                                            
                                            <div x-show="index === activeIndex && photo.title" x-transition.opacity.duration.300ms class="absolute -bottom-12 text-center w-full">
                                                <span class="text-white/80 font-sans text-sm tracking-wide" x-text="photo.title"></span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                
                                <!-- Mobile Navigation Controls -->
                                <div class="absolute bottom-8 flex gap-6 z-50 md:hidden">
                                    <button @click="prev()" :class="activeIndex === 0 ? 'opacity-30 cursor-not-allowed' : 'opacity-80 hover:opacity-100'" class="p-3 bg-white/10 rounded-full backdrop-blur-md">
                                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                    </button>
                                    <button @click="next()" :class="activeIndex === photos.length - 1 ? 'opacity-30 cursor-not-allowed' : 'opacity-80 hover:opacity-100'" class="p-3 bg-white/10 rounded-full backdrop-blur-md">
                                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                @endif

                <!-- Like Button & Comment Form Panel -->
                <div class="border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-6 md:p-8 shadow-xl space-y-6">
                    <div>
                        <button type="button" class="btn-like w-full justify-center content-reaction-button flex items-center gap-3 transition-transform active:scale-97 {{ $hasLiked ? 'is-active' : '' }}" data-band="{{ $talent->band_name }}">
                            <span class="content-reaction-button__icon text-lg">♥</span>
                            <span class="font-display uppercase tracking-[.15em]">Me Gusta</span>
                            <span class="like-count content-reaction-count ml-2">{{ $likesCount }}</span>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('talents.comment', ['bandName' => $talent->band_name]) }}" class="comment-section pt-5 border-t border-white/5">
                        @csrf
                        <div class="hidden" style="display:none !important" aria-hidden="true">
                            <input type="text" name="user_website" tabindex="-1" autocomplete="off">
                        </div>
                        <h4 class="font-display text-lg uppercase tracking-[.15em] text-white">Dejar un comentario</h4>
                        <textarea name="content" placeholder="Escribe un comentario..." maxlength="500" required class="mt-4 w-full rounded-[10px] border border-white/10 bg-black/40 p-4 text-sm text-white focus:border-[var(--lucille-accent)] focus:ring-0 outline-none transition-colors" rows="4"></textarea>
                        <button type="submit" class="mt-3 w-full lucille-button-solid">Publicar comentario</button>
                    </form>
                </div>

                <!-- Recent Comments List Panel -->
                <div class="border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-6 md:p-8 shadow-xl">
                    <h4 class="font-display text-lg uppercase tracking-[.18em] text-white border-b border-white/5 pb-3">Comentarios recientes</h4>
                    <div class="comments-list mt-5 space-y-4">
                        @forelse ($topComments as $comment)
                            <div class="comment-item bg-white/[0.02] border border-white/5 p-4 rounded-[12px] flex items-start gap-4 hover:border-white/10 transition-colors">
                                <div class="h-9 w-9 shrink-0 rounded-full bg-white/5 flex items-center justify-center text-base border border-white/10">
                                    🎸
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <strong class="text-white text-xs font-semibold uppercase tracking-wider">Anónimo</strong>
                                        <small class="text-[9px] text-gray-500 font-mono">{{ $comment->created_at->diffForHumans() }}</small>
                                    </div>
                                    <p class="mt-2 text-sm text-gray-300 leading-relaxed whitespace-pre-line">{{ $comment->content }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="text-sm text-gray-500 text-center py-4">Todavía no hay comentarios para este artista.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Related Bands Panel -->
                @if ($relatedByStyle->isNotEmpty() || $relatedByName->isNotEmpty() || $recommended->isNotEmpty())
                    <div class="border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-6 md:p-8 shadow-xl">
                        <h4 class="font-display text-lg uppercase tracking-[.18em] text-white border-b border-white/5 pb-3">Artistas Relacionados</h4>
                        <div class="space-y-6 mt-5">
                            @if ($relatedByStyle->isNotEmpty())
                                <div>
                                    <span class="text-[9px] uppercase tracking-[.2em] text-[var(--lucille-accent)] font-semibold font-display">Mismo Estilo</span>
                                    <div class="space-y-3 mt-2">
                                        @foreach ($relatedByStyle as $relBand)
                                            <a href="{{ route('talents.show', ['bandName' => $relBand->band_name]) }}" class="flex items-center gap-3 bg-white/[0.02] border border-white/5 p-2 rounded-[10px] hover:border-white/10 transition-colors">
                                                <div class="h-10 w-10 rounded-[6px] border border-white/10 overflow-hidden bg-black/20 shrink-0">
                                                    @if ($relBand->logoUrl())
                                                        <img src="{{ $relBand->logoUrl() }}" class="h-full w-full object-cover" alt="{{ $relBand->band_name }}" width="40" height="40" loading="lazy">
                                                    @else
                                                        <div class="h-full w-full flex items-center justify-center text-[8px] text-gray-500 uppercase tracking-widest">Logo</div>
                                                    @endif
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <strong class="text-white text-xs block truncate">{{ $relBand->band_name }}</strong>
                                                    <span class="text-[9px] text-gray-500 uppercase tracking-wider block font-mono">Plan {{ ucfirst($relBand->plan) }}</span>
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($relatedByName->isNotEmpty())
                                <div class="@if($relatedByStyle->isNotEmpty()) pt-4 border-t border-white/5 @endif">
                                    <span class="text-[9px] uppercase tracking-[.2em] text-[var(--lucille-accent)] font-semibold font-display">Nombres Similares</span>
                                    <div class="space-y-3 mt-2">
                                        @foreach ($relatedByName as $relBand)
                                            <a href="{{ route('talents.show', ['bandName' => $relBand->band_name]) }}" class="flex items-center gap-3 bg-white/[0.02] border border-white/5 p-2 rounded-[10px] hover:border-white/10 transition-colors">
                                                <div class="h-10 w-10 rounded-[6px] border border-white/10 overflow-hidden bg-black/20 shrink-0">
                                                    @if ($relBand->logoUrl())
                                                        <img src="{{ $relBand->logoUrl() }}" class="h-full w-full object-cover" alt="{{ $relBand->band_name }}" width="40" height="40" loading="lazy">
                                                    @else
                                                        <div class="h-full w-full flex items-center justify-center text-[8px] text-gray-500 uppercase tracking-widest">Logo</div>
                                                    @endif
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <strong class="text-white text-xs block truncate">{{ $relBand->band_name }}</strong>
                                                    <span class="text-[9px] text-gray-500 uppercase tracking-wider block font-mono">{{ $relBand->interacts }} interacciones</span>
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($recommended->isNotEmpty())
                                <div class="@if($relatedByStyle->isNotEmpty() || $relatedByName->isNotEmpty()) pt-4 border-t border-white/5 @endif">
                                    <span class="text-[9px] uppercase tracking-[.2em] text-[var(--lucille-accent)] font-semibold font-display">Recomendados del Muro</span>
                                    <div class="space-y-3 mt-2">
                                        @foreach ($recommended as $relBand)
                                            <a href="{{ route('talents.show', ['bandName' => $relBand->band_name]) }}" class="flex items-center gap-3 bg-white/[0.02] border border-white/5 p-2 rounded-[10px] hover:border-white/10 transition-colors">
                                                <div class="h-10 w-10 rounded-[6px] border border-white/10 overflow-hidden bg-black/20 shrink-0">
                                                    @if ($relBand->logoUrl())
                                                        <img src="{{ $relBand->logoUrl() }}" class="h-full w-full object-cover" alt="{{ $relBand->band_name }}" width="40" height="40" loading="lazy">
                                                    @else
                                                        <div class="h-full w-full flex items-center justify-center text-[8px] text-gray-500 uppercase tracking-widest">Logo</div>
                                                    @endif
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <strong class="text-white text-xs block truncate">{{ $relBand->band_name }}</strong>
                                                    <span class="text-[9px] text-[#ffd24d] font-semibold uppercase tracking-wider block font-mono">★ Destacado</span>
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('audioPlaylist', () => ({
                    playing: false,
                    duration: 0,
                    currentTime: 0,
                    currentIndex: -1,
                    currentTrackUrl: '',
                    currentTrackTitle: 'Selecciona una pista',
                    tracks: [
                        @foreach ($audioFiles as $item)
                            { url: '{{ $item->url }}', title: '{{ addslashes($item->title ?: $item->filename) }}' },
                        @endforeach
                    ],
                    init() {
                        if (this.tracks.length > 0) {
                            this.currentIndex = 0;
                            this.currentTrackUrl = this.tracks[0].url;
                            this.currentTrackTitle = this.tracks[0].title;
                            this.$refs.audioPlayer.src = this.currentTrackUrl;
                            this.$refs.audioPlayer.preload = 'none';
                        }
                    },
                    togglePlay() {
                        if (!this.currentTrackUrl) return;
                        if (this.playing) {
                            this.$refs.audioPlayer.pause();
                            this.playing = false;
                        } else {
                            window.dispatchEvent(new CustomEvent('stop-all-audio', { detail: { except: this.$refs.audioPlayer } }));
                            this.$refs.audioPlayer.play();
                            this.playing = true;
                        }
                    },
                    playTrack(index, url, title) {
                        if (this.currentIndex === index) {
                            this.togglePlay();
                            return;
                        }
                        this.currentIndex = index;
                        this.currentTrackUrl = url;
                        this.currentTrackTitle = title;
                        this.$refs.audioPlayer.src = url;
                        this.playing = false;
                        this.togglePlay();
                    },
                    nextTrack() {
                        if (this.currentIndex < this.tracks.length - 1) {
                            const next = this.tracks[this.currentIndex + 1];
                            this.playTrack(this.currentIndex + 1, next.url, next.title);
                        } else {
                            this.playing = false;
                        }
                    },
                    formatTime(secs) {
                        if (isNaN(secs)) return '0:00';
                        const minutes = Math.floor(secs / 60);
                        const seconds = Math.floor(secs % 60);
                        return minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
                    },
                    seek(event) {
                        const percent = event.target.value / 100;
                        this.$refs.audioPlayer.currentTime = percent * this.duration;
                    }
                }));
            });

            const btnLike = document.querySelector('.btn-like');
            if (btnLike) {
                btnLike.addEventListener('click', function () {
                    const band = this.dataset.band;

                    fetch(`/talentos/${encodeURIComponent(band)}/like`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                    })
                        .then((response) => response.json())
                        .then((data) => {
                            if (typeof data.likes !== 'undefined') {
                                document.querySelectorAll('.like-count').forEach((node) => {
                                    node.textContent = data.likes;
                                });
                                btnLike.classList.add('is-active');
                            }

                            if (data.error) {
                                alert(data.error);
                            }
                        });
                });
            }
        </script>
    @endpush
</x-layouts.site>
