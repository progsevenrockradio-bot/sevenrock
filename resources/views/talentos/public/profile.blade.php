@php
    // Obtenemos las fotos para poder extraer la imagen principal si no tiene logo
    $photoFiles = $media->where('type', 'photo')->values();
    
    $bestImage = $talent->logoUrl();
    if (!$bestImage && $photoFiles->isNotEmpty()) {
        $bestImage = $photoFiles->first()->url;
    }

    // Título principal: Solo el nombre de la banda
    $shareTitle = $talent->band_name;
    
    // Descripción: Estadísticas y el nombre de la plataforma
    $shareDesc = "Visitas: " . number_format($viewsCount) . " • Me gusta: " . number_format($likesCount) . " • Muro del Rock";

    // Si pertenece a una agencia (para los perfiles importados)
    if ($talent instanceof \App\Models\RadioArtistTalentFallback && $talent->agency_id) {
        $agency = $talent->agency;
        if ($agency) {
            $shareTitle = $talent->band_name . ' (' . $agency->name . ')';
        }
    }

    $profileCanonical = route('talents.show', ['bandName' => $talent->band_name]);
    $musicGroupSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'MusicGroup',
        'name' => $talent->band_name,
        'url' => $profileCanonical,
    ];

    if (!empty($bestImage)) {
        $musicGroupSchema['image'] = $bestImage;
    }

    if (!empty($talent->bio)) {
        $musicGroupSchema['description'] = \Illuminate\Support\Str::limit(strip_tags($talent->bio), 250);
    }

    if (!empty($talent->country)) {
        $musicGroupSchema['location'] = [
            '@type' => 'Place',
            'name' => $talent->country,
        ];
    }

    if (!empty($talent->genre)) {
        $musicGroupSchema['genre'] = $talent->genre;
    }
@endphp

<x-layouts.site 
    :title="'Seven Rock Radio - ' . $shareTitle"
    :description="$shareDesc"
    :canonical="$profileCanonical"
    :og-image="route('talents.og-image', \Illuminate\Support\Str::slug($talent->band_name))"
    :twitter-card="'summary_large_image'"
