@props(['videos' => collect(), 'image' => null])

@if ($videos->isNotEmpty())
    @php
        $ui = $themeAppearance['ui_texts'];
    @endphp
    <section id="featured-video-section" class="relative bg-cover bg-center bg-no-repeat overflow-hidden">
        <!-- Contenedores de Fondo Dinámicos -->
        <div id="video-bg-container" class="absolute inset-0 z-0">
            @foreach ($videos as $index => $video)
                @php
                    $videoImage = \App\Support\PublicMediaUrl::normalize($video->image)
                        ?? data_get($video, 'image_url')
                        ?? $image
                        ?? asset('assets/lucille/man-597179_1920.jpg');
                @endphp
                <div 
                    id="video-bg-{{ $index }}" 
                    class="absolute inset-0 bg-cover bg-center bg-no-repeat lg:bg-fixed transition-opacity duration-700 ease-in-out {{ $index === 0 ? 'opacity-100' : 'opacity-0' }}" 
                    style="background-image: url('{{ $videoImage }}');"
                ></div>
            @endforeach
        </div>

        <!-- Capa de oscurecimiento global -->
        <div class="absolute inset-0 bg-[rgba(21,21,21,.90)] z-10 pointer-events-none"></div>

        <!-- Contenido principal -->
        <div class="relative mx-auto px-6 py-[90px] text-center lg:px-8 z-20 {{ $videos->count() === 1 ? 'max-w-[898px]' : 'max-w-[1200px]' }}">
            <x-ui.section-heading :title="$ui['featured_video']" :subtitle="$videos->count() === 1 ? data_get($videos->first(), 'title', '') : ''" />

            <div class="mt-[60px] grid gap-8 {{ $videos->count() === 1 ? 'max-w-[700px] mx-auto grid-cols-1' : ($videos->count() === 2 ? 'max-w-[960px] mx-auto grid-cols-1 md:grid-cols-2' : 'grid-cols-1 md:grid-cols-3') }}">
                @foreach ($videos as $index => $video)
                    @php
                        $videoImage = \App\Support\PublicMediaUrl::normalize($video->image)
                            ?? data_get($video, 'image_url')
                            ?? $image
                            ?? asset('assets/lucille/man-597179_1920.jpg');
                    @endphp
                    <div class="flex flex-col">
                        <a 
                            href="{{ data_get($video, 'youtube_url', '#') }}" 
                            target="_blank" 
                            rel="noreferrer" 
                            class="lucille-video-card group block overflow-hidden"
                            onmouseenter="switchVideoBackground({{ $index }}, {{ $videos->count() }})"
                        >
                            <div class="lucille-video-thumb relative aspect-video bg-cover bg-center border border-[#2b2b2b]/40" style="background-image: url('{{ $videoImage }}');">
                                <div class="absolute inset-0 z-10 flex items-center justify-center">
                                    <span class="lucille-video-play flex items-center justify-center pl-1 {{ $videos->count() === 1 ? 'h-[84px] w-[84px] text-3xl' : 'h-[64px] w-[64px] text-2xl' }}">
                                        ▶
                                    </span>
                                </div>
                            </div>
                            @if ($videos->count() > 1)
                                <h4 class="mt-4 font-display text-[15px] uppercase tracking-[.08em] text-[#dcdcdc] line-clamp-2 transition-colors text-center">{!! formatear_titulo_hover($video->title) !!}</h4>
                            @endif
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    @if ($videos->count() > 1)
        <script>
            function switchVideoBackground(activeIndex, totalCount) {
                for (let i = 0; i < totalCount; i++) {
                    const bgEl = document.getElementById('video-bg-' + i);
                    if (bgEl) {
                        if (i === activeIndex) {
                            bgEl.classList.remove('opacity-0');
                            bgEl.classList.add('opacity-100');
                        } else {
                            bgEl.classList.remove('opacity-100');
                            bgEl.classList.add('opacity-0');
                        }
                    }
                }
            }
        </script>
    @endif
@endif
