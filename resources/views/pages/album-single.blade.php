<x-layouts.site title="Seven Rock Radio - {{ $album['title'] }}">
    <x-sections.page-heading
        :title="$album['title']"
        :subtitle="$album['artist']"
        image="assets/lucille/album1.jpg"
        overlay="rgba(21,21,21,.86)"
        :categories="$album['categories']"
    />

    <section>
        <div class="lucille-content-box">
            <div class="grid gap-8 lg:grid-cols-[40%_60%]">
                <aside class="lg:pr-[15px]">
                    <img src="{{ asset($album['cover']) }}" alt="{{ $album['title'] }}" class="w-full max-w-[500px]">

                    <div class="mt-[15px] space-y-[10px] text-[#7b7b7b]">
                        <p><span class="mr-2 text-[#dcdcdc]">Calendar:</span>{{ $album['date'] }}</p>
                        <p><span class="mr-2 text-[#dcdcdc]">Artist:</span>{{ $album['artist'] }}</p>
                        <p><span class="mr-2 text-[#dcdcdc]">Label:</span>{{ $album['label'] }}</p>
                        <p><span class="mr-2 text-[#dcdcdc]">Producer:</span>{{ $album['producer'] }}</p>
                        <p><span class="mr-2 text-[#dcdcdc]">Number of discs:</span>{{ $album['discs'] }}</p>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-2">
                        @foreach ($album['buttons'] as $button)
                            <a href="{{ $button['url'] }}" target="_blank" rel="noreferrer" class="lucille-button min-h-[32px] px-[13px] text-[10px] tracking-[2px]">{{ $button['label'] }}</a>
                        @endforeach
                    </div>
                </aside>

                <div class="lg:pl-[15px]">
                    <div class="space-y-[2px]">
                        @foreach ($album['tracks'] as $track)
                            <div class="bg-[#222] px-5 py-3">
                                <div class="mb-[3px] pl-[7px] text-[#dcdcdc]">
                                    <span class="mr-[5px]">{{ $loop->iteration }}.</span>{{ $track['title'] }}
                                </div>
                                <div class="h-10 rounded-sm border border-[#2b2b2b] bg-[#191919]">
                                    <div class="flex h-full items-center gap-3 px-3 text-xs text-[#7b7b7b]">
                                        <span class="text-lg text-[#dcdcdc]">▶</span>
                                        <span class="h-px flex-1 bg-[#3a3a3a]"><span class="block h-px w-1/3 bg-lucille-accent"></span></span>
                                        <span>00:00</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-8 space-y-5 text-[14px] leading-[26px] text-[#7b7b7b]">
                        @foreach ($album['content'] as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.site>
