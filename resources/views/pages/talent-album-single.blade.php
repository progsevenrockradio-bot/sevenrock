<x-layouts.site :title="'Seven Rock Radio - ' . $album->title"
    :description="$album->description ?? 'Album de ' . $talent->band_name . ' en Seven Rock Radio'"
    :og-image="$album->coverUrl() ?? asset('assets/lucille/logo.png')">
    <x-sections.page-heading
        :title="$album->title"
        :subtitle="$talent->band_name ?? 'Talento'"
        image="assets/lucille/album1.jpg"
        overlay="rgba(21,21,21,.86)"
    />

    <section x-data="albumPreview">
    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('albumPreview', () => ({
                currentTrack: null,
                playing: false,
                loading: false,
                progress: 0,
                elapsed: 0,
                duration: 0,
                previewDuration: 30,
                audio: null,

                play(trackIndex, previewUrl) {
                    this.stop();
                    if (!previewUrl) return;
                    this.currentTrack = trackIndex;
                    this.loading = true;
                    const audio = new Audio(previewUrl);
                    audio.preload = 'auto';
                    audio.addEventListener('canplaythrough', () => {
                        this.loading = false;
                        audio.play().then(() => { this.playing = true; }).catch(() => { this.playing = false; this.loading = false; });
                    }, { once: true });
                    audio.addEventListener('timeupdate', () => {
                        this.elapsed = audio.currentTime;
                        this.duration = Math.min(audio.duration || this.previewDuration, this.previewDuration);
                        this.progress = audio.duration > 0 ? (audio.currentTime / this.previewDuration) * 100 : 0;
                        if (audio.currentTime >= this.previewDuration) { this.stop(); }
                    });
                    audio.addEventListener('ended', () => { this.stop(); });
                    audio.addEventListener('error', () => { this.loading = false; this.playing = false; });
                    this.audio = audio;
                },

                toggle(trackIndex, previewUrl) {
                    if (this.currentTrack === trackIndex && this.playing) { this.pause(); }
                    else { this.play(trackIndex, previewUrl); }
                },

                pause() { if (this.audio) { this.audio.pause(); } this.playing = false; },

                stop() {
                    if (this.audio) { this.audio.pause(); this.audio.currentTime = 0; this.audio = null; }
                    this.playing = false; this.currentTrack = null; this.progress = 0;
                    this.elapsed = 0; this.duration = 0; this.loading = false;
                },

                seek(event) {
                    if (!this.audio || this.currentTrack === null) return;
                    const rect = event.currentTarget.getBoundingClientRect();
                    const x = event.clientX - rect.left;
                    const ratio = Math.max(0, Math.min(1, x / rect.width));
                    this.audio.currentTime = ratio * this.previewDuration;
                },

                formatTime(seconds) {
                    const m = Math.floor(seconds / 60);
                    const s = Math.floor(seconds % 60);
                    return `${m}:${s.toString().padStart(2, '0')}`;
                },

                get isPlaying() { return this.playing; },
                get currentIndex() { return this.currentTrack; },
            }));
        });
    </script>
    @endpush
        <div class="lucille-content-box">
            <div class="grid gap-8 lg:grid-cols-[40%_60%]">
                <aside class="lg:pr-[15px]">
                    <img src="{{ $album->coverUrl() ?? asset('assets/lucille/man-597179_1920.jpg') }}" alt="{{ $album->title }}" loading="lazy" class="w-full max-w-[500px]">

                    <div class="mt-[15px] space-y-[10px] text-[#7b7b7b]">
                        <p><span class="mr-2 text-[#dcdcdc]">Talento:</span>{{ $talent->band_name ?? 'Sin nombre' }}</p>
                        @if ($album->release_date)
                            <p><span class="mr-2 text-[#dcdcdc]">Lanzamiento:</span>{{ $album->release_date->format('F Y') }}</p>
                        @endif
                        <p><span class="mr-2 text-[#dcdcdc]">Canciones:</span>{{ count($album->tracks ?? []) }}</p>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-2">
                        <a href="{{ route('talents.show', ['bandName' => $talent->band_name]) }}" class="lucille-button min-h-[32px] px-[13px] text-[10px] tracking-[2px]">
                            🎵 Ver Perfil
                        </a>
                        @if($hasProducts ?? false)
                            <a href="{{ route('talents.show', ['bandName' => $talent->band_name]) }}" class="lucille-button min-h-[32px] px-[13px] text-[10px] tracking-[2px]">
                                🛒 Tienda
                            </a>
                        @endif
                        @if($talent->website_url)
                            <a href="{{ $talent->website_url }}" target="_blank" rel="noreferrer" class="lucille-button min-h-[32px] px-[13px] text-[10px] tracking-[2px]">
                                🌐 Sitio Web
                            </a>
                        @endif
                        @foreach($paymentLinks ?? [] as $label => $url)
                            <a href="{{ $url }}" target="_blank" rel="nofollow noopener" class="lucille-button min-h-[32px] px-[13px] text-[10px] tracking-[2px]">
                                💳 {{ ucfirst($label) }}
                            </a>
                        @endforeach
                        <a href="{{ route('talents.explore') }}" class="lucille-button min-h-[32px] px-[13px] text-[10px] tracking-[2px]">
                            👥 Más Artistas
                        </a>
                    </div>

                    <div class="mt-8 text-sm leading-[26px] text-[#7b7b7b]">
                        @if ($album->description)
                            <p>{{ $album->description }}</p>
                        @endif
                    </div>
                </aside>

                <div class="lg:pl-[15px]">
                    <div class="mb-6">
                        <h3 class="font-display text-sm uppercase tracking-[.18em] text-[#7b7b7b]">Lista de canciones</h3>
                        <p class="mt-1 text-xs text-[#5a5a5a]">Haz clic en ▶ para escuchar una vista previa de 30 segundos</p>
                    </div>

                    <div class="space-y-[2px]">
                        @forelse (($album->tracks ?? []) as $trackIndex => $track)
                            @php $trackTitle = $track['title'] ?? 'Track ' . ($trackIndex + 1); @endphp
                            <div class="bg-[#222] px-5 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <button
                                            type="button"
                                            @click="toggle({{ $trackIndex }}, '{{ addslashes($track['preview_url'] ?? '') }}')"
                                            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full border border-[#3a3a3a] bg-[#191919] text-lg text-[#dcdcdc] transition hover:border-lucille-accent hover:text-lucille-accent"
                                            :class="{ 'border-lucille-accent text-lucille-accent': currentIndex === {{ $trackIndex }} && isPlaying }"
                                        >
                                            <template x-if="currentIndex !== {{ $trackIndex }} || !isPlaying">
                                                <span>▶</span>
                                            </template>
                                            <template x-if="currentIndex === {{ $trackIndex }} && isPlaying">
                                                <span>❚❚</span>
                                            </template>
                                        </button>
                                        <div class="min-w-0 flex-1">
                                            <span class="text-[#dcdcdc]">{{ $loop->iteration }}. {{ $trackTitle }}</span>
                                            @if (!empty($track['duration']))
                                                <span class="ml-2 text-xs text-[#5a5a5a]">({{ $track['duration'] }})</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="flex-shrink-0 text-right text-xs text-[#5a5a5a]">
                                        @if (!empty($track['preview_url']))
                                            <span class="text-lucille-accent">▶ 30s</span>
                                        @else
                                            Sin preview
                                        @endif
                                    </div>
                                </div>

                                <!-- Progress bar -->
                                <div class="mt-2" x-show="currentIndex === {{ $trackIndex }}">
                                    <div
                                        class="h-10 rounded-sm border border-[#2b2b2b] bg-[#191919] cursor-pointer"
                                        @click="seek($event)"
                                    >
                                        <div class="flex h-full items-center gap-3 px-3 text-xs text-[#7b7b7b]">
                                            <span class="text-lg text-[#dcdcdc]" x-show="!loading">▶</span>
                                            <span class="h-px flex-1 bg-[#3a3a3a] relative overflow-hidden">
                                                <span class="absolute inset-y-0 left-0 bg-lucille-accent" :style="'width: ' + progress + '%'"></span>
                                            </span>
                                            <span x-text="formatTime(elapsed) + ' / 0:30'"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="py-8 text-center text-sm text-[#7b7b7b]">
                                Este álbum no tiene canciones.
                            </div>
                        @endforelse
                    </div>

                    @if ($talentMp3s->isNotEmpty())
                        <div class="mt-8 rounded border border-white/10 bg-[#10161b] p-6">
                            <h4 class="font-display text-xs uppercase tracking-[.18em] text-[#7b7b7b]">MP3s disponibles de {{ $talent->band_name }}</h4>
                            <p class="mt-1 text-xs text-[#5a5a5a]">Estos son los MP3 que el talento ha subido. Copia la URL para usarla como preview.</p>
                            <div class="mt-4 space-y-2">
                                @foreach ($talentMp3s as $mp3)
                                    <div class="flex items-center gap-3 rounded bg-[#1d1d1d] px-3 py-2">
                                        <span class="flex-shrink-0 text-xs text-[#5a5a5a]">MP3</span>
                                        <span class="flex-1 truncate text-xs text-[#c7d0d8]">{{ $mp3->title ?? $mp3->filename }}</span>
                                        <button type="button" class="flex-shrink-0 text-xs text-lucille-accent hover:text-white"
                                            @click="
                                                navigator.clipboard.writeText('{{ addslashes($mp3->url) }}').then(() => {
                                                    $el.textContent = '✓ Copiado';
                                                    setTimeout(() => $el.textContent = 'Copiar URL', 2000);
                                                })
                                            "
                                        >Copiar URL</button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</x-layouts.site>