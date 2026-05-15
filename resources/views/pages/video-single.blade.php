<x-layouts.site title="Seven Rock Radio - {{ $video['title'] }}">
    <x-sections.page-heading
        :title="$video['title']"
        :subtitle="$video['artist']"
        :image="$video['image']"
        overlay="rgba(29,29,29,.85)"
        :categories="$video['categories']"
    />

    <section>
        <div class="lucille-content-box">
            <div class="relative aspect-video w-full overflow-hidden bg-[#111]">
                <iframe
                    src="{{ $video['embed'] }}"
                    title="{{ $video['title'] }}"
                    class="absolute inset-0 h-full w-full"
                    allowfullscreen
                ></iframe>
            </div>

            <div class="mt-8 space-y-5 text-[14px] leading-[26px] text-[#7b7b7b]">
                @foreach ($video['content'] as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </div>

            <div class="mt-9 flex items-center gap-4 text-[#757575]">
                <span class="font-display text-sm uppercase tracking-[.08em] text-[#dcdcdc]">Share:</span>
                <a href="#" class="transition duration-300 hover:text-lucille-accent">Twitter</a>
                <a href="#" class="transition duration-300 hover:text-lucille-accent">Facebook</a>
                <a href="#" class="transition duration-300 hover:text-lucille-accent">Pinterest</a>
            </div>
        </div>
    </section>
</x-layouts.site>
