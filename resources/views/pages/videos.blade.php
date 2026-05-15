<x-layouts.site title="Seven Rock Radio - Videos">
    <x-sections.page-heading
        title="Videos"
        image="assets/lucille/pedalboard-1511069_1920.jpg"
        overlay="rgba(19,19,19,.91)"
    />

    <section>
        <div class="lucille-content-box">
            <div class="grid gap-y-5 md:grid-cols-2 md:gap-x-[2%]">
                @foreach ($videos as $video)
                    <a href="{{ route('videos.single', ['slug' => $video->slug]) }}" class="lucille-video-card group">
                        <div class="lucille-video-thumb relative aspect-video overflow-hidden bg-cover bg-center" style="background-image: url('{{ $video->image_url }}');">
                            <div class="absolute inset-0 z-10 flex items-center justify-center">
                                <span class="lucille-video-play flex items-center justify-center pl-1 font-display text-2xl">▶</span>
                            </div>
                        </div>
                        <h3 class="my-[15px] font-display text-[26px] font-normal uppercase leading-[1.35] tracking-[2px] text-white transition duration-300 group-hover:text-lucille-accent">{{ $video->title }}</h3>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
</x-layouts.site>
