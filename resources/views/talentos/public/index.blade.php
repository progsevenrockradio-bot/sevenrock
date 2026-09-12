<x-layouts.site :title="'Seven Rock Radio - Talentos'" description="Descubre nuevos talentos musicales en Seven Rock Radio. Bandas independientes, artistas emergentes y musica original.">
    {{-- Exclusivo Fondo de Pared de Rock con Glassmorphism para Muro del Rock --}}
    <div class="relative min-h-screen bg-[#0a0a0b]">
        {{-- Imagen de Fondo Fijo / Parallax Vívida Limpia (Sin duplicación de tarjetas o texto) --}}
        <div class="fixed inset-0 z-0 bg-cover bg-center bg-no-repeat pointer-events-none opacity-40 mix-blend-luminosity blur-[2px]" style="background-image: url('{{ asset('assets/lucille/dark-background.jpg') }}');"></div>
        
        {{-- Capa de Cristal Esmerilado (Overlay Translúcido sin backdrop-blur para evitar ghosting) --}}
        <div class="fixed inset-0 z-0 bg-gradient-to-b from-[#0a0a0b]/60 via-[#0a0a0b]/80 to-[#0a0a0b] pointer-events-none"></div>

        <section class="relative z-10 mx-auto max-w-[1180px] px-5 py-16" style="padding-top: 150px;">
        <!-- Section Header Banner (Impeccable Design) -->
        <div class="relative overflow-hidden mb-8 rounded-[20px] border border-white/10 p-8 md:p-12 shadow-[0_20px_50px_rgba(0,0,0,0.6)] group">
            {{-- Background Image --}}
            <div class="absolute inset-0 z-0 bg-cover bg-center transition-transform duration-700 group-hover:scale-105" style="background-image: url('{{ asset('assets/lucille/rock-wall.jpg') }}');"></div>
            
            {{-- Glass Overlay (Vidrio) - Less opacity to make image visible --}}
            <div class="absolute inset-0 z-0 bg-gradient-to-br from-[#161214]/40 via-[#0f0e11]/50 to-[#08080a]/75 backdrop-blur-[3px] pointer-events-none"></div>

            {{-- Ambient radial background glow --}}
            <div class="absolute -top-24 -left-24 w-96 h-96 opacity-40 pointer-events-none bg-[radial-gradient(circle,var(--lucille-accent)_0%,transparent_70%)] blur-2xl z-0 mix-blend-screen"></div>
            <div class="absolute -bottom-24 -right-24 w-96 h-96 opacity-30 pointer-events-none bg-[radial-gradient(circle,#d4af37_0%,transparent_70%)] blur-2xl z-0 mix-blend-screen"></div>
            
            <div class="relative z-10">
                <div class="inline-flex items-center gap-2 rounded-full border border-[var(--lucille-accent)]/30 bg-[var(--lucille-accent)]/10 px-3.5 py-1 text-[10px] font-bold uppercase tracking-[.25em] text-[var(--lucille-accent)] shadow-[0_0_15px_rgba(195,39,32,0.25)]">
                    <span class="h-1.5 w-1.5 rounded-full bg-[var(--lucille-accent)] animate-pulse"></span>
                    Talentos de la Comunidad
                </div>

                <h1 class="mt-4 font-display text-4xl md:text-6xl uppercase tracking-[.14em] text-white drop-shadow-md">
                    Muro del <span class="text-[var(--lucille-accent)] drop-shadow-[0_0_30px_rgba(195,39,32,0.5)]">Rock</span>
                </h1>

                <div class="mt-3 h-1 w-24 rounded-full bg-gradient-to-r from-[var(--lucille-accent)] via-[var(--lucille-accent)]/50 to-transparent"></div>

                <p class="mt-4 max-w-2xl text-sm md:text-base text-gray-300 font-sans leading-relaxed">
                    Descubre las bandas y artistas independientes de nuestra comunidad. Ordenados por actividad — las más activas primero.
                </p>
            </div>
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
        @endphp

        <!-- Filter Form -->
        <form method="GET" action="{{ route('talents.explore') }}" class="relative z-20 grid gap-4 border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-6 md:grid-cols-[1.5fr_1fr_auto] shadow-lg" x-data="{ dropdownOpen: false, selectedPlan: '{{ $selectedPlan }}', selectedLabel: '{{ addslashes($activePlanLabel) }}' }">
            <input type="search" name="search" value="{{ $search }}" placeholder="Buscar talento..." class="lucille-product-field w-full rounded-[8px]">
            
            <div class="relative w-full">
                <input type="hidden" name="plan" :value="selectedPlan">
                
                <button type="button" @click="dropdownOpen = !dropdownOpen" @click.away="dropdownOpen = false" 
                    class="lucille-product-field w-full flex items-center justify-between text-left rounded-[8px]" 
                    style="color: #dcdcdc; background-color: rgba(0, 0, 0, .22); cursor: pointer; height: 50px; border: 1px solid rgba(255,255,255,0.06); padding: 0 16px; font-size: 14px;">
                    <span x-text="selectedLabel"></span>
                    <svg class="w-4 h-4 ml-2 transition-transform duration-200" :class="dropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="14" height="14">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                
                <ul x-show="dropdownOpen" x-cloak x-transition.opacity 
                    class="absolute left-0 z-30 mt-1 w-full border border-white/10 bg-[#141416] rounded-[8px] py-1 shadow-2xl" 
                    style="max-height: 250px; overflow-y: auto; list-style: none; padding: 0; margin: 0;">
                    <li>
                        <button type="button" @click="selectedPlan = ''; selectedLabel = 'Todos los planes'; dropdownOpen = false" 
                            class="w-full text-left px-4 py-3 text-sm transition-colors duration-150"
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
                                class="w-full text-left px-4 py-3 text-sm transition-colors duration-150"
                                :class="selectedPlan === '{{ $key }}' ? 'bg-[var(--lucille-accent)] text-white' : 'text-[#dcdcdc] hover:bg-white/5'">
                                {{ $itemLabel }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>

            <button type="submit" class="lucille-button-solid rounded-[8px] px-8">Filtrar</button>
        </form>

        @php
            $planThemes = [
                'premium' => [
                    'badge'     => 'border-[#d4af37]/40 bg-[#d4af37]/15 text-[#d4af37] shadow-[0_0_12px_rgba(212,175,55,0.2)]',
                    'hover'     => 'hover:border-[#d4af37]/60 hover:shadow-[0_8px_35px_rgba(212,175,55,0.25)]',
                    'hoverText' => 'group-hover:text-[#d4af37]',
                    'text'      => 'text-[#d4af37]',
                    'label'     => 'Plan Premium',
                ],
                'pro' => [
                    'badge'     => 'border-[#3b82f6]/40 bg-[#3b82f6]/15 text-[#60a5fa] shadow-[0_0_12px_rgba(59,130,246,0.2)]',
                    'hover'     => 'hover:border-[#3b82f6]/60 hover:shadow-[0_8px_35px_rgba(59,130,246,0.25)]',
                    'hoverText' => 'group-hover:text-[#60a5fa]',
                    'text'      => 'text-[#60a5fa]',
                    'label'     => 'Plan Pro',
                ],
                'basic' => [
                    'badge'     => 'border-[#10b981]/40 bg-[#10b981]/15 text-[#34d399] shadow-[0_0_12px_rgba(16,185,129,0.2)]',
                    'hover'     => 'hover:border-[#10b981]/60 hover:shadow-[0_8px_35px_rgba(16,185,129,0.25)]',
                    'hoverText' => 'group-hover:text-[#34d399]',
                    'text'      => 'text-[#34d399]',
                    'label'     => 'Plan Basic',
                ],
                'free' => [
                    'badge'     => 'border-[#a855f7]/40 bg-[#a855f7]/15 text-[#c084fc] shadow-[0_0_12px_rgba(168,85,247,0.2)]',
                    'hover'     => 'hover:border-[#a855f7]/60 hover:shadow-[0_8px_35px_rgba(168,85,247,0.25)]',
                    'hoverText' => 'group-hover:text-[#c084fc]',
                    'text'      => 'text-[#c084fc]',
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
                    $latestTrack = $talent->media ? $talent->media->firstWhere('type', 'mp3') : null;
                    
                    $planKey = strtolower((string) ($talent->plan ?? 'free'));
                    $theme = $planThemes[$planKey] ?? $planThemes['free'];
                @endphp

                @if ($isWide)
                    {{-- Tarjeta Ancha (2 Columnas en tablet/desktop) con Hover Neon --}}
                    <div class="col-span-1 md:col-span-2 group relative border border-white/10 {{ $theme['hover'] }} bg-gradient-to-br from-[#141214]/90 via-[#0d0d10]/95 to-[#08080a]/98 backdrop-blur-md rounded-[16px] p-6 transition-all duration-300 flex flex-col md:flex-row gap-6 justify-between overflow-hidden min-h-[260px]">
                        {{-- Portada Grande y Badges --}}
                        <div class="flex flex-row md:flex-col items-center md:items-start gap-4 shrink-0">
                            <div class="h-28 w-28 md:h-36 md:w-36 shrink-0 overflow-hidden rounded-[14px] border border-white/10 bg-black/40 relative shadow-inner">
                                @if ($talent->logoUrl())
                                    <img src="{{ $talent->logoUrl() }}" alt="{{ $talent->band_name }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy" width="144" height="144">
                                @else
                                    <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-[#1c1c1e] to-[#111112]">
                                        <svg class="h-12 w-12 text-[#555] group-hover:text-[var(--lucille-accent)] transition-colors duration-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                                            <circle cx="12" cy="12" r="9" />
                                            <circle cx="12" cy="12" r="3" />
                                            <path d="M12 11c.5 0 1 .5 1 1" />
                                        </svg>
                                    </div>
                                @endif
                            </div>
                            
                            <div class="flex flex-wrap gap-1.5 md:w-36 justify-start">
                                @if ($talent->is_featured)
                                    <span class="border border-[#d4af37]/40 bg-[#d4af37]/15 px-2 py-0.5 text-[9px] font-bold uppercase tracking-[.15em] text-[#d4af37] rounded-sm shadow-[0_0_10px_rgba(212,175,55,0.2)]">★ Destacado</span>
                                @endif
                                <span class="border {{ $theme['badge'] }} px-2 py-0.5 text-[9px] font-bold uppercase tracking-[.18em] rounded-sm">
                                    {{ $theme['label'] }}
                                </span>
                            </div>
                        </div>

                        {{-- Información Amplia y Audio --}}
                        <div class="min-w-0 flex-1 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between gap-3">
                                    <h2 class="font-display text-2xl uppercase tracking-[.12em] text-white truncate {{ $theme['hoverText'] }} transition-colors">
                                        <a href="{{ route('talents.show', ['bandName' => $talent->band_name]) }}" class="hover:underline">
                                            {{ $talent->band_name }}
                                        </a>
                                    </h2>
                                    <span class="hidden sm:inline-block text-[10px] uppercase font-mono tracking-widest text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-0.5 rounded-full">Banda Verificada</span>
                                </div>

                                <p class="mt-2 text-sm text-gray-300 leading-relaxed line-clamp-3 md:line-clamp-4 font-sans">
                                    {{ $talent->bio ?: 'Este artista forma parte de nuestra comunidad oficial de talentos.' }}
                                </p>

                                {{-- Track Audio Preview --}}
                                @if ($latestTrack && $latestTrack->url)
                                    <div class="mt-3 p-3 rounded-[10px] bg-black/50 border border-white/10 flex flex-wrap items-center gap-3">
                                        <div class="flex items-center gap-2 min-w-0 flex-1">
                                            <div class="h-7 w-7 rounded-full bg-[var(--lucille-accent)]/20 text-[var(--lucille-accent)] flex items-center justify-center shrink-0 animate-pulse">
                                                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-[9px] uppercase tracking-widest text-gray-500 font-mono">Pista Destacada</div>
                                                <div class="text-xs font-semibold text-white truncate font-mono">{{ $latestTrack->title ?: $latestTrack->filename }}</div>
                                            </div>
                                        </div>
                                        <audio controls controlsList="nodownload" class="h-8 max-w-[220px] w-full text-xs">
                                            <source src="{{ $latestTrack->url }}" type="{{ $latestTrack->mime_type ?: 'audio/mpeg' }}">
                                            Tu navegador no soporta el reproductor de audio.
                                        </audio>
                                    </div>
                                @endif
                            </div>

                            {{-- Barra Inferior --}}
                            <div class="mt-4 pt-3 border-t border-white/5 flex items-center justify-between text-[10px] uppercase tracking-[.18em] text-gray-400 font-mono">
                                <div class="flex gap-4">
                                    <span>📁 {{ $talent->media_count }} archivos</span>
                                    <span>🔥 {{ $talent->interacts }} interacciones</span>
                                </div>
                                <a href="{{ route('talents.show', ['bandName' => $talent->band_name]) }}" class="lucille-button-solid text-[10px] py-1.5 px-4 tracking-widest uppercase rounded-[6px] shrink-0">
                                    Ver Perfil →
                                </a>
                            </div>
                        </div>
                    </div>
                @else
                    {{-- Tarjeta Estándar (1 Columna) con Hover Neon --}}
                    <a href="{{ route('talents.show', ['bandName' => $talent->band_name]) }}" class="col-span-1 group relative border border-white/10 {{ $theme['hover'] }} bg-[#101014]/90 backdrop-blur-md rounded-[16px] p-6 transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between min-h-[260px]">
                        <div class="flex items-start gap-4">
                            <div class="h-20 w-20 shrink-0 overflow-hidden rounded-[12px] border border-white/10 bg-black/30 relative">
                                @if ($talent->logoUrl())
                                    <img src="{{ $talent->logoUrl() }}" alt="{{ $talent->band_name }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy" width="80" height="80">
                                @else
                                    <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-[#1c1c1e] to-[#111112]">
                                        <svg class="h-8 w-8 text-[#555] group-hover:text-[var(--lucille-accent)] transition-colors duration-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                                            <circle cx="12" cy="12" r="9" />
                                            <circle cx="12" cy="12" r="3" />
                                            <path d="M12 11c.5 0 1 .5 1 1" />
                                        </svg>
                                    </div>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="font-display text-xl uppercase tracking-[.12em] text-white truncate {{ $theme['hoverText'] }} transition-colors">{{ $talent->band_name }}</h2>
                                    @if ($talent->is_featured)
                                        <span class="border border-[#d4af37]/40 bg-[#d4af37]/15 px-2 py-0.5 text-[9px] font-bold uppercase tracking-[.15em] text-[#d4af37] rounded-sm shadow-[0_0_8px_rgba(212,175,55,0.2)]">★</span>
                                    @endif
                                </div>
                                <div class="mt-2">
                                    <span class="border {{ $theme['badge'] }} px-2 py-0.5 text-[9px] font-bold uppercase tracking-[.18em] rounded-sm inline-block">
                                        {{ $theme['label'] }}
                                    </span>
                                </div>
                                <p class="mt-3 line-clamp-3 text-sm text-gray-400 leading-relaxed font-sans">{{ $talent->bio ?: 'Este artista aún no ha escrito su biografía.' }}</p>
                            </div>
                        </div>
                        <div class="mt-5 pt-4 border-t border-white/5 flex items-center justify-between text-[10px] uppercase tracking-[.18em] text-gray-400 font-mono">
                            <span>{{ $talent->media_count }} archivos</span>
                            <span>{{ $talent->interacts }} interacciones</span>
                        </div>
                    </a>
                @endif
            @empty
                <div class="col-span-full border border-white/10 bg-[#101014]/90 backdrop-blur-md rounded-[16px] p-8 text-sm text-gray-500 text-center shadow-lg">No hay talentos publicados todavía con estos criterios de búsqueda.</div>
            @endforelse

            @if(count($talents) < 6)
                <div class="col-span-1 border border-dashed border-white/10 bg-[#101014]/80 backdrop-blur-md rounded-[16px] p-6 transition-all duration-300 hover:border-white/20 hover:bg-white/[0.03] shadow-lg flex flex-col justify-between min-h-[260px] text-center items-center group">
                    <div class="flex-1 flex flex-col items-center justify-center">
                        <div class="h-12 w-12 rounded-full border border-white/10 flex items-center justify-center bg-white/[0.02] mb-3 group-hover:border-[var(--lucille-accent)]/30 group-hover:bg-[var(--lucille-accent)]/5 transition-all duration-300">
                            <svg class="h-6 w-6 text-gray-400 group-hover:text-[var(--lucille-accent)] transition-colors duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <h3 class="font-display text-lg uppercase tracking-[.12em] text-white">¿Tienes una Banda?</h3>
                        <p class="mt-2 text-xs text-[#8b8b8b] max-w-[240px] leading-relaxed">Únete al Muro del Rock. Registra tu perfil, comparte tu música y conecta con la audiencia.</p>
                    </div>
                    <a href="{{ route('talents.register') }}" class="lucille-button-solid mt-4 w-full">Registrar Banda</a>
                </div>
            @endif
        </div>

        <div class="mt-8">
            {{ $talents->links() }}
        </div>
        </section>
    </div>
</x-layouts.site>