>
    @push('jsonld')
    <script type="application/ld+json">
    {!! json_encode($musicGroupSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>
    @endpush
    
    @php
        $planKey = strtolower((string) ($talent->plan ?? 'free'));
        $planColors = [
            'premium' => '#d4af37',
            'pro'     => '#60a5fa',
            'basic'   => '#34d399',
            'free'    => '#c32720',
        ];
        $accentColor = $planColors[$planKey] ?? '#c32720';
    @endphp

    <div class="relative min-h-screen bg-[#121215] text-[#dcdcdc] font-sans">
        {{-- Capa de grano/textura sutil Lucille --}}
        <div class="fixed inset-0 z-0 bg-cover bg-center bg-no-repeat pointer-events-none opacity-20 mix-blend-luminosity" style="background-image: -webkit-image-set(url('{{ asset('assets/lucille/dark-background.webp') }}') 1x, url('{{ asset('assets/lucille/dark-background.jpg') }}') 1x); background-image: image-set(url('{{ asset('assets/lucille/dark-background.webp') }}') type('image/webp'), url('{{ asset('assets/lucille/dark-background.jpg') }}') type('image/jpeg'));"></div>
        <div class="fixed inset-0 z-0 bg-gradient-to-b from-[#121215]/80 via-[#121215]/90 to-[#121215] pointer-events-none"></div>

        <section class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 pt-28 md:pt-36 pb-20" style="--lucille-accent: {{ $accentColor }};">
            <!-- Profile Header -->
            <div class="relative overflow-hidden rounded-sm bg-[#141417] border border-white/10 p-6 md:p-10 text-center shadow-sm">
                <!-- Background Banner Texture -->
                <div class="absolute inset-0 z-0 opacity-20 mix-blend-luminosity">
                    <img src="{{ \App\Models\ThemeSetting::current()->talents_banner_url ?? asset('assets/lucille/dark-background.jpg') }}" alt="Banner" class="w-full h-full object-cover" width="1280" height="400" loading="eager" fetchpriority="high" decoding="async">
                </div>
                <!-- Overlay Gradient for Readability -->
                <div class="absolute inset-0 z-0 bg-gradient-to-t from-[#141417] via-[#141417]/80 to-transparent pointer-events-none"></div>
                
                <div class="relative z-10 flex flex-col items-center">
                    <div class="relative group">
                        <div class="relative h-28 w-28 md:h-36 md:w-36 overflow-hidden rounded-sm border-2 border-white/20 hover:border-[var(--lucille-accent)] transition-colors duration-200 shadow-md bg-[#101012]">
                            <img src="{{ $bestImage ?? asset('assets/lucille/beatles_t_shirt.jpeg') }}" alt="{{ $talent->band_name }}" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" loading="eager" fetchpriority="high" decoding="async" width="144" height="144">
                        </div>
                    </div>

                    <h1 class="font-display text-3xl sm:text-4xl md:text-5xl uppercase font-bold tracking-[.12em] text-[#dcdcdc] mt-5 drop-shadow-sm">{{ $talent->band_name }}</h1>

                    <div class="mt-3 flex flex-wrap justify-center items-center gap-2 relative z-10">
                        @php
                            $planBadgeThemes = [
                                'premium' => 'border-[#d4af37]/35 bg-[#d4af37]/10 text-[#d4af37]',
                                'pro'     => 'border-[#60a5fa]/35 bg-[#60a5fa]/10 text-[#93c5fd]',
                                'basic'   => 'border-[#34d399]/35 bg-[#34d399]/10 text-[#6ee7b7]',
                                'free'    => 'border-white/15 bg-white/5 text-[#dcdcdc]',
                            ];
                            $headerBadgeStyle = $planBadgeThemes[$planKey] ?? $planBadgeThemes['free'];
                        @endphp

                        <span class="inline-flex items-center gap-1.5 rounded-sm border px-3 py-1 text-xs font-bold uppercase tracking-[.10em] {{ $headerBadgeStyle }}">
                            Plan: {{ ucfirst($talent->plan) }}
                        </span>
                        @if ($talent->is_featured)
                            <span class="inline-flex items-center gap-1.5 rounded-sm border border-[#d4af37]/40 bg-[#d4af37]/15 px-3 py-1 text-xs font-bold uppercase tracking-[.10em] text-[#d4af37]">
                                ★ Destacado
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="flex justify-center gap-4 sm:gap-8 md:gap-10 flex-wrap rounded-sm border border-white/10 bg-[#141417] px-5 py-3.5 shadow-sm max-w-2xl mx-auto -mt-5 relative z-20 items-center">
                <div class="text-center px-3">
                    <span class="block font-display text-2xl md:text-3xl font-bold text-white like-count">{{ $likesCount }}</span>
                    <span class="text-[10px] uppercase tracking-[.15em] text-[#8b8b8b] font-mono">Me gusta</span>
                </div>
                <div class="h-8 w-[1px] bg-white/10 self-center hidden sm:block"></div>
                <div class="text-center px-3">
                    <span class="block font-display text-2xl md:text-3xl font-bold text-white">{{ $media->count() }}</span>
                    <span class="text-[10px] uppercase tracking-[.15em] text-[#8b8b8b] font-mono">Archivos</span>
                </div>
                <div class="h-8 w-[1px] bg-white/10 self-center hidden sm:block"></div>
                <div class="text-center px-3">
                    <span class="block font-display text-2xl md:text-3xl font-bold text-white">{{ $viewsCount }}</span>
                    <span class="text-[10px] uppercase tracking-[.15em] text-[#8b8b8b] font-mono">Visitas</span>
                </div>
                <div class="h-8 w-[1px] bg-white/10 self-center hidden sm:block"></div>
                
                <!-- Botón Compartir -->
                <button type="button" 
                    class="text-center px-3 flex items-center gap-2 cursor-pointer rounded-sm border border-white/10 bg-[#101012] hover:bg-white/5 py-1.5 px-3 transition-colors group"
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
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-[var(--lucille-accent)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                    <span class="text-[10px] uppercase tracking-[.15em] text-[#dcdcdc] group-hover:text-white font-mono font-bold">Compartir</span>
                </button>
            </div>

            <!-- Main Layout Grid -->
            @php
                $audioFiles = $media->where('type', 'mp3')->values();
                $photoFiles = $media->where('type', 'photo')->values();
                $otherMedia = $media->whereNotIn('type', ['mp3', 'photo'])->values();

                // Fallback de demostración cuando el artista aún no ha subido archivos multimedia
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
                            'url' => asset('assets/lucille/muro-del-rock-banner.webp'),
                            'title' => 'Foto Demo 1'
                        ],
                        (object)[
                            'url' => asset('assets/lucille/dark-background.webp'),
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
            <div class="grid gap-6 lg:grid-cols-[1.2fr_.8fr] mt-8 items-start">
                <!-- Left Column: Biography, Media, Store, etc. -->
                <div class="space-y-6">
                    <!-- Biography Panel -->
                    <div class="border border-white/10 bg-[#141417] rounded-sm p-5 md:p-6 shadow-sm">
                        <h3 class="font-display text-lg uppercase tracking-[.12em] text-white border-b border-white/10 pb-2.5">Biografía</h3>
                        <p class="mt-3 text-sm leading-relaxed text-[#b5b5b5] font-sans whitespace-pre-line">{{ $talent->bio ?: 'Este artista aún no ha escrito su biografía.' }}</p>
                    </div>

                    <!-- Social Networks Panel -->
                    @php($socialLinks = $talent->socialLinkMap())
                    @if ($socialLinks !== [])
                        <div class="border border-white/10 bg-[#141417] rounded-sm p-5 md:p-6 shadow-sm">
                            <h3 class="font-display text-lg uppercase tracking-[.12em] text-white border-b border-white/10 pb-2.5">Sigue al artista</h3>
                            <div class="mt-4 flex flex-wrap gap-2.5">
                                @foreach ($socialLinks as $network => $url)
                                    <a href="{{ $url }}" target="_blank" rel="noreferrer" class="inline-flex items-center gap-2 px-3.5 py-1.5 border border-white/15 bg-[#101012] text-[#dcdcdc] hover:border-[var(--lucille-accent)] hover:text-white rounded-sm transition-colors text-xs font-semibold uppercase tracking-wider">
                                        @if ($network === 'instagram')
                                            <svg class="w-3.5 h-3.5 opacity-80" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                                        @elseif ($network === 'youtube')
                                            <svg class="w-3.5 h-3.5 opacity-80" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                        @elseif ($network === 'spotify')
                                            <svg class="w-3.5 h-3.5 opacity-80" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.495 17.29a.625.625 0 0 1-.86.208c-2.355-1.44-5.32-1.765-8.812-.966a.626.626 0 1 1-.295-1.216c3.82-.876 7.108-.501 9.759 1.115a.625.625 0 0 1 .208.859zm1.233-2.766a.78.78 0 0 1-1.074.258c-2.707-1.664-6.848-2.147-10.02-.117a.78.78 0 1 1-.84-1.31c3.633-2.324 8.243-1.786 11.365.132a.78.78 0 0 1 .57 1.037zm.116-2.903c-3.26-1.934-8.636-2.115-11.758-1.17a.974.974 0 1 1-.555-1.867c3.565-1.077 9.52-1.006 13.314 1.246a.976.976 0 0 1-.99 1.792z"/></svg>
                                        @elseif ($network === 'tiktok')
                                            <svg class="w-3.5 h-3.5 opacity-80" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.04.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93v7.02c-.01 1.63-.52 3.26-1.51 4.56-1.11 1.46-2.92 2.43-4.72 2.75-1.8.31-3.69.12-5.32-.64-1.66-.77-2.98-2.22-3.61-3.95-.61-1.68-.61-3.6.09-5.26.7-1.66 2.05-3.04 3.75-3.75 1.68-.7 3.63-.71 5.33-.12V12.7c-1.3-.39-2.71-.34-3.94.18-.89.37-1.65 1.01-2.12 1.83-.48.81-.66 1.8-.46 2.74.2 1 .74 1.9 1.5 2.5 1 .79 2.45 1.09 3.69.75 1.11-.29 2.06-1.11 2.53-2.14.46-1 .61-2.13.56-3.2V.02z"/></svg>
                                        @else
                                            <svg class="w-3.5 h-3.5 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                        @endif
                                        <span>{{ ucfirst($network) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Multimedia Content Panel -->
                    @if ($otherMedia->isNotEmpty())
                        <div class="border border-white/10 bg-[#141417] rounded-sm p-5 md:p-6 shadow-sm">
                            <h3 class="font-display text-lg uppercase tracking-[.12em] text-white border-b border-white/10 pb-2.5">Contenido</h3>
                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                @foreach ($otherMedia as $item)
                                    <article class="overflow-hidden rounded-sm bg-[#101012] border border-white/10 p-3.5 hover:border-white/20 transition-colors group" data-type="{{ $item->type }}">
                                        @if (!empty($item->is_exclusive) && !Auth::guard('web')->check() && !Auth::guard('talent')->check())
                                            <div class="p-4 bg-black/40 rounded-sm text-center flex flex-col justify-between items-center h-full min-h-[140px]">
                                                <div>
                                                    <div class="h-8 w-8 mx-auto rounded-sm bg-[var(--lucille-accent)]/15 border border-[var(--lucille-accent)]/30 text-[var(--lucille-accent)] flex items-center justify-center text-sm">
                                                        🔒
                                                    </div>
                                                    <h4 class="mt-2.5 font-display text-xs font-bold uppercase tracking-wide text-white truncate max-w-full" title="{{ $item->title ?: $item->filename }}">
                                                        {{ $item->title ?: $item->filename }}
                                                    </h4>
                                                    <p class="mt-1 text-[10px] text-[#8b8b8b] leading-relaxed">
                                                        Exclusivo para Afiliados
                                                    </p>
                                                </div>
                                                <a href="{{ route('afiliados.register') }}" class="lucille-button text-[9px] uppercase py-1 px-3 tracking-wider mt-3 rounded-sm w-full text-center">
                                                    Registrarse Gratis
                                                </a>
                                            </div>
                                        @else
                                            @if ($item->type === 'video')
                                                 <div class="relative group aspect-video w-full overflow-hidden rounded-sm bg-black/60 border border-white/10 shadow-sm" x-data="{
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
                                                     
                                                     <div x-show="!playing" class="absolute inset-0 flex items-center justify-center bg-black/40 group-hover:bg-black/20 transition-colors">
                                                         <button type="button" @click="togglePlay()" class="h-12 w-12 rounded-sm bg-[var(--lucille-accent)] text-white flex items-center justify-center shadow-md hover:brightness-110 active:scale-95 transition-all" aria-label="Reproducir video">
                                                             <svg class="h-5 w-5 fill-current ml-0.5" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                                         </button>
                                                     </div>
                                                 </div>
                                                 <p class="mt-2 text-xs font-semibold text-white truncate">{{ $item->title ?: $item->filename }}</p>
                                            @else
                                                <div class="flex items-center gap-3 p-1">
                                                    <span class="text-xl">📄</span>
                                                    <div class="min-w-0 flex-1">
                                                        <a href="{{ $item->url }}" target="_blank" class="text-xs font-semibold text-white hover:underline truncate block">{{ $item->title ?? $item->filename }}</a>
                                                        <span class="text-[10px] text-[#8b8b8b] uppercase tracking-wider font-mono">Documento</span>
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
                        <div class="border border-white/10 bg-[#141417] rounded-sm p-5 md:p-6 shadow-sm">
                            <h3 class="font-display text-lg uppercase tracking-[.12em] text-white border-b border-white/10 pb-2.5">Tienda de {{ $talent->band_name }}</h3>
                            <p class="mt-1 text-xs text-[#8b8b8b] italic">
                                * Las ventas son gestionadas directamente por la banda. Seven Rock Radio actúa como escaparate.
                            </p>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                @foreach ($products as $product)
                                    <div class="store-card border border-white/10 bg-[#101012] rounded-sm p-4 hover:border-white/20 transition-colors flex flex-col justify-between">
                                        <div>
                                            @if ($product->image_url)
                                                <div class="aspect-square w-full overflow-hidden rounded-sm bg-black/20 mb-3 relative">
                                                    <img src="{{ $product->image_url }}" alt="{{ $product->title }}" class="h-full w-full object-cover" loading="lazy" width="200" height="200">
                                                </div>
                                            @endif
                                            <h4 class="font-display text-base text-white font-medium uppercase tracking-[.08em]">{{ $product->title }}</h4>
                                            <p class="mt-1 font-display text-lg font-bold text-[var(--lucille-accent)]">{{ number_format((float) $product->price, 2) }} €</p>
                                            <p class="mt-1.5 text-xs text-[#8b8b8b] leading-relaxed line-clamp-3">{{ \Illuminate\Support\Str::limit((string) $product->description, 100) }}</p>
                                        </div>
                                        @if ($product->external_payment_url)
                                            <a href="{{ $product->external_payment_url }}" target="_blank" rel="nofollow noopener" class="mt-3 inline-flex items-center justify-center min-h-[2.2rem] rounded-sm bg-[#00d165] text-white text-xs font-bold uppercase tracking-[.10em] hover:bg-[#00b959] transition-colors shadow-sm">
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
                        <div class="border border-white/10 bg-[#141417] rounded-sm p-5 md:p-6 shadow-sm">
                            <h3 class="font-display text-lg uppercase tracking-[.12em] text-white border-b border-white/10 pb-2.5">Acepto pagos vía</h3>
                            <div class="mt-3 flex flex-wrap gap-2.5">
                                @foreach ($paymentLinks as $label => $url)
                                    <a href="{{ $url }}" target="_blank" rel="nofollow noopener" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-sm border border-white/15 bg-[#101012] text-xs text-[#dcdcdc] hover:border-white/30 hover:text-white transition-colors">
                                        <span>{{ ucfirst($label) }}</span>
                                        <span class="text-[10px] text-[#8b8b8b]">↗</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Right Column: Player, Gallery, Likes, Comments -->
                <div class="space-y-6">
                    <!-- Music Player Panel -->
                    @if ($audioFiles->isNotEmpty())
                        <div class="border border-white/10 bg-[#141417] rounded-sm p-5 md:p-6 shadow-sm" x-data="audioPlaylist()">
                            <h3 class="font-display text-lg uppercase tracking-[.12em] text-white border-b border-white/10 pb-2.5 mb-4">Reproductor</h3>
                            <!-- Player UI -->
                            <div class="bg-[#101012] rounded-sm p-4 border border-white/10 mb-4">
                                <div class="flex items-center gap-4">
                                    <button type="button" @click="togglePlay()" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-sm bg-[var(--lucille-accent)] text-white hover:brightness-110 transition-all shadow-sm" aria-label="Reproducir/Pausar">
                                        <template x-if="!playing">
                                            <svg class="h-5 w-5 fill-current ml-0.5" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                        </template>
                                        <template x-if="playing">
                                            <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                                        </template>
                                    </button>
                                    <div class="min-w-0 flex-1">
                                        <p class="line-clamp-1 text-sm font-bold text-white uppercase tracking-wider" x-text="currentTrackTitle">Selecciona una pista</p>
                                        <div class="mt-2 flex items-center gap-3">
                                            <input type="range" min="0" max="100" :value="duration ? (currentTime / duration) * 100 : 0" @input="seek($event)" class="lucille-range-slider flex-1" :disabled="!currentTrackUrl">
                                            <span class="text-[10px] text-[#8b8b8b] font-mono shrink-0" x-text="formatTime(currentTime) + ' / ' + (duration ? formatTime(duration) : '0:00')">0:00 / 0:00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Playlist -->
                            <div class="space-y-1.5 max-h-[250px] overflow-y-auto pr-1">
                                @foreach ($audioFiles as $index => $item)
                                    @if (!empty($item->is_exclusive) && !Auth::guard('web')->check() && !Auth::guard('talent')->check())
                                        <div class="w-full text-left flex items-center justify-between p-2.5 rounded-sm border border-white/5 bg-[#101012] text-[#8b8b8b]">
                                            <div class="flex items-center gap-2">
                                                <span>🔒</span>
                                                <span class="text-xs font-semibold text-[#8b8b8b]">Exclusivo para Afiliados</span>
                                            </div>
                                            <a href="{{ route('afiliados.register') }}" class="text-[9px] uppercase tracking-wider text-[var(--lucille-accent)] hover:underline font-mono">Acceder</a>
                                        </div>
                                    @else
                                        <button type="button" @click="playTrack({{ $index }}, '{{ $item->url }}', '{{ addslashes($item->title ?: $item->filename) }}')" 
                                            class="w-full text-left flex items-center justify-between p-2.5 rounded-sm border transition-colors group"
                                            :class="currentIndex === {{ $index }} ? 'border-[var(--lucille-accent)]/50 bg-[var(--lucille-accent)]/10' : 'border-white/5 bg-[#101012] hover:border-white/15'">
                                            <div class="flex items-start gap-2.5 flex-1 min-w-0 pr-2">
                                                <span class="text-xs font-mono shrink-0 mt-0.5" :class="currentIndex === {{ $index }} ? 'text-[var(--lucille-accent)]' : 'text-[#8b8b8b]'">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                                <span class="text-xs line-clamp-1" :class="currentIndex === {{ $index }} ? 'text-white font-semibold' : 'text-[#b5b5b5] group-hover:text-white'">{{ $item->title ?: $item->filename }}</span>
                                            </div>
                                            <template x-if="currentIndex === {{ $index }} && playing">
                                                <div class="flex items-end gap-[2px] h-3.5 shrink-0 ml-2">
                                                    <div class="w-1 h-2 bg-[var(--lucille-accent)] animate-pulse"></div>
                                                    <div class="w-1 h-3.5 bg-[var(--lucille-accent)] animate-pulse" style="animation-delay: 150ms"></div>
                                                    <div class="w-1 h-2.5 bg-[var(--lucille-accent)] animate-pulse" style="animation-delay: 300ms"></div>
                                                </div>
                                            </template>
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                            <!-- Audio element (hidden) -->
                            <audio x-ref="audioPlayer" @durationchange="duration = $event.target.duration" @timeupdate="currentTime = $event.target.currentTime" @ended="nextTrack()"></audio>
                        </div>
                    @endif

                    <!-- Image Gallery Panel -->
                    @if ($photoFiles->isNotEmpty())
                        <div class="border border-white/10 bg-[#141417] rounded-sm p-5 md:p-6 shadow-sm" 
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
                            <h3 class="font-display text-lg uppercase tracking-[.12em] text-white border-b border-white/10 pb-2.5 mb-4">Galería</h3>
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-2 xl:grid-cols-3 gap-2.5">
                                @foreach ($photoFiles as $index => $photo)
                                    <button type="button" @click="activeIndex = {{ $index }}; lightboxOpen = true" class="group relative aspect-square overflow-hidden rounded-sm bg-[#101012] border border-white/10 hover:border-[var(--lucille-accent)]/60 transition-colors focus:outline-none">
                                        <img src="{{ $photo->url }}" alt="{{ $photo->title }}" loading="lazy" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
                                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors flex items-center justify-center">
                                            <svg class="w-5 h-5 text-white opacity-0 group-hover:opacity-100 transition-opacity drop-shadow-sm" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" /></svg>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                            
                            <!-- Lightbox Modal -->
                            <template x-teleport="body">
                                <div x-show="lightboxOpen" style="display: none;"
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0"
                                     x-transition:enter-end="opacity-100"
                                     x-transition:leave="transition ease-in duration-200"
                                     x-transition:leave-start="opacity-100"
                                     x-transition:leave-end="opacity-0"
                                     class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/95 backdrop-blur-md"
                                     @keydown.escape.window="lightboxOpen = false"
                                     @keydown.right.window="if(lightboxOpen) next()"
                                     @keydown.left.window="if(lightboxOpen) prev()">
                                     
                                    <button type="button" @click="lightboxOpen = false" class="absolute top-5 right-5 z-50 text-white/60 hover:text-white transition-colors p-2" aria-label="Cerrar">
                                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>

                                    <div class="relative w-full max-w-[100vw] h-[75vh] flex items-center justify-center overflow-hidden">
                                        <template x-for="(photo, index) in photos" :key="index">
                                            <div class="absolute transition-all duration-300 ease-out flex flex-col items-center justify-center"
                                                 :class="{
                                                     'z-50 scale-100 opacity-100 translate-x-0': index === activeIndex,
                                                     'z-40 scale-75 opacity-50 -translate-x-[60%] md:-translate-x-[50%] blur-[1px] hover:opacity-75 cursor-pointer': index === activeIndex - 1,
                                                     'z-40 scale-75 opacity-50 translate-x-[60%] md:translate-x-[50%] blur-[1px] hover:opacity-75 cursor-pointer': index === activeIndex + 1,
                                                     'z-30 scale-50 opacity-0 -translate-x-[120%] pointer-events-none': index < activeIndex - 1,
                                                     'z-30 scale-50 opacity-0 translate-x-[120%] pointer-events-none': index > activeIndex + 1
                                                 }"
                                                 @click="
                                                     if (index === activeIndex - 1) prev();
                                                     else if (index === activeIndex + 1) next();
                                                 ">
                                                <img :src="photo.url" :alt="photo.title" class="max-h-[75vh] max-w-[85vw] md:max-w-[70vw] object-contain rounded-sm shadow-2xl border border-white/10">
                                                
                                                <div x-show="index === activeIndex && photo.title" x-transition.opacity.duration.200ms class="absolute -bottom-10 text-center w-full">
                                                    <span class="text-[#dcdcdc] font-sans text-xs tracking-wide" x-text="photo.title"></span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                    
                                    <!-- Mobile Navigation Controls -->
                                    <div class="absolute bottom-6 flex gap-4 z-50 md:hidden">
                                        <button @click="prev()" :class="activeIndex === 0 ? 'opacity-30 cursor-not-allowed' : 'opacity-80 hover:opacity-100'" class="p-2.5 bg-white/10 rounded-sm">
                                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                        </button>
                                        <button @click="next()" :class="activeIndex === photos.length - 1 ? 'opacity-30 cursor-not-allowed' : 'opacity-80 hover:opacity-100'" class="p-2.5 bg-white/10 rounded-sm">
                                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    @endif

                    <!-- Like Button & Comment Form Panel -->
                    <div class="border border-white/10 bg-[#141417] rounded-sm p-5 md:p-6 shadow-sm space-y-5">
                        <div>
                            <button type="button" class="btn-like w-full justify-center content-reaction-button flex items-center gap-2 py-2.5 px-4 rounded-sm border border-white/15 bg-[#101012] hover:border-[var(--lucille-accent)] text-[#dcdcdc] transition-colors active:scale-98 {{ $hasLiked ? 'is-active' : '' }}" data-band="{{ $talent->band_name }}">
                                <span class="content-reaction-button__icon text-base text-[var(--lucille-accent)]">♥</span>
                                <span class="font-display uppercase tracking-[.12em] text-xs font-semibold">Me Gusta</span>
                                <span class="like-count content-reaction-count ml-1 text-xs font-mono font-bold">{{ $likesCount }}</span>
                            </button>
                        </div>

                        <form method="POST" action="{{ route('talents.comment', ['bandName' => $talent->band_name]) }}" class="comment-section pt-4 border-t border-white/10">
                            @csrf
                            <div class="hidden" style="display:none !important" aria-hidden="true">
                                <input type="text" name="user_website" tabindex="-1" autocomplete="off">
                            </div>
                            <h4 class="font-display text-base uppercase tracking-[.12em] text-white">Dejar un comentario</h4>
                            <textarea name="content" placeholder="Escribe un comentario para la banda..." maxlength="500" required class="mt-3 w-full rounded-sm border border-white/10 bg-[#101012] p-3 text-xs text-[#dcdcdc] placeholder-[#707070] focus:border-[var(--lucille-accent)] focus:ring-0 outline-none transition-colors" rows="3"></textarea>
                            <button type="submit" class="mt-2.5 w-full lucille-button-solid py-2 text-xs rounded-sm">Publicar comentario</button>
                        </form>
                    </div>

                    <!-- Recent Comments List Panel -->
                    <div class="border border-white/10 bg-[#141417] rounded-sm p-5 md:p-6 shadow-sm">
                        <h4 class="font-display text-lg uppercase tracking-[.12em] text-white border-b border-white/10 pb-2.5">Comentarios recientes</h4>
                        <div class="comments-list mt-4 space-y-3">
                            @forelse ($topComments as $comment)
                                <div class="comment-item bg-[#101012] border border-white/10 p-3 rounded-sm flex items-start gap-3">
                                    <div class="h-8 w-8 shrink-0 rounded-sm bg-white/5 flex items-center justify-center text-sm border border-white/10">
                                        🎸
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <strong class="text-white text-xs font-semibold uppercase tracking-wider">Anónimo</strong>
                                            <small class="text-[9px] text-[#8b8b8b] font-mono">{{ $comment->created_at->diffForHumans() }}</small>
                                        </div>
                                        <p class="mt-1 text-xs text-[#b5b5b5] leading-relaxed whitespace-pre-line">{{ $comment->content }}</p>
                                    </div>
                                </div>
                            @empty
                                <div class="text-xs text-[#8b8b8b] text-center py-4">Todavía no hay comentarios para este artista.</div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Related Bands Panel -->
                    @if ($relatedByStyle->isNotEmpty() || $relatedByName->isNotEmpty() || $recommended->isNotEmpty())
                        <div class="border border-white/10 bg-[#141417] rounded-sm p-5 md:p-6 shadow-sm">
                            <h4 class="font-display text-lg uppercase tracking-[.12em] text-white border-b border-white/10 pb-2.5">Artistas Relacionados</h4>
                            <div class="space-y-4 mt-4">
                                @if ($relatedByStyle->isNotEmpty())
                                    <div>
                                        <span class="text-[9px] uppercase tracking-[.15em] text-[var(--lucille-accent)] font-semibold font-mono">Mismo Estilo</span>
                                        <div class="space-y-2 mt-2">
                                            @foreach ($relatedByStyle as $relBand)
                                                <a href="{{ route('talents.show', ['bandName' => $relBand->band_name]) }}" class="flex items-center gap-3 bg-[#101012] border border-white/10 p-2 rounded-sm hover:border-white/20 transition-colors">
                                                    <div class="h-9 w-9 rounded-sm border border-white/10 overflow-hidden bg-black/20 shrink-0">
                                                        @if ($relBand->logoUrl())
                                                            <img src="{{ $relBand->logoUrl() }}" class="h-full w-full object-cover" alt="{{ $relBand->band_name }}" width="36" height="36" loading="lazy">
                                                        @else
                                                            <div class="h-full w-full flex items-center justify-center text-[8px] text-[#555] uppercase tracking-widest">Logo</div>
                                                        @endif
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <strong class="text-white text-xs block truncate">{{ $relBand->band_name }}</strong>
                                                        <span class="text-[9px] text-[#8b8b8b] uppercase tracking-wider block font-mono">Plan {{ ucfirst($relBand->plan) }}</span>
                                                    </div>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if ($relatedByName->isNotEmpty())
                                    <div class="@if($relatedByStyle->isNotEmpty()) pt-3 border-t border-white/10 @endif">
                                        <span class="text-[9px] uppercase tracking-[.15em] text-[var(--lucille-accent)] font-semibold font-mono">Nombres Similares</span>
                                        <div class="space-y-2 mt-2">
                                            @foreach ($relatedByName as $relBand)
                                                <a href="{{ route('talents.show', ['bandName' => $relBand->band_name]) }}" class="flex items-center gap-3 bg-[#101012] border border-white/10 p-2 rounded-sm hover:border-white/20 transition-colors">
                                                    <div class="h-9 w-9 rounded-sm border border-white/10 overflow-hidden bg-black/20 shrink-0">
                                                        @if ($relBand->logoUrl())
                                                            <img src="{{ $relBand->logoUrl() }}" class="h-full w-full object-cover" alt="{{ $relBand->band_name }}" width="36" height="36" loading="lazy">
                                                        @else
                                                            <div class="h-full w-full flex items-center justify-center text-[8px] text-[#555] uppercase tracking-widest">Logo</div>
                                                        @endif
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <strong class="text-white text-xs block truncate">{{ $relBand->band_name }}</strong>
                                                        <span class="text-[9px] text-[#8b8b8b] uppercase tracking-wider block font-mono">{{ $relBand->interacts }} interacciones</span>
                                                    </div>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if ($recommended->isNotEmpty())
                                    <div class="@if($relatedByStyle->isNotEmpty() || $relatedByName->isNotEmpty()) pt-3 border-t border-white/10 @endif">
                                        <span class="text-[9px] uppercase tracking-[.15em] text-[var(--lucille-accent)] font-semibold font-mono">Recomendados del Muro</span>
                                        <div class="space-y-2 mt-2">
                                            @foreach ($recommended as $relBand)
                                                <a href="{{ route('talents.show', ['bandName' => $relBand->band_name]) }}" class="flex items-center gap-3 bg-[#101012] border border-white/10 p-2 rounded-sm hover:border-white/20 transition-colors">
                                                    <div class="h-9 w-9 rounded-sm border border-white/10 overflow-hidden bg-black/20 shrink-0">
                                                        @if ($relBand->logoUrl())
                                                            <img src="{{ $relBand->logoUrl() }}" class="h-full w-full object-cover" alt="{{ $relBand->band_name }}" width="36" height="36" loading="lazy">
                                                        @else
                                                            <div class="h-full w-full flex items-center justify-center text-[8px] text-[#555] uppercase tracking-widest">Logo</div>
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
    </div>

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
                            @if (!empty($item->is_exclusive) && !Auth::guard('web')->check() && !Auth::guard('talent')->check())
                                @continue
                            @endif
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
