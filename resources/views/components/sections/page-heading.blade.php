@props([
    'title',
    'subtitle' => null,
    'image' => null,
    'overlay' => 'rgba(21,21,21,.88)',
    'categories' => [],
])

<section class="lucille-page-heading {{ $subtitle ? '' : 'no-subtitle' }} {{ ! empty($categories) ? 'has-cpt-tax' : '' }}">
    @if ($image)
        @php
            $pageHeadingImage = \App\Support\PublicMediaUrl::normalizePublicUrl($image)
                ?: (str_starts_with((string) $image, 'http://') || str_starts_with((string) $image, 'https://')
                    ? (string) $image
                    : asset((string) $image));
        @endphp
        <div class="absolute inset-0 hero-slide" style="background-image: url('{{ $pageHeadingImage }}');">
            <img src="{{ $pageHeadingImage }}" alt="Background" class="hero-slide-img" aria-hidden="true">
        </div>
        <div class="absolute inset-0" style="background: {{ $overlay === 'rgba(21,21,21,.88)' ? 'rgba(16, 16, 18, 0.75)' : $overlay }}; z-index: 2;"></div>
    @else
        <div class="absolute inset-0" style="background: rgba(16, 16, 18, 0.75); z-index: 2;"></div>
    @endif

    <div class="lucille-page-heading-inner">
        <h1 class="lucille-page-title">{!! formatear_titulo($title) !!}</h1>
        @if ($subtitle)
            <h2 class="lucille-page-subtitle">{{ $subtitle }}</h2>
        @endif
        @if ($slot->isNotEmpty())
            <div class="mt-5 text-sm italic text-[#757575]">
                {{ $slot }}
            </div>
        @endif
    </div>

    @if (! empty($categories))
        <div class="lucille-cpt-meta">
            @foreach ($categories as $category)
                <a href="#">{{ data_get($category, 'label', $category) }}</a>@if (! $loop->last) <span class="px-1 text-[#757575]"> </span>@endif
            @endforeach
        </div>
    @endif
</section>
