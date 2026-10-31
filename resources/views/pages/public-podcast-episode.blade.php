@php
    $episodeTitle = $episode->live_title ?: (($master->nombre ?? 'Programa') . ' #' . ($episode->numero_episodio ?? '1'));
    $episodeDesc = \Illuminate\Support\Str::limit(strip_tags($episode->editorial_summary ?: $episode->resena ?: 'Episodio exclusivo en Seven Rock Radio'), 250);
    $episodeCanonical = route('public.podcasts.episode', [
        'programSlug' => $master->publicSlug(),
        'episodeNumber' => (int) $episode->numero_episodio,
    ]);

    $podcastEpisodeSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'PodcastEpisode',
        'name' => $episodeTitle,
        'episodeNumber' => (int) $episode->numero_episodio,
        'description' => $episodeDesc,
        'url' => $episodeCanonical,
        'partOfSeries' => [
            '@type' => 'PodcastSeries',
            'name' => $master->nombre,
            'url' => route('programs.detail', $master->archive_identifier ?: \Illuminate\Support\Str::slug($master->nombre)),
        ],
    ];

    if (!empty($coverUrl)) {
        $podcastEpisodeSchema['image'] = $coverUrl;
    }

    if (!empty($episode->created_at)) {
        $podcastEpisodeSchema['datePublished'] = $episode->created_at->toIso8601String();
    }

    if (!empty($audioPlaybackUrl)) {
        $podcastEpisodeSchema['associatedMedia'] = [
            '@type' => 'MediaObject',
            'contentUrl' => $audioPlaybackUrl,
        ];
    }
@endphp

<x-layouts.site 
    :title="'Seven Rock Radio · ' . ($master->nombre ?? 'Programa') . ' #' . ($episode->numero_episodio ?? '1') . ' — ' . ($episode->live_title ?? 'Episodio')"
    :og-image="$coverUrl"
    :description="$episodeDesc"
    :canonical="$episodeCanonical"
