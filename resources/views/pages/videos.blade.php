<x-layouts.site title="Seven Rock Radio - Videos" description="Videos musicales y contenido audiovisual de Seven Rock Radio. Los mejores videos de rock y metal.">
    <x-sections.page-heading
        title="Videos"
        subtitle="Videos de nuestros talentos"
        image="assets/lucille/pedalboard-1511069_1920.jpg"
        overlay="rgba(19,19,19,.91)"
    />

    <section>
        <div class="lucille-content-box">
            @if ($videos->isEmpty())
                <div class="py-16 text-center text-sm text-[#7b7b7b]">
                    No hay videos publicados todavía.
                </div>
            @else
                <div class="grid gap-y-5 md:grid-cols-2 md:gap-x-[2%]">
                    @foreach ($videos as $media)
                        @php
                            $talent = data_get($media, 'talent');
                            $videoUrl = \App\Support\PublicMediaUrl::normalizePublicUrl(data_get($media, 'url'))
                                ?: data_get($media, 'url', '');
                        @endphp
                        <div class="lucille-video-card group cursor-default">
                            <div class="lucille-video-thumb relative aspect-video overflow-hidden bg-cover bg-center" style="background-image: url('{{ $videoUrl }}');">
                                <div class="absolute inset-0 z-10 flex items-center justify-center">
                                    <span class="lucille-video-play flex items-center justify-center pl-1 font-display text-2xl">▶</span>
                                </div>
                            </div>
                            <h3 class="my-[15px] font-display text-[26px] font-normal uppercase leading-[1.35] tracking-[2px] text-white transition duration-300 group-hover:text-lucille-accent">
                                {{ data_get($media, 'title', data_get($media, 'filename', '')) }}
                            </h3>
                            @if ($talent)
                                <a href="{{ "#" }}" class="text-xs uppercase tracking-[.15em] text-[#7b7b7b] transition hover:text-lucille-accent">
                                    {{ data_get($talent, 'band_name', '') }}
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-layouts.site>
