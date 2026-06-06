@props(['video', 'image' => null])

@if ($video)
    @php
        $backgroundImage = $image
            ?? \App\Support\PublicMediaUrl::normalizePublicUrl(data_get($video, 'image'))
            ?? data_get($video, 'image_url')
            ?? asset('assets/lucille/man-597179_1920.jpg');
    @endphp
    @php $ui = $themeAppearance['ui_texts']; @endphp
    <section class="relative bg-cover bg-center bg-no-repeat lg:bg-fixed" style="background-image: url('{{ $backgroundImage }}');">
        <div class="absolute inset-0 bg-[rgba(21,21,21,.88)]"></div>
        <div class="relative mx-auto max-w-[898px] px-6 py-[90px] text-center lg:px-8">
            <x-ui.section-heading :title="$ui['featured_video']" :subtitle="data_get($video, 'title', '')" />

            <a href="{{ data_get($video, 'youtube_url', '#') }}" target="_blank" rel="noreferrer" class="lucille-video-card group mt-[60px] block overflow-hidden">
                <div class="lucille-video-thumb relative aspect-video bg-cover bg-center" style="background-image: url('{{ $backgroundImage }}');">
                    <div class="absolute inset-0 z-10 flex items-center justify-center">
                        <span class="lucille-video-play flex h-[84px] w-[84px] items-center justify-center pl-1 text-3xl">
                            ▶
                        </span>
                    </div>
                </div>
            </a>
        </div>
    </section>
@endif
