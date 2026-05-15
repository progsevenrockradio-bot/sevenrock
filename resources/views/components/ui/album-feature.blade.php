@props(['album', 'coverImage' => null])

@if ($album)
    @php $albumCover = $coverImage ?? $album->cover_image_url; @endphp
    <div class="mt-[60px] grid gap-10 lg:grid-cols-[minmax(320px,470px)_1fr] lg:gap-[30px]">
        <a href="{{ route('discography') }}" class="group block overflow-hidden bg-[#1d1d1d]">
            <img src="{{ $albumCover }}" alt="{{ $album->title }}" class="w-full transition duration-500 ease-out group-hover:scale-[1.025] group-hover:opacity-90">
        </a>

        <div>
            <div class="space-y-[2px]">
                @foreach ($album->tracks ?? [] as $index => $track)
                    <div class="group/track bg-[#222] px-5 py-4 transition duration-300 hover:bg-[#272727]">
                        <div class="mb-3 text-[#7b7b7b] transition duration-300 group-hover/track:text-[#dcdcdc]">
                            <span class="mr-1">{{ $index + 1 }}.</span>{{ $track['title'] }}
                        </div>
                        <div class="lucille-audio-line">
                            <span class="text-xl leading-none">▶</span>
                            <span>00:00</span>
                            <span class="lucille-audio-track"></span>
                            <span>00:00</span>
                            <span class="text-xl leading-none">⌕</span>
                            <span class="lucille-audio-volume"></span>
                        </div>
                    </div>
                @endforeach
            </div>

            <p class="mt-8 max-w-2xl text-[14px] leading-[26px] text-[#7b7b7b]">{{ $album->summary }}</p>

            <div class="mt-8 flex flex-wrap gap-3">
                @foreach ($album->buy_links ?? [] as $link)
                    <a href="{{ $link['url'] }}" target="_blank" rel="noreferrer" class="lucille-button">{{ $link['label'] }}</a>
                @endforeach
            </div>
        </div>
    </div>
@endif
