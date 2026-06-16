<x-layouts.admin :title="($themeAppearance['admin_texts']['dashboard_title'] ?? 'Dashboard').' - '.$themeSettings->site_name">
    @php $admin = $themeAppearance['admin_texts']; @endphp

    <!-- Dashboard Greeting Banner -->
    <div class="mb-8 border border-[#2b2b2b] bg-[linear-gradient(135deg,rgba(16,16,18,0.96),rgba(20,10,30,0.4))] p-8 rounded-lg relative overflow-hidden">
        <!-- Accent Glow background decoration -->
        <div class="absolute -right-10 -top-10 w-48 h-48 bg-[var(--lucille-accent)] opacity-10 rounded-full blur-3xl" aria-hidden="true"></div>
        
        <p class="text-[10px] uppercase tracking-[.28em] text-[var(--lucille-accent)] font-bold">Panel de control</p>
        <h1 class="mt-2 font-display text-4xl uppercase tracking-[.12em] text-white">¡Hola, {{ auth()->user()->name }}!</h1>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-[#8b8b8b]">
            Bienvenido al panel administrativo de Seven Rock Radio. Desde aquí puedes auditar el estado operativo, publicar artículos en el blog, gestionar pistas de audio, códigos de locutor y monitorear campañas de outreach.
        </p>
    </div>

    <!-- Main Grid: Stats & Theme Sidebar -->
    <div class="grid gap-6 lg:grid-cols-[1.3fr_.7fr]">
        
        <!-- Left Side: Metrics Grid -->
        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6 md:p-8 rounded-lg flex flex-col justify-between">
            <div>
                <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-3 mb-6">Estadísticas Operativas</h2>
                
                <div class="grid gap-4 sm:grid-cols-2">
                    
                    <!-- Users -->
                    <div class="border border-[#2b2b2b] bg-[#151515] p-5 rounded hover:border-[var(--lucille-accent)] transition-all duration-300 group flex items-start justify-between">
                        <div>
                            <div class="font-display text-[10px] uppercase tracking-[.2em] text-[#7b7b7b]">{{ $admin['users_label'] }}</div>
                            <div class="mt-2 font-display text-3xl text-white group-hover:text-[var(--lucille-accent)] transition-colors">{{ $stats['users'] }}</div>
                        </div>
                        <span class="p-2 bg-[rgba(255,255,255,0.02)] border border-[#2b2b2b] rounded text-blue-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        </span>
                    </div>

                    <!-- Admins -->
                    <div class="border border-[#2b2b2b] bg-[#151515] p-5 rounded hover:border-[var(--lucille-accent)] transition-all duration-300 group flex items-start justify-between">
                        <div>
                            <div class="font-display text-[10px] uppercase tracking-[.2em] text-[#7b7b7b]">{{ $admin['admins_label'] }}</div>
                            <div class="mt-2 font-display text-3xl text-white group-hover:text-[var(--lucille-accent)] transition-colors">{{ $stats['admin_users'] }}</div>
                        </div>
                        <span class="p-2 bg-[rgba(255,255,255,0.02)] border border-[#2b2b2b] rounded text-red-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </span>
                    </div>

                    <!-- Radio Artists -->
                    <div class="border border-[#2b2b2b] bg-[#151515] p-5 rounded hover:border-[var(--lucille-accent)] transition-all duration-300 group flex items-start justify-between">
                        <div>
                            <div class="font-display text-[10px] uppercase tracking-[.2em] text-[#7b7b7b]">Artistas / Bandas</div>
                            <div class="mt-2 font-display text-3xl text-white group-hover:text-[var(--lucille-accent)] transition-colors">{{ $stats['radio_artists'] }}</div>
                        </div>
                        <span class="p-2 bg-[rgba(255,255,255,0.02)] border border-[#2b2b2b] rounded text-indigo-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </span>
                    </div>

                    <!-- Songs -->
                    <div class="border border-[#2b2b2b] bg-[#151515] p-5 rounded hover:border-[var(--lucille-accent)] transition-all duration-300 group flex items-start justify-between">
                        <div>
                            <div class="font-display text-[10px] uppercase tracking-[.2em] text-[#7b7b7b]">Canciones</div>
                            <div class="mt-2 font-display text-3xl text-white group-hover:text-[var(--lucille-accent)] transition-colors">{{ $stats['songs'] }}</div>
                        </div>
                        <span class="p-2 bg-[rgba(255,255,255,0.02)] border border-[#2b2b2b] rounded text-green-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                        </span>
                    </div>

                    <!-- Master Programs -->
                    <div class="border border-[#2b2b2b] bg-[#151515] p-5 rounded hover:border-[var(--lucille-accent)] transition-all duration-300 group flex items-start justify-between">
                        <div>
                            <div class="font-display text-[10px] uppercase tracking-[.2em] text-[#7b7b7b]">Programas Master</div>
                            <div class="mt-2 font-display text-3xl text-white group-hover:text-[var(--lucille-accent)] transition-colors">{{ $stats['master_programs'] }}</div>
                        </div>
                        <span class="p-2 bg-[rgba(255,255,255,0.02)] border border-[#2b2b2b] rounded text-purple-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                        </span>
                    </div>

                    <!-- Posts -->
                    <div class="border border-[#2b2b2b] bg-[#151515] p-5 rounded hover:border-[var(--lucille-accent)] transition-all duration-300 group flex items-start justify-between">
                        <div>
                            <div class="font-display text-[10px] uppercase tracking-[.2em] text-[#7b7b7b]">{{ $admin['posts_label'] }}</div>
                            <div class="mt-2 font-display text-3xl text-white group-hover:text-[var(--lucille-accent)] transition-colors">{{ $stats['posts'] }}</div>
                        </div>
                        <span class="p-2 bg-[rgba(255,255,255,0.02)] border border-[#2b2b2b] rounded text-yellow-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                        </span>
                    </div>

                    <!-- Outreach Contacts -->
                    <div class="border border-[#2b2b2b] bg-[#151515] p-5 rounded hover:border-[var(--lucille-accent)] transition-all duration-300 group flex items-start justify-between">
                        <div>
                            <div class="font-display text-[10px] uppercase tracking-[.2em] text-[#7b7b7b]">Contactos Outreach</div>
                            <div class="mt-2 font-display text-3xl text-white group-hover:text-[var(--lucille-accent)] transition-colors">{{ $stats['outreach_contacts'] }}</div>
                        </div>
                        <span class="p-2 bg-[rgba(255,255,255,0.02)] border border-[#2b2b2b] rounded text-pink-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </span>
                    </div>

                    <!-- Outreach Sent -->
                    <div class="border border-[#2b2b2b] bg-[#151515] p-5 rounded hover:border-[var(--lucille-accent)] transition-all duration-300 group flex items-start justify-between">
                        <div>
                            <div class="font-display text-[10px] uppercase tracking-[.2em] text-[#7b7b7b]">Correos Enviados</div>
                            <div class="mt-2 font-display text-3xl text-white group-hover:text-[var(--lucille-accent)] transition-colors">{{ $stats['outreach_sent'] }}</div>
                        </div>
                        <span class="p-2 bg-[rgba(255,255,255,0.02)] border border-[#2b2b2b] rounded text-teal-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        </span>
                    </div>

                </div>
            </div>
        </section>

        <!-- Right Side: Theme info & Shortcuts -->
        <aside class="space-y-6">
            <!-- Current Theme settings info -->
            <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6 md:p-8 rounded-lg">
                <h2 class="font-display text-xl uppercase tracking-[.12em] text-white border-b border-[#2b2b2b] pb-2 mb-4">{{ $admin['current_theme'] }}</h2>
                <div class="space-y-3.5 text-xs text-[#8b8b8b]">
                    <div class="flex items-center justify-between border-b border-[#1b1b1d] pb-2">
                        <span class="font-semibold text-gray-400">{{ $admin['dashboard_logo_label'] }}:</span> 
                        <span class="font-mono text-[11px] text-[#c7c7c7] truncate max-w-[150px]" title="{{ $settings->logo_path ?? 'Default asset' }}">{{ basename($settings->logo_path ?? 'Default') }}</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-[#1b1b1d] pb-2">
                        <span class="font-semibold text-gray-400">{{ $admin['dashboard_background_label'] }}:</span> 
                        <span class="font-mono text-[11px] text-[#c7c7c7] truncate max-w-[150px]" title="{{ $settings->background_path ?? 'Default asset' }}">{{ basename($settings->background_path ?? 'Default') }}</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-[#1b1b1d] pb-2">
                        <span class="font-semibold text-gray-400">Tipografías:</span> 
                        <span class="text-[#c7c7c7]">{{ $settings->body_font }} / {{ $settings->heading_font }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-gray-400">Color de acento:</span> 
                        <div class="flex items-center gap-2">
                            <span class="h-3.5 w-3.5 rounded border border-[#2b2b2b]" style="background-color: {{ $settings->accent_color }}"></span>
                            <span class="font-mono text-[#c7c7c7]">{{ $settings->accent_color }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Organized shortcuts list -->
            <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6 md:p-8 rounded-lg">
                <h2 class="font-display text-xl uppercase tracking-[.12em] text-white border-b border-[#2b2b2b] pb-2 mb-4">Acceso Rápido</h2>
                
                <div class="space-y-4">
                    <div>
                        <span class="text-[9px] uppercase tracking-wider text-[#7b7b7b] font-semibold block mb-2">Publicación</span>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('admin.posts.index') }}" class="lucille-button py-1.5 px-3 text-[10px] uppercase tracking-wider text-white">Posts</a>
                            <a href="#taxonomias" class="lucille-button py-1.5 px-3 text-[10px] uppercase tracking-wider text-white">Taxonomías</a>
                        </div>
                    </div>

                    <div>
                        <span class="text-[9px] uppercase tracking-wider text-[#7b7b7b] font-semibold block mb-2">Música & Podcasts</span>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('admin.podcast-uploads.index') }}" class="lucille-button py-1.5 px-3 text-[10px] uppercase tracking-wider text-white">Podcast uploads</a>
                            <a href="{{ route('admin.songs.index') }}" class="lucille-button py-1.5 px-3 text-[10px] uppercase tracking-wider text-white">Canciones</a>
                            <a href="{{ route('admin.master-programs.index') }}" class="lucille-button py-1.5 px-3 text-[10px] uppercase tracking-wider text-white">Programas</a>
                            <a href="{{ route('admin.media-kit.form') }}" class="lucille-button py-1.5 px-3 text-[10px] uppercase tracking-wider text-white">Media Kit</a>
                        </div>
                    </div>

                    <div>
                        <span class="text-[9px] uppercase tracking-wider text-[#7b7b7b] font-semibold block mb-2">Configuración</span>
                        <div class="flex flex-wrap gap-2">
                            @role('Super Admin')
                            <a href="{{ route('admin.settings.edit') }}" class="lucille-button-solid py-1.5 px-3 text-[10px] uppercase tracking-wider">Ajustes</a>
                            @endrole
                            <a href="{{ route('home') }}" class="lucille-button py-1.5 px-3 text-[10px] uppercase tracking-wider text-white" target="_blank">Previsualizar web</a>
                        </div>
                    </div>
                </div>
            </section>
        </aside>

    </div>

    @php
        $pipelineCounts = $pipeline['counts'] ?? [];
        $recentPipelineEvents = $pipeline['recent_events'] ?? collect();
    @endphp

    <!-- Podcast Pipeline Section -->
    <section class="mt-8 border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6 md:p-8 rounded-lg">
        <div class="flex flex-wrap items-end justify-between gap-4 border-b border-[#2b2b2b] pb-3 mb-6">
            <div>
                <h2 class="font-display text-2xl uppercase tracking-[.12em] text-white">Podcast Pipeline</h2>
                <p class="mt-1 max-w-3xl text-xs text-[#7b7b7b]">
                    Estado operacional del pipeline de audios. Sincronización independiente de RadioBOSS, Archive.org y entrega final de podcasts.
                </p>
            </div>
            <div class="text-[10px] uppercase tracking-wider text-[#7b7b7b] bg-[rgba(255,255,255,0.02)] px-3 py-1.5 border border-[#2b2b2b] rounded">
                Eventos recientes registrados: <span class="text-white font-bold">{{ is_countable($recentPipelineEvents) ? count($recentPipelineEvents) : 0 }}</span>
            </div>
        </div>

        <!-- Pipeline Operative Counters Grid -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5 mb-8">
            @foreach ([
                'processing' => ['label' => 'Procesando', 'tone' => 'text-blue-400'],
                'radioboss_pending' => ['label' => 'RB Pendiente', 'tone' => 'text-yellow-400'],
                'archive_pending' => ['label' => 'Archive Pendiente', 'tone' => 'text-indigo-400'],
                'delivery_partial' => ['label' => 'Entrega Parcial', 'tone' => 'text-orange-400'],
                'delivery_verified' => ['label' => 'Entrega Verificada', 'tone' => 'text-green-400'],
            ] as $key => $props)
                <div class="border border-[#2b2b2b] bg-[#151515] p-5 rounded">
                    <div class="font-display text-[9px] uppercase tracking-[.2em] text-[#7b7b7b]">{{ $props['label'] }}</div>
                    <div class="mt-2 font-display text-3xl font-bold {{ $props['tone'] }}">{{ $pipelineCounts[$key] ?? 0 }}</div>
                </div>
            @endforeach
        </div>

        <!-- Pipeline details log -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Left Side: Recent logs events -->
            <div class="border border-[#2b2b2b] bg-[#151515] p-5 rounded">
                <h3 class="font-display text-xs uppercase tracking-[.12em] text-white border-b border-[#2b2b2b] pb-2 mb-4">Eventos Recientes</h3>
                <div class="space-y-3.5 max-h-[300px] overflow-y-auto pr-2">
                    @forelse ($recentPipelineEvents as $event)
                        <div class="border border-[#2b2b2b] bg-[rgba(255,255,255,.01)] p-4 rounded hover:bg-[rgba(255,255,255,.02)] transition-colors">
                            <div class="flex items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="text-xs text-white font-semibold truncate">{{ $event->event_type }}</div>
                                    <div class="text-[11px] text-[#8b8b8b] mt-1 line-clamp-2">{{ $event->event_message ?: 'Sin mensaje registrado.' }}</div>
                                </div>
                                <div class="text-[10px] text-gray-500 font-mono shrink-0">
                                    {{ optional($event->created_at)->format('d/m H:i') }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-[#7b7b7b] py-4 text-center">No hay eventos del pipeline registrados aún.</p>
                    @endforelse
                </div>
            </div>

            <!-- Right Side: Recent programs list -->
            <div class="border border-[#2b2b2b] bg-[#151515] p-5 rounded">
                <h3 class="font-display text-xs uppercase tracking-[.12em] text-white border-b border-[#2b2b2b] pb-2 mb-4">Programas Procesados</h3>
                <div class="space-y-3.5 max-h-[300px] overflow-y-auto pr-2">
                    @forelse (($pipeline['recent_programs'] ?? collect()) as $program)
                        <div class="border border-[#2b2b2b] bg-[rgba(255,255,255,.01)] p-4 rounded hover:bg-[rgba(255,255,255,.02)] transition-colors">
                            <div class="text-xs text-white font-semibold truncate">{{ $program->live_title ?: $program->titulo_programa }}</div>
                            <div class="text-[10px] text-[#8b8b8b] mt-2 flex flex-wrap gap-2.5">
                                <span class="px-2 py-0.5 bg-[#1b1b1d] border border-[#2b2b2b] rounded">RB: <strong class="text-white">{{ $program->radioboss_status ?? 'n/a' }}</strong></span>
                                <span class="px-2 py-0.5 bg-[#1b1b1d] border border-[#2b2b2b] rounded">Archive: <strong class="text-white">{{ $program->archive_org_status ?? 'n/a' }}</strong></span>
                                <span class="px-2 py-0.5 bg-[#1b1b1d] border border-[#2b2b2b] rounded">Entrega: <strong class="text-white">{{ $program->delivery_status ?? 'n/a' }}</strong></span>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-[#7b7b7b] py-4 text-center">No hay programas cargados recientemente.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <!-- Taxonomies section -->
    <section id="taxonomias" class="mt-8 border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6 md:p-8 rounded-lg">
        <div class="flex flex-wrap items-end justify-between gap-4 border-b border-[#2b2b2b] pb-3 mb-6">
            <div>
                <h2 class="font-display text-2xl uppercase tracking-[.12em] text-white">Taxonomías Rápidas</h2>
                <p class="mt-1 max-w-3xl text-xs text-[#7b7b7b]">
                    Registra categorías y etiquetas de manera global para que los editores puedan autocompletarlas al crear posts.
                </p>
            </div>
            <div class="text-[10px] uppercase tracking-wider text-[#7b7b7b]">
                Categorías: <span class="text-white font-bold">{{ $stats['categories'] }}</span> ·
                Etiquetas: <span class="text-white font-bold">{{ $stats['tags'] }}</span>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            @foreach ([
                ['label' => 'Categorías', 'type' => 'category', 'items' => $taxonomies['categories'], 'placeholder' => 'Noticias, Conciertos, Bandas'],
                ['label' => 'Etiquetas (Tags)', 'type' => 'tag', 'items' => $taxonomies['tags'], 'placeholder' => 'live, green day, metal, 2026'],
            ] as $group)
                <div class="border border-[#2b2b2b] bg-[#151515] p-5 rounded">
                    <div class="flex items-center justify-between border-b border-[#2b2b2b] pb-2 mb-4">
                        <h3 class="font-display text-xs uppercase tracking-[.12em] text-white">{{ $group['label'] }}</h3>
                        <span class="text-[10px] uppercase tracking-wider text-[#7b7b7b]">{{ $group['items']->count() }} registrados</span>
                    </div>

                    <!-- Add inline form -->
                    <form action="{{ route('admin.taxonomies.store') }}" method="POST" class="flex gap-2 mb-4">
                        @csrf
                        <input type="hidden" name="type" value="{{ $group['type'] }}">
                        <input
                            name="name"
                            class="lucille-product-field min-w-0 flex-1 py-1.5 text-xs"
                            placeholder="{{ $group['placeholder'] }}"
                            required
                        >
                        <button type="submit" class="lucille-button-solid py-1.5 px-4 text-xs font-semibold uppercase tracking-wider">Añadir</button>
                    </form>

                    <!-- Items List -->
                    <div class="flex flex-wrap gap-2 max-h-[220px] overflow-y-auto pr-1">
                        @forelse ($group['items'] as $taxonomy)
                            <span class="inline-flex items-center gap-2 border border-[#2b2b2b] bg-[rgba(255,255,255,.01)] hover:bg-[rgba(255,255,255,0.02)] px-2.5 py-1.5 rounded text-[10px] uppercase tracking-[.12em] text-[#c7c7c7] transition-all">
                                <span>{{ $taxonomy->name }}</span>
                                <a href="{{ route('admin.taxonomies.edit', $taxonomy) }}" class="text-gray-500 hover:text-white transition-colors" title="Editar">edit</a>
                                <form
                                    action="{{ route('admin.taxonomies.destroy', $taxonomy) }}"
                                    method="POST"
                                    data-confirm="{{ '¿Eliminar la taxonomía ' . $taxonomy->name . '?' }}"
                                    data-confirm-title="Eliminar taxonomía"
                                    data-confirm-action="Eliminar"
                                    data-confirm-tone="danger"
                                    class="inline"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-gray-500 hover:text-red-400 text-sm leading-3" title="Eliminar">×</button>
                                </form>
                            </span>
                        @empty
                            <p class="text-xs text-[#7b7b7b] py-2">No hay elementos registrados aún.</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <!-- Outreach Section -->
    <section class="mt-8 border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6 md:p-8 rounded-lg mb-8">
        <div class="flex flex-wrap items-end justify-between gap-4 border-b border-[#2b2b2b] pb-3 mb-6">
            <div>
                <h2 class="font-display text-2xl uppercase tracking-[.12em] text-white">Outreach (Convocatorias)</h2>
                <p class="mt-1 max-w-3xl text-xs text-[#7b7b7b]">
                    Monitoreo de campañas masivas por e-mail a manager de bandas, agencias y actividad de registro reciente.
                </p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Campaigns -->
            <div class="border border-[#2b2b2b] bg-[#151515] p-5 rounded">
                <h3 class="font-display text-xs uppercase tracking-[.12em] text-white border-b border-[#2b2b2b] pb-2 mb-4">Campañas de Convocatoria Recientes</h3>
                <div class="space-y-3 max-h-[300px] overflow-y-auto pr-1">
                    @forelse ($recentCampaigns as $campaign)
                        <div class="border border-[#2b2b2b] bg-[rgba(255,255,255,.01)] p-4 rounded flex justify-between items-center hover:bg-[rgba(255,255,255,.02)] transition-colors">
                            <div>
                                <div class="text-xs text-white font-semibold">{{ $campaign->name }}</div>
                                <div class="text-[10px] text-[#8b8b8b] uppercase tracking-wider mt-1">{{ $campaign->template?->name ?? 'Sin plantilla' }}</div>
                            </div>
                            <div class="text-[10px] font-mono text-gray-400 bg-[#1e1e21] border border-[#2b2b2b] px-2 py-1 rounded">
                                {{ $campaign->sent_count }} envíos
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-[#7b7b7b] py-4 text-center">No hay campañas registradas todavía.</p>
                    @endforelse
                </div>
            </div>

            <!-- Contacts -->
            <div class="border border-[#2b2b2b] bg-[#151515] p-5 rounded">
                <h3 class="font-display text-xs uppercase tracking-[.12em] text-white border-b border-[#2b2b2b] pb-2 mb-4">Últimos Contactos Registrados</h3>
                <div class="space-y-3 max-h-[300px] overflow-y-auto pr-1">
                    @forelse ($recentContacts as $contact)
                        <div class="border border-[#2b2b2b] bg-[rgba(255,255,255,.01)] p-4 rounded flex justify-between items-center hover:bg-[rgba(255,255,255,.02)] transition-colors">
                            <div>
                                <div class="text-xs text-white font-semibold">{{ $contact->displayName() }}</div>
                                <div class="text-[10px] text-[#8b8b8b] mt-1">{{ $contact->email ?: 'Sin correo electrónico' }}</div>
                            </div>
                            <div class="text-[9px] uppercase tracking-widest font-semibold px-2 py-0.5 rounded border border-[#2b2b2b] bg-[#1e1e21] text-gray-400">
                                {{ $contact->status }}
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-[#7b7b7b] py-4 text-center">No hay contactos cargados aún.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
</x-layouts.admin>
