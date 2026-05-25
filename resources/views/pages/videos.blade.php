<x-layouts.site title="Seven Rock Radio - Videos">
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
                        @php $talent = $media->talent; @endphp
                        <div class="lucille-video-card group cursor-default">
                            <div class="lucille-video-thumb relative aspect-video overflow-hidden bg-cover bg-center" style="background-image: url('{{ $media->url }}');">
                                <div class="absolute inset-0 z-10 flex items-center justify-center">
                                    <span class="lucille-video-play flex items-center justify-center pl-1 font-display text-2xl">▶</span>
                                </div>
                            </div>
                            <h3 class="my-[15px] font-display text-[26px] font-normal uppercase leading-[1.35] tracking-[2px] text-white transition duration-300 group-hover:text-lucille-accent">
                                {{ $media->title ?? $media->filename }}
                            </h3>
                            @if ($talent)
                                <a href="{{ route('talents.show', ['bandName' => $talent->band_name]) }}" class="text-xs uppercase tracking-[.15em] text-[#7b7b7b] transition hover:text-lucille-accent">
                                    {{ $talent->band_name }}
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-layouts.site>