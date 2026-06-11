@props([
    'title',
    'subtitle' => null,
    'image' => null,
    'overlay' => 'rgba(21,21,21,.88)',
    'categories' => [],
])

<section class="lucille-page-heading {{ $subtitle ? '' : 'no-subtitle' }} {{ ! empty($categories) ? 'has-cpt-tax' : '' }}" style="overflow: visible;">
    @if ($image)
        @php
            $pageHeadingImage = \App\Support\PublicMediaUrl::normalizePublicUrl($image)
                ?: (str_starts_with((string) $image, 'http://') || str_starts_with((string) $image, 'https://')
                    ? (string) $image
                    : asset((string) $image));
        @endphp
        <div class="absolute inset-0 lucille-card-image" style="background-image: url('{{ $pageHeadingImage }}');"></div>
        <div class="absolute inset-0" style="background: {{ $overlay === 'rgba(21,21,21,.88)' ? 'rgba(16, 16, 18, 0.75)' : $overlay }};"></div>
    @else
        <div class="absolute inset-0" style="background: rgba(16, 16, 18, 0.75);"></div>
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

    <!-- Blood Drip Border hanging from the bottom of the heading (proportional, non-squished) -->
    <div style="position: absolute; bottom: -63px; left: 0; right: 0; height: 64px; background: url('{{ asset('assets/lucille/blood_drip.png') }}') repeat-x top center; background-size: 80px 64px; pointer-events: none; opacity: 0.9; z-index: 15;"></div>
</section>
