<x-layouts.site :title="'Muro de la Comunidad'">
    <section class="mx-auto max-w-[1400px] px-5 pt-32 pb-12">
        
        {{-- Cabecera de la Sección --}}
        <div class="mb-10 flex flex-wrap items-center justify-between gap-4 border-b border-white/5 pb-6">
            <div>
                <span class="text-[10px] uppercase tracking-[.25em] text-[var(--lucille-accent)] font-semibold font-display">Espacio de Interacción</span>
                <h1 class="font-display text-4xl uppercase tracking-[.12em] text-[#dcdcdc] mt-1">Muro de la Comunidad</h1>
                <p class="mt-2 text-sm text-[#7b7b7b]">El punto de encuentro oficial entre bandas, oyentes y locutores de Seven Rock Radio.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('comunidad.exclusivos') }}" class="lucille-button-solid rounded-[8px] px-5 py-2.5 text-xs font-display uppercase tracking-wider flex items-center gap-2">
                    🎵 Descargas & Exclusivos
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="mb-6 border border-green-500/20 bg-green-500/5 rounded-[12px] p-4 text-xs text-green-400">
                {{ session('status') }}
            </div>
        @endif

        {{-- Grid del Muro --}}
        <div class="grid gap-8 lg:grid-cols-[1fr_360px]">
            
            {{-- Sección de Publicación y Timeline --}}
            <div class="space-y-8">
                
                {{-- Formulario para publicar --}}
                <div class="border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-6 shadow-xl">
                    <h2 class="font-display text-lg uppercase tracking-[.10em] text-[#dcdcdc] mb-4">¿Qué está sonando hoy?</h2>
                    
                    @php
                        $authorName = '';
                        $isBand = false;
                        if (Auth::guard('web')->check()) {
                            $authorName = Auth::guard('web')->user()->name;
                        } elseif (Auth::guard('talent')->check()) {
                            $authorName = Auth::guard('talent')->user()->band_name;
                            $isBand = true;
                        }
                    @endphp

                    <form action="{{ route('comunidad.muro.post') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <textarea 
                                name="content" 
                                rows="3" 
                                placeholder="Escribe tu mensaje... comparte tu banda favorita, opina sobre la radio o saluda a la comunidad (máx. 500 caracteres)" 
                                class="lucille-product-field w-full rounded-[8px] resize-none text-sm leading-relaxed p-4" 
                                maxlength="500" 
                                required
                            ></textarea>
                            @error('content')
                                <span class="mt-1 block text-[10px] text-red-400 uppercase tracking-wider font-mono">{{ $message }}</span>
                            @enderror
                        </div>
                        
                        <div class="grid gap-4 sm:grid-cols-[1fr_auto]">
                            <div>
                                <input 
                                    type="url" 
                                    name="youtube_url" 
                                    placeholder="Enlace de YouTube (opcional, ej. https://www.youtube.com/watch?v=...)" 
                                    class="lucille-product-field w-full rounded-[8px] text-xs py-2 px-3"
                                >
                                @error('youtube_url')
                                    <span class="mt-1 block text-[10px] text-red-400 uppercase tracking-wider font-mono">{{ $message }}</span>
                                @enderror
                            </div>
                            <button type="submit" class="lucille-button-solid rounded-[8px] px-6 py-2 text-xs font-display uppercase tracking-wider h-fit">
                                Publicar
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Feed / Timeline --}}
                <div class="space-y-6">
                    @forelse ($posts as $post)
                        @php
                            $postAuthor = '';
                            $badgeText = 'Fan';
                            $badgeClass = 'border-white/10 bg-white/5 text-gray-400';
                            $avatarLetter = 'F';

                            if ($post->talent) {
                                $postAuthor = $post->talent->band_name;
                                $badgeText = 'Banda';
                                $badgeClass = 'border-[var(--lucille-accent)]/20 bg-[var(--lucille-accent)]/10 text-[var(--lucille-accent)]';
                                $avatarLetter = strtoupper(substr($postAuthor, 0, 1));
                            } elseif ($post->user) {
                                $postAuthor = $post->user->name;
                                $avatarLetter = strtoupper(substr($postAuthor, 0, 1));
                            } else {
                                $postAuthor = 'Seven Rock Radio';
                                $badgeText = 'Radio';
                                $badgeClass = 'border-[#c32720]/25 bg-[#c32720]/15 text-[#c32720] font-bold';
                                $avatarLetter = 'S';
                            }

                            // YouTube URL Parsing
                            $embedUrl = null;
                            if ($post->youtube_url) {
                                if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $post->youtube_url, $match)) {
                                    $embedUrl = "https://www.youtube.com/embed/" . $match[1];
                                }
                            }
                        @endphp

                        <div class="border border-white/5 bg-white/[0.01] rounded-[16px] p-6 hover:bg-white/[0.02] transition-colors duration-300">
                            <div class="flex items-start gap-4">
                                {{-- Avatar ficticio con inicial --}}
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#1b1b1e] border border-white/10 text-white font-bold font-display text-sm">
                                    {{ $avatarLetter }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-display text-sm font-semibold text-white tracking-wide">
                                            @if($post->talent)
                                                <a href="{{ route('talents.show', $post->talent->band_name) }}" class="hover:text-[var(--lucille-accent)] transition-colors">
                                                    {{ $postAuthor }}
                                                </a>
                                            @else
                                                {{ $postAuthor }}
                                            @endif
                                        </h3>
                                        <span class="rounded border px-2 py-0.5 text-[9px] uppercase font-semibold tracking-wider {{ $badgeClass }}">
                                            {{ $badgeText }}
                                        </span>
                                        <span class="text-[10px] text-[#555] font-mono">&bull; {{ $post->created_at->diffForHumans() }}</span>
                                    </div>
                                    
                                    <p class="mt-3 text-sm leading-relaxed text-[#c8c8c8] whitespace-pre-line select-text">{{ $post->content }}</p>

                                    @if ($embedUrl)
                                        <div class="mt-4 overflow-hidden rounded-[8px] border border-white/5 bg-black max-w-[560px]">
                                            <div class="relative w-full aspect-video">
                                                <iframe 
                                                    class="absolute inset-0 w-full h-full"
                                                    src="{{ $embedUrl }}" 
                                                    frameborder="0" 
                                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                                    allowfullscreen
                                                    loading="lazy"
                                                ></iframe>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="border border-dashed border-white/10 rounded-[16px] p-12 text-center text-[#7b7b7b]">
                            <p class="text-sm">El Muro está vacío en este momento. ¡Sé el primero en iniciar la conversación!</p>
                        </div>
                    @endforelse

                    <div class="mt-6 font-mono text-xs">
                        {{ $posts->links() }}
                    </div>
                </div>

            </div>

            {{-- Barra Lateral (Sidebar) --}}
            <aside class="space-y-6">
                
                {{-- Mi Perfil Card --}}
                <div class="border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-6 shadow-xl">
                    <h2 class="font-display text-sm uppercase tracking-[.15em] text-[#dcdcdc] border-b border-white/5 pb-3">Mi Afiliación</h2>
                    <div class="mt-4 flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-[var(--lucille-accent)]/10 border border-[var(--lucille-accent)]/20 text-[var(--lucille-accent)] font-bold font-display text-sm flex items-center justify-center">
                            {{ strtoupper(substr($authorName, 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-white tracking-wide truncate">{{ $authorName }}</div>
                            <div class="text-[10px] uppercase tracking-wider text-gray-500 mt-0.5">
                                {{ $isBand ? 'Banda Registrada' : 'Miembro Afiliado' }}
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-5 border-t border-white/5 pt-4">
                        @if (Auth::guard('web')->check())
                            <form action="{{ route('afiliados.logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full text-center py-2 border border-white/10 hover:border-red-500/30 hover:text-red-400 transition-colors text-xs font-display uppercase tracking-wider rounded-[8px]">
                                    Cerrar Sesión
                                </button>
                            </form>
                        @elseif (Auth::guard('talent')->check())
                            <form action="{{ route('talents.logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full text-center py-2 border border-white/10 hover:border-red-500/30 hover:text-red-400 transition-colors text-xs font-display uppercase tracking-wider rounded-[8px]">
                                    Cerrar Sesión (Banda)
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Recomendados sidebar --}}
                <div class="border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-6 shadow-xl">
                    <h2 class="font-display text-sm uppercase tracking-[.15em] text-[#dcdcdc] border-b border-white/5 pb-3">Recomendados del Muro</h2>
                    <div class="mt-4 space-y-4">
                        @forelse ($featuredBands as $band)
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    @if ($band->logo)
                                        <img src="{{ \App\Support\PublicMediaUrl::normalizePublicUrl($band->logo) }}" alt="{{ $band->band_name }}" class="h-8 w-8 rounded-full border border-white/10 object-cover shrink-0">
                                    @else
                                        <div class="h-8 w-8 rounded-full bg-white/5 border border-white/10 text-white font-bold font-display text-xs flex items-center justify-center shrink-0">
                                            {{ strtoupper(substr($band->band_name, 0, 1)) }}
                                        </div>
                                    @endif
                                    <span class="text-xs font-semibold text-white tracking-wide truncate block">{{ $band->band_name }}</span>
                                </div>
                                <a href="{{ route('talents.show', $band->band_name) }}" class="lucille-button text-[10px] uppercase py-1 px-3.5 tracking-wider shrink-0 rounded-[6px]">
                                    Ver Ficha
                                </a>
                            </div>
                        @empty
                            <p class="text-xs text-[#7b7b7b]">No hay bandas registradas aún.</p>
                        @endforelse
                    </div>
                </div>

            </aside>
        </div>
    </section>
</x-layouts.site>
