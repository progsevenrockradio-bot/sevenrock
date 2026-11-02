<x-layouts.site :title="'Seven Rock Radio - Muro del Rock'" description="Descubre bandas y artistas independientes en el Muro del Rock de Seven Rock Radio. Bandas emergentes y música original.">
    {{-- Fondo carbón unificado Lucille para el Muro del Rock --}}
    <div class="relative min-h-screen bg-[#121215] text-[#dcdcdc] font-sans">
        {{-- Capa de grano/textura sutil Lucille --}}
        <div class="fixed inset-0 z-0 bg-cover bg-center bg-no-repeat pointer-events-none opacity-20 mix-blend-luminosity" style="background-image: -webkit-image-set(url('{{ asset('assets/lucille/dark-background.webp') }}') 1x, url('{{ asset('assets/lucille/dark-background.jpg') }}') 1x); background-image: image-set(url('{{ asset('assets/lucille/dark-background.webp') }}') type('image/webp'), url('{{ asset('assets/lucille/dark-background.jpg') }}') type('image/jpeg'));"></div>
        <div class="fixed inset-0 z-0 bg-gradient-to-b from-[#121215]/80 via-[#121215]/90 to-[#121215] pointer-events-none"></div>

        <section class="relative z-10 mx-auto max-w-[1180px] px-4 sm:px-6 pt-28 md:pt-36 pb-20">
            <!-- Section Header Banner with H1 -->
            <div class="mb-8 w-full flex flex-col items-center justify-center">
                <h1 class="font-display text-3xl sm:text-4xl md:text-5xl uppercase font-bold tracking-[.12em] text-[#dcdcdc] text-center mb-2">Muro del Rock</h1>
                <p class="text-sm md:text-base text-[#8b8b8b] text-center max-w-2xl mb-6">Directorio y catálogo oficial de bandas y artistas independientes de Seven Rock Radio. ¿Buscas dejar saludos o interactuar en directo con los oyentes? Visita el <a href="{{ route('comunidad.muro') }}" class="text-[var(--lucille-accent-readable,#ff5b52)] hover:underline font-semibold">Muro de la Comunidad</a>.</p>
                <picture class="w-full">
                    <source srcset="{{ asset('assets/lucille/muro-del-rock-banner.webp') }}" type="image/webp">
                    <img src="{{ \App\Models\ThemeSetting::current()->talents_banner_url }}"
                         alt="Muro del Rock - Seven Rock Radio"
                         width="1024" height="361"
                         style="aspect-ratio: 1024 / 361;"
                         class="w-full h-auto max-w-full rounded-sm border border-white/10 shadow-sm"
                         loading="eager" fetchpriority="high" decoding="async">
                </picture>
            </div>

            @php
                $defaultPlansList = [
                    'free'    => ['label' => 'Plan Free'],
                    'basic'   => ['label' => 'Plan Basic'],
                    'pro'     => ['label' => 'Plan Pro'],
                    'premium' => ['label' => 'Plan Premium'],
                ];
                $availablePlans = is_array($plans ?? null) && !empty($plans) ? $plans : $defaultPlansList;
                $selectedKey = (string) ($selectedPlan ?? '');
                $activePlanLabel = 'Todos los planes';
                if ($selectedKey !== '' && array_key_exists($selectedKey, $availablePlans)) {
                    $activePlanLabel = (string) ($availablePlans[$selectedKey]['label'] ?? ('Plan ' . ucfirst($selectedKey)));
                }
                if ($activePlanLabel !== 'Todos los planes' && !str_starts_with(strtolower($activePlanLabel), 'plan')) {
                    $activePlanLabel = 'Plan ' . $activePlanLabel;
                }
                $hasActiveFilters = filled($search) || filled($selectedPlan);
            @endphp

            <!-- Filter Form -->
            <form method="GET" action="{{ route('talents.explore') }}" class="relative z-20 grid gap-3 border border-white/10 bg-[#141417] rounded-sm p-4 md:p-5 shadow-sm md:grid-cols-[1.5fr_1fr_auto_auto] items-center" x-data="{ dropdownOpen: false, selectedPlan: '{{ $selectedPlan }}', selectedLabel: '{{ addslashes($activePlanLabel) }}' }">
                <div>
                    <label for="muro-search-input" class="sr-only">Buscar banda o artista en el Muro del Rock</label>
                    <input id="muro-search-input" type="search" name="search" value="{{ $search }}" placeholder="Buscar banda o artista..." aria-label="Buscar banda o artista en el Muro del Rock" class="w-full rounded-sm border border-white/10 bg-[#101012] text-[#dcdcdc] placeholder-[#707070] px-3.5 py-2.5 text-sm focus:border-[var(--lucille-accent)] focus:ring-1 focus:ring-[var(--lucille-accent)] focus:outline-none">
                </div>
                
                <div class="relative w-full">
                    <input type="hidden" name="plan" :value="selectedPlan">
                    
                    <button type="button" @click="dropdownOpen = !dropdownOpen" @click.away="dropdownOpen = false" 
                        id="muro-plan-filter-button"
                        aria-label="Filtrar por tipo de plan: {{ $activePlanLabel }}"
                        aria-haspopup="listbox"
                        :aria-expanded="dropdownOpen.toString()"
                        class="w-full flex items-center justify-between text-left rounded-sm border border-white/10 bg-[#101012] text-[#dcdcdc] px-3.5 py-2.5 text-sm cursor-pointer focus:border-[var(--lucille-accent)] focus:ring-1 focus:ring-[var(--lucille-accent)] focus:outline-none">
                        <span x-text="selectedLabel"></span>
                        <svg class="w-4 h-4 ml-2 text-[#8b8b8b] transition-transform duration-200" :class="dropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    
                    <ul x-show="dropdownOpen" x-cloak x-transition.opacity 
                        class="absolute left-0 z-30 mt-1 w-full border border-white/10 bg-[#141417] rounded-sm py-1 shadow-2xl" 
                        style="max-height: 250px; overflow-y: auto;">
                        <li>
                            <button type="button" @click="selectedPlan = ''; selectedLabel = 'Todos los planes'; dropdownOpen = false" 
                                class="w-full text-left px-4 py-2.5 text-sm transition-colors duration-150"
                                :class="selectedPlan === '' ? 'bg-[var(--lucille-accent)] text-white' : 'text-[#dcdcdc] hover:bg-white/5'">
                                Todos los planes
                            </button>
                        </li>
                        @foreach ($availablePlans as $key => $planItem)
                            @php
                                $itemLabel = $planItem['label'] ?? ('Plan ' . ucfirst($key));
                                if (!str_starts_with(strtolower($itemLabel), 'plan')) {
                                    $itemLabel = 'Plan ' . $itemLabel;
                                }
                            @endphp
                            <li>
                                <button type="button" @click="selectedPlan = '{{ $key }}'; selectedLabel = '{{ addslashes($itemLabel) }}'; dropdownOpen = false" 
                                    class="w-full text-left px-4 py-2.5 text-sm transition-colors duration-150"
                                    :class="selectedPlan === '{{ $key }}' ? 'bg-[var(--lucille-accent)] text-white' : 'text-[#dcdcdc] hover:bg-white/5'">
                                    {{ $itemLabel }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <button type="submit" class="lucille-button-solid rounded-sm px-6 py-2.5 text-xs uppercase tracking-wider font-display font-semibold">Filtrar</button>

                @if ($hasActiveFilters)
                    <a href="{{ route('talents.explore') }}" class="inline-flex items-center justify-center px-4 py-2.5 text-xs text-[#8b8b8b] hover:text-white border border-white/10 rounded-sm hover:bg-white/5 transition-colors text-center uppercase tracking-wider font-mono">Restablecer</a>
                @endif
            </form>

            @php
                $planThemes = [
                    'premium' => [
                        'badge'     => 'border-[#d4af37]/35 bg-[#d4af37]/10 text-[#d4af37]',
                        'hover'     => 'hover:border-[#d4af37]/40',
                        'hoverText' => 'group-hover:text-[#d4af37]',
                        'text'      => 'text-[#d4af37]',
                        'label'     => 'Plan Premium',
                    ],
                    'pro' => [
                        'badge'     => 'border-[#60a5fa]/35 bg-[#60a5fa]/10 text-[#93c5fd]',
                        'hover'     => 'hover:border-[#60a5fa]/40',
                        'hoverText' => 'group-hover:text-[#93c5fd]',
                        'text'      => 'text-[#93c5fd]',
                        'label'     => 'Plan Pro',
                    ],
                    'basic' => [
                        'badge'     => 'border-[#34d399]/35 bg-[#34d399]/10 text-[#6ee7b7]',
                        'hover'     => 'hover:border-[#34d399]/40',
                        'hoverText' => 'group-hover:text-[#6ee7b7]',
                        'text'      => 'text-[#6ee7b7]',
                        'label'     => 'Plan Basic',
                    ],
                    'free' => [
                        'badge'     => 'border-white/15 bg-white/5 text-[#dcdcdc]',
                        'hover'     => 'hover:border-white/20',
                        'hoverText' => 'group-hover:text-white',
                        'text'      => 'text-[#dcdcdc]',
                        'label'     => 'Plan Free',
                    ],
                ];
            @endphp

            <!-- Talents Cards Bento Grid -->
            <div class="mt-8 grid gap-6 grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
                @forelse ($talents as $talent)
                    @php
                        $patternIndex = $loop->index % 7;
                        $isWide = in_array($patternIndex, [0, 3]);
                        
                        $tracks = $talent->media
                            ? $talent->media->where('type', 'mp3')->filter(fn ($m) => ! empty($m->url))->take(4)->values()
                            : collect();
                        $totalTracks = $talent->media ? $talent->media->where('type', 'mp3')->count() : 0;
                        $latestTrack = $tracks->first();
                        
                        $planKey = strtolower((string) ($talent->plan ?? 'free'));
                        $theme = $planThemes[$planKey] ?? $planThemes['free'];
                    @endphp

                    @if ($isWide)
                        {{-- Tarjeta Ancha (2 Columnas en tablet/desktop) --}}
                        <div class="col-span-1 md:col-span-2 group relative border border-white/10 {{ $theme['hover'] }} bg-[#141417] rounded-sm p-5 md:p-6 transition-all duration-200 flex flex-col md:flex-row gap-5 justify-between overflow-hidden shadow-sm">
                            {{-- Portada Grande y Badges --}}
                            <div class="flex flex-row md:flex-col items-center md:items-start gap-4 shrink-0">
                                <div class="h-28 w-28 md:h-32 md:w-32 shrink-0 overflow-hidden rounded-sm border border-white/10 bg-[#101012] relative">
                                    @if ($talent->logoUrl())
                                        <img src="{{ $talent->logoUrl() }}" alt="{{ $talent->band_name }}" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy" width="128" height="128">
                                    @else
                                        <div class="flex h-full w-full items-center justify-center bg-[#101012] text-[#555] group-hover:text-[var(--lucille-accent)] transition-colors duration-300">
                                            <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                                                <circle cx="12" cy="12" r="9" />
                                                <circle cx="12" cy="12" r="3" />
                                                <path d="M12 11c.5 0 1 .5 1 1" />
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                
                                <div class="flex flex-wrap gap-1.5 md:w-32 justify-start">
                                    @if ($talent->is_featured)
                                        <span class="border border-[#d4af37]/40 bg-[#d4af37]/15 px-2 py-0.5 inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-[.10em] text-[#d4af37] rounded-sm">
                                            <svg class="h-2.5 w-2.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5l2.95 6.36 6.95.86-5.1 4.78 1.32 6.87L12 18.1l-6.12 3.27 1.32-6.87-5.1-4.78 6.95-.86L12 2.5Z"/></svg> Destacado
                                        </span>
                                    @endif
                                    <span class="border {{ $theme['badge'] }} px-2 py-0.5 text-[10px] font-bold uppercase tracking-[.10em] rounded-sm">
                                        {{ $theme['label'] }}
                                    </span>
                                </div>
                            </div>

                            {{-- Información y Audio --}}
                            <div class="min-w-0 flex-1 flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between gap-3">
                                        <h3 class="font-display text-2xl uppercase tracking-[.10em] text-white truncate {{ $theme['hoverText'] }} transition-colors">
                                            <a href="{{ route('talents.show', ['bandName' => $talent->band_name]) }}" class="hover:underline">
                                                {{ $talent->band_name }}
                                            </a>
                                        </h3>
                                        <span class="hidden sm:inline-flex items-center gap-1 text-[10px] uppercase font-mono tracking-[.10em] text-[#dcdcdc] bg-white/[0.04] border border-white/10 px-2 py-0.5 rounded-sm">
                                            <svg class="h-2.5 w-2.5 text-[var(--lucille-accent)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg> Banda Verificada
                                        </span>
                                    </div>

                                    <p class="mt-2 text-sm text-[#b5b5b5] leading-relaxed line-clamp-3 font-sans">
                                        {{ $talent->bio ?: 'Este artista forma parte de nuestra comunidad oficial de talentos.' }}
                                    </p>

                                    {{-- Track Audio Preview --}}
                                    @if ($tracks->count() > 1)
                                        <div class="mt-3 p-3 rounded-sm bg-[#101012] border border-white/10" data-playlist>
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="text-[10px] uppercase tracking-[.10em] text-[#8b8b8b] font-mono">
                                                    Pistas · {{ $tracks->count() }}
                                                </span>
                                                @if ($totalTracks > $tracks->count())
                                                    <a href="{{ route('talents.show', ['bandName' => $talent->band_name]) }}"
                                                       class="text-[10px] uppercase tracking-[.10em] text-[#8b8b8b] hover:text-white font-mono">
                                                        +{{ $totalTracks - $tracks->count() }} más
                                                    </a>
                                                @endif
                                            </div>

                                            <ul class="mt-2 space-y-0.5">
                                                @foreach ($tracks as $i => $track)
                                                    <li>
                                                        <button type="button" data-track data-src="{{ $track->url }}"
                                                                class="w-full flex items-center gap-2 text-left px-2 py-1 rounded-sm transition-colors hover:bg-white/5 {{ $i === 0 ? 'bg-white/5 text-white' : '' }}">
                                                            <span class="h-5 w-5 shrink-0 rounded-sm border border-white/15 text-white/70 flex items-center justify-center text-[10px] font-mono">
                                                                {{ $i + 1 }}
                                                            </span>
                                                            <span class="min-w-0 flex-1 truncate text-xs font-mono {{ $i === 0 ? '' : 'text-[#b5b5b5]' }}">
                                                                {{ $track->title ?: $track->filename }}
                                                            </span>
                                                        </button>
                                                    </li>
                                                @endforeach
                                            </ul>

                                            <audio src="{{ $latestTrack->url }}" preload="none" controls controlsList="nodownload" class="mt-2 h-8 w-full text-xs"></audio>
                                        </div>
                                    @elseif ($latestTrack && $latestTrack->url)
                                        <div class="mt-3 p-3 rounded-sm bg-[#101012] border border-white/10 flex flex-col sm:flex-row sm:items-center gap-3">
                                            <div class="flex items-center gap-2 min-w-0 w-full sm:flex-1">
                                                <div class="h-6 w-6 rounded-sm bg-[var(--lucille-accent)]/20 text-[var(--lucille-accent)] flex items-center justify-center shrink-0">
                                                    <svg class="w-3 h-3 fill-current" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="text-[10px] uppercase tracking-[.10em] text-[#8b8b8b] font-mono">Pista Destacada</div>
                                                    <div class="text-xs font-semibold text-white truncate font-mono">{{ $latestTrack->title ?: $latestTrack->filename }}</div>
                                                </div>
                                            </div>
                                            <audio preload="none" controls controlsList="nodownload" class="h-8 w-full sm:w-auto sm:max-w-[200px] shrink-0 text-xs">
                                                <source src="{{ $latestTrack->url }}" type="{{ $latestTrack->mime_type ?: 'audio/mpeg' }}">
                                                Tu navegador no soporta el reproductor de audio.
                                            </audio>
                                        </div>
                                    @endif
                                </div>

                                {{-- Barra Inferior --}}
                                <div class="mt-4 pt-3 border-t border-white/10 flex flex-wrap items-center justify-between gap-3 text-[11px] uppercase tracking-[.10em] text-[#8b8b8b] font-mono">
                                    <div class="flex items-center gap-4">
                                        <span class="inline-flex items-center gap-1.5"><svg class="h-3.5 w-3.5 text-[#8b8b8b]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z"/></svg> {{ $talent->media_count }} archivos</span>
                                        <span class="inline-flex items-center gap-1.5"><svg class="h-3.5 w-3.5 text-[#8b8b8b]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z"/></svg> {{ $talent->interacts }} interacciones</span>
                                    </div>
                                    <a href="{{ route('talents.show', ['bandName' => $talent->band_name]) }}" class="lucille-button-solid inline-flex items-center gap-1.5 text-[11px] py-1.5 px-3.5 tracking-[.10em] uppercase rounded-sm shrink-0">
                                        Ver Perfil <svg class="h-3.5 w-3.5 transition-transform duration-300 group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @else
                        {{-- Tarjeta Estándar (1 Columna) --}}
                        <a href="{{ route('talents.show', ['bandName' => $talent->band_name]) }}" class="col-span-1 group relative border border-white/10 {{ $theme['hover'] }} bg-[#141417] rounded-sm p-5 transition-all duration-200 flex flex-col justify-between min-h-[240px] shadow-sm">
                            <div class="flex items-start gap-4">
                                <div class="h-20 w-20 shrink-0 overflow-hidden rounded-sm border border-white/10 bg-[#101012] relative">
                                    @if ($talent->logoUrl())
                                        <img src="{{ $talent->logoUrl() }}" alt="{{ $talent->band_name }}" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy" width="80" height="80">
                                    @else
                                        <div class="flex h-full w-full items-center justify-center bg-[#101012] text-[#555] group-hover:text-[var(--lucille-accent)] transition-colors duration-300">
                                            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                                                <circle cx="12" cy="12" r="9" />
                                                <circle cx="12" cy="12" r="3" />
                                                <path d="M12 11c.5 0 1 .5 1 1" />
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <h3 class="font-display text-xl uppercase tracking-[.10em] text-white truncate {{ $theme['hoverText'] }} transition-colors">{{ $talent->band_name }}</h3>
                                        @if ($talent->is_featured)
                                            <span class="border border-[#d4af37]/40 bg-[#d4af37]/15 px-1.5 py-0.5 inline-flex text-[10px] font-bold uppercase tracking-[.10em] text-[#d4af37] rounded-sm">★</span>
                                        @endif
                                    </div>
                                    <div class="mt-2">
                                        <span class="border {{ $theme['badge'] }} px-2 py-0.5 text-[10px] font-bold uppercase tracking-[.10em] rounded-sm inline-block">
                                            {{ $theme['label'] }}
                                        </span>
                                    </div>
                                    <p class="mt-2.5 line-clamp-3 text-xs text-[#b5b5b5] leading-relaxed font-sans">{{ $talent->bio ?: 'Este artista aún no ha escrito su biografía.' }}</p>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 border-t border-white/10 flex items-center justify-between text-[11px] uppercase tracking-[.10em] text-[#8b8b8b] font-mono">
                                <span>{{ $talent->media_count }} archivos</span>
                                <span>{{ $talent->interacts }} interacciones</span>
                            </div>
                        </a>
                    @endif
                @empty
                    <div class="col-span-full border border-white/10 bg-[#141417] rounded-sm p-8 md:p-12 text-center shadow-sm">
                        @if ($hasActiveFilters)
                            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-sm bg-white/5 text-[#8b8b8b]">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <h3 class="font-display text-xl uppercase tracking-[.10em] text-white">Búsqueda sin resultados</h3>
                            <p class="mt-2 text-sm text-[#8b8b8b] max-w-md mx-auto leading-relaxed">No se encontraron bandas o artistas con los criterios o filtros seleccionados.</p>
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    if (window.SevenRockAnalytics) {
                                        window.SevenRockAnalytics.track('search_no_results', {
                                            section: 'muro_del_rock',
                                            query_length: {{ mb_strlen($search) }}
                                        });
                                    }
                                });
                            </script>
                            <div class="mt-6 flex flex-wrap justify-center gap-3">
                                <a href="{{ route('talents.explore') }}" class="lucille-button-solid text-xs py-2 px-5 rounded-sm">Limpiar filtros</a>
                                <a href="{{ route('talents.register') }}" class="border border-white/15 bg-white/5 hover:bg-white/10 text-white text-xs py-2 px-5 rounded-sm font-display uppercase tracking-wider transition-colors">Registrar banda</a>
                            </div>
                        @else
                            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-sm bg-white/5 text-[#8b8b8b]">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 100-6 3 3 0 000 6z"/></svg>
                            </div>
                            <h3 class="font-display text-xl uppercase tracking-[.10em] text-white">Catálogo del Muro del Rock</h3>
                            <p class="mt-2 text-sm text-[#8b8b8b] max-w-md mx-auto leading-relaxed">El catálogo de bandas no cuenta con perfiles públicos en este momento. ¡Sé el primero en dar a conocer tu música a nuestra comunidad!</p>
                            <div class="mt-6 flex justify-center">
                                <a href="{{ route('talents.register') }}" class="lucille-button-solid text-xs py-2 px-5 rounded-sm">Registrar banda</a>
                            </div>
                        @endif
                    </div>
                @endforelse

                @if(count($talents) > 0 && count($talents) < 6)
                    <div class="col-span-1 border border-dashed border-white/15 bg-[#141417] rounded-sm p-5 transition-all duration-200 hover:border-white/25 shadow-sm flex flex-col justify-between min-h-[240px] text-center items-center group">
                        <div class="flex-1 flex flex-col items-center justify-center">
                            <div class="h-10 w-10 rounded-sm border border-white/10 flex items-center justify-center bg-white/[0.02] mb-3 group-hover:border-[var(--lucille-accent)]/40 transition-colors">
                                <svg class="h-5 w-5 text-[#8b8b8b] group-hover:text-[var(--lucille-accent)] transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                            <h3 class="font-display text-lg uppercase tracking-[.10em] text-white">¿Tienes una Banda?</h3>
                            <p class="mt-2 text-xs text-[#8b8b8b] max-w-[240px] leading-relaxed">Únete al Muro del Rock. Registra tu perfil, comparte tu música y conecta con la audiencia.</p>
                        </div>
                        <a href="{{ route('talents.register') }}" class="lucille-button-solid mt-4 w-full py-2 text-xs rounded-sm">Registrar Banda</a>
                    </div>
                @endif
            </div>

            <div class="mt-8">
                {{ $talents->links() }}
            </div>
        </section>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-track]');
        if (!btn) return;
        const box = btn.closest('[data-playlist]');
        if (!box) return;
        const audio = box.querySelector('audio');
        if (!audio) return;
        
        box.querySelectorAll('[data-track]').forEach(el => el.classList.remove('bg-white/5', 'text-white'));
        btn.classList.add('bg-white/5', 'text-white');
        
        audio.src = btn.dataset.src;
        audio.play().catch(() => {});
    });
    </script>
    @endpush
</x-layouts.site>