>
    @push('jsonld')
    <script type="application/ld+json">
    {!! json_encode($podcastEpisodeSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>
    @endpush
    <x-sections.page-heading
        :title="$master->nombre"
        :subtitle="sprintf('Episodio #%d · Conduce: %s', (int) $episode->numero_episodio, $episode->conductor ?: $master->conductor ?: 'Seven Rock Radio')"
        :image="$coverUrl"
        overlay="rgba(19,19,19,.92)"
    />

    <section class="min-h-screen bg-[#151515] py-12 text-[#dcdcdc]">
        <div class="mx-auto max-w-[1140px] px-6">
            {{-- Tarjeta principal del episodio --}}
            <article class="border border-[#2b2b2b] bg-[#101012] p-8 shadow-[0_28px_72px_rgba(0,0,0,.58)]">
                <div class="grid gap-8 md:grid-cols-[280px_1fr]">
                    {{-- Portada --}}
                    <div>
                        <div class="aspect-square w-full overflow-hidden border border-[#2b2b2b] bg-black">
                            <img src="{{ $coverUrl }}" alt="Portada de {{ $master->nombre }}" class="h-full w-full object-cover">
                        </div>

                        {{-- Botón de descarga pública (si está autorizada) --}}
                        @if ($allowPublicDownload)
                            <div class="mt-5">
                                <a
                                    href="{{ route('public.podcasts.download', ['programSlug' => $master->publicSlug(), 'episodeNumber' => (int) $episode->numero_episodio]) }}"
                                    class="lucille-button-solid flex w-full items-center justify-center gap-2 text-center text-xs tracking-wider"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    Descargar MP3
                                </a>
                            </div>
                        @endif
                    </div>

                    {{-- Datos del Episodio --}}
                    <div class="space-y-5">
                        <div class="flex flex-wrap items-center gap-3 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">
                            <span class="rounded bg-[#1a1a1d] px-2.5 py-1 text-[#c32720] border border-[#2c2c30]">Episodio #{{ (int) $episode->numero_episodio }}</span>
                            @if ($episode->fecha_emision)
                                <span>Emisión: {{ $episode->fecha_emision->format('d/m/Y') }}</span>
                            @endif
                            @if ($master->horario_transmision || $master->hora_transmision)
                                <span>· {{ $master->dia_transmision }} {{ substr((string) ($master->hora_transmision ?: ''), 0, 5) }}</span>
                            @endif
                        </div>

                        <h1 class="font-display text-3xl uppercase tracking-[.08em] text-[#f0f0f0]">
                            {{ $episode->live_title }}
                        </h1>

                        {{-- Reproductor de audio embebido --}}
                        @if (!empty($audioPlaybackUrl))
                            <div class="border border-[#2b2b2b] bg-[#0c0c0e] p-5 rounded">
                                <p class="mb-3 text-[11px] uppercase tracking-[.18em] text-[#9a9a9a]">Escuchar episodio</p>
                                <audio controls class="w-full" preload="metadata"
                                    onplay="if (window.SevenRockAnalytics) window.SevenRockAnalytics.track('podcast_play', { episode_id: '{{ $episode->id }}', program_slug: '{{ $master->publicSlug() }}' });"
                                    onseeked="if (window.SevenRockAnalytics) window.SevenRockAnalytics.track('podcast_seek', { episode_id: '{{ $episode->id }}', seek_position_seconds: Math.round(this.currentTime) });"
                                >
                                    <source src="{{ $audioPlaybackUrl }}" type="audio/mpeg">
                                    Tu navegador no soporta el reproductor de audio HTML5.
                                </audio>
                            </div>
                        @endif

                        {{-- Resumen editorial --}}
                        @if (!empty($episode->editorial_summary))
                            <div class="border-l-2 border-[#c32720] pl-4 py-1 text-sm leading-relaxed text-[#b0b0b0] italic">
                                {{ $episode->editorial_summary }}
                            </div>
                        @endif

                        {{-- Reseña completa --}}
                        @if (!empty($episode->resena))
                            <div>
                                <h3 class="mb-2 text-xs uppercase tracking-[.18em] text-[#8b8b8b]">Reseña y detalles</h3>
                                <div class="text-sm leading-relaxed text-[#cbcbcb] space-y-3 whitespace-pre-line">
                                    {{ $episode->resena }}
                                </div>
                            </div>
                        @endif

                        {{-- Notas del invitado si existen --}}
                        @if (!empty($episode->biografia_invitado))
                            <div class="border-t border-[#202024] pt-4">
                                <h4 class="mb-1 text-xs uppercase tracking-[.18em] text-[#8b8b8b]">Notas del Invitado</h4>
                                <p class="text-xs text-[#9a9a9a] leading-relaxed">{{ $episode->biografia_invitado }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </article>

            {{-- Cintillo de la emisora en directo --}}
            <div class="mt-8 border border-[#2b2b2b] bg-[#081a24] p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <p class="text-[11px] uppercase tracking-[.22em] text-[#c32720]">Seven Rock Radio · 24/7</p>
                    <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#f0f0f0]">¿Quieres más rock? Escucha nuestra radio en directo</h3>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('home') }}" class="lucille-button-solid text-xs">Escuchar Emisora en Vivo</a>
                    <a href="{{ route('programs') }}" class="lucille-button text-xs">Ver Programación</a>
                </div>
            </div>

            {{-- Descubrir otros programas --}}
            @if ($otherPrograms->isNotEmpty())
                <div class="mt-12">
                    <h3 class="mb-6 font-display text-xl uppercase tracking-[.12em] text-[#f0f0f0]">Descubre otros programas</h3>
                    <div class="grid gap-6 sm:grid-cols-2 md:grid-cols-4">
                        @foreach ($otherPrograms as $other)
                            <div class="border border-[#242426] bg-[#101012] p-4 transition-colors hover:border-[#c32720]">
                                <div class="aspect-square w-full overflow-hidden bg-black mb-3">
                                    <img src="{{ $other->cover_url ?: asset('assets/lucille/logo.png') }}" alt="{{ $other->nombre }}" class="h-full w-full object-cover">
                                </div>
                                <h4 class="font-display text-sm uppercase tracking-wider text-[#dcdcdc] line-clamp-1">{{ $other->nombre }}</h4>
                                <p class="text-[11px] text-[#7b7b7b] line-clamp-1">{{ $other->conductor ?: 'Seven Rock Radio' }}</p>
                                <a href="{{ route('programs.detail', $other->archive_identifier ?: $other->publicSlug()) }}" class="mt-3 inline-block text-[10px] uppercase tracking-[.18em] text-[#c32720] hover:underline">Ver programa →</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>
</x-layouts.site>
