@php
    $shareTitle = $talent->band_name . ' - Seven Rock Radio';
    $shareDesc = $talent->bio ? Str::limit($talent->bio, 160) : 'Perfil de ' . $talent->band_name . ' en Seven Rock Radio';

    // If it belongs to an agency, prepend agency name to sharing preview
    if ($talent instanceof \App\Models\RadioArtistTalentFallback && $talent->agency_id) {
        $agency = $talent->agency;
        if ($agency) {
            $shareTitle = $agency->name . ' presenta: ' . $talent->band_name . ' - Seven Rock Radio';
            $shareDesc = 'Perfil de ' . $talent->band_name . ' (representado por ' . $agency->name . ') en Seven Rock Radio. ' . ($talent->bio ? Str::limit($talent->bio, 100) : '');
        }
    }
@endphp

<x-layouts.site :title="$shareTitle"
    :description="$shareDesc"
    :og-image="$talent->logoUrl() ?? asset('assets/lucille/logo.png')">
    <section class="mx-auto max-w-7xl px-5 pt-32 pb-16">
        <!-- Profile Header -->
        <div class="relative overflow-hidden rounded-[20px] bg-gradient-to-br from-[#10151a]/95 to-[#070a0d]/98 border border-white/10 p-8 md:p-12 text-center shadow-[0_20px_50px_rgba(0,0,0,0.5)] backdrop-blur-md">
            <div class="absolute inset-0 opacity-20 pointer-events-none bg-[radial-gradient(circle_at_center,var(--lucille-accent),transparent_70%)]"></div>
            <div class="relative z-10 flex flex-col items-center">
                <div class="relative group">
                    <div class="absolute inset-0 rounded-full blur-[15px] opacity-60 bg-[var(--lucille-accent)] group-hover:opacity-85 transition-opacity duration-300"></div>
                    <div class="relative h-[160px] w-[160px] overflow-hidden rounded-full border-4 border-white/15 hover:border-[var(--lucille-accent)] transition-colors duration-300 shadow-2xl">
                        <img src="{{ $talent->logoUrl() ?? asset('assets/lucille/beatles_t_shirt.jpeg') }}" alt="{{ $talent->band_name }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110" loading="lazy" width="160" height="160">
                    </div>
                </div>
                <h1 class="font-display text-4xl md:text-6xl uppercase tracking-[.18em] text-white mt-6 drop-shadow-lg">{{ $talent->band_name }}</h1>
                <div class="mt-4 flex flex-wrap justify-center items-center gap-3">
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4.5 py-1.5 text-xs font-semibold uppercase tracking-[.15em] text-gray-300">
                        Plan: {{ ucfirst($talent->plan) }}
                    </span>
                    @if ($talent->is_featured)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-[#d4af37]/30 bg-[#d4af37]/10 px-4.5 py-1.5 text-xs font-bold uppercase tracking-[.15em] text-[#d4af37] shadow-[0_0_15px_rgba(212,175,55,0.25)] animate-pulse">
                            ⭐ Destacado
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="flex justify-center gap-6 md:gap-12 flex-wrap rounded-[16px] border border-white/10 bg-[#10151a]/60 backdrop-blur-md px-6 py-5 shadow-lg max-w-2xl mx-auto -mt-6 relative z-20">
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
        </div>

        <!-- Main Layout Grid -->
        <div class="grid gap-8 lg:grid-cols-[1.2fr_.8fr] mt-12">
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
                                <a href="{{ $url }}" target="_blank" rel="noreferrer" class="lucille-button flex items-center gap-2 hover:border-[var(--lucille-accent)] hover:text-white transition-all">
                                    <span>{{ ucfirst($network) }}</span>
                                    <span class="text-[10px]">↗</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Multimedia Content Panel -->
                @if ($media->isNotEmpty())
                    <div class="border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-6 md:p-8 shadow-xl">
                        <h3 class="font-display text-xl uppercase tracking-[.18em] text-white border-b border-white/5 pb-3">Contenido</h3>
                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            @foreach ($media as $item)
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
                                        @if ($item->type === 'photo')
                                            <div class="aspect-video w-full overflow-hidden rounded-[8px] bg-black/40 relative">
                                                <img src="{{ $item->url }}" alt="{{ $item->title }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy" width="400" height="225">
                                            </div>
                                            <p class="mt-3 text-sm font-semibold text-white truncate">{{ $item->title ?: $item->filename }}</p>
                                        @elseif ($item->type === 'mp3')
                                            <!-- Custom Styled Audio Player using Alpine.js -->
                                            <div class="custom-audio-player-wrapper py-1" x-data="{
                                                playing: false,
                                                duration: 0,
                                                currentTime: 0,
                                                audio: null,
                                                init() {
                                                    this.audio = new Audio('{{ $item->url }}');
                                                    this.audio.preload = 'none';
                                                    this.audio.addEventListener('durationchange', () => this.duration = this.audio.duration);
                                                    this.audio.addEventListener('timeupdate', () => this.currentTime = this.audio.currentTime);
                                                    this.audio.addEventListener('ended', () => this.playing = false);
                                                },
                                                togglePlay() {
                                                    if (this.playing) {
                                                        this.audio.pause();
                                                        this.playing = false;
                                                    } else {
                                                        window.dispatchEvent(new CustomEvent('stop-all-audio', { detail: { except: this.audio } }));
                                                        this.audio.play();
                                                        this.playing = true;
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
                                                    this.audio.currentTime = percent * this.duration;
                                                }
                                            }" x-on:stop-all-audio.window="if (audio !== $event.detail.except) { audio.pause(); playing = false; }">
                                                <div class="flex items-center gap-4">
                                                    <button type="button" @click="togglePlay()" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[var(--lucille-accent)] text-white hover:scale-105 transition-transform shadow-md" aria-label="Reproducir/Pausar">
                                                        <template x-if="!playing">
                                                            <svg class="h-5 w-5 fill-current ml-0.5" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                                        </template>
                                                        <template x-if="playing">
                                                            <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                                                        </template>
                                                    </button>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="truncate text-sm font-semibold text-white">{{ $item->title ?: $item->filename }}</p>
                                                        <div class="mt-1.5 flex items-center gap-3">
                                                            <input type="range" min="0" max="100" :value="duration ? (currentTime / duration) * 100 : 0" @input="seek($event)" class="audio-slider flex-1">
                                                            <span class="text-[10px] text-gray-400 font-mono shrink-0" x-text="formatTime(currentTime) + ' / ' + (duration ? formatTime(duration) : '0:00')">0:00 / 0:00</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @elseif ($item->type === 'video')
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

            <!-- Right Column: Likes, Comments -->
            <div class="space-y-8">
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
