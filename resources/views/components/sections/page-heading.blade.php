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
            $pageHeadingImage = str_starts_with($image, 'http://') || str_starts_with($image, 'https://')
                ? $image
                : asset($image);
        @endphp
        <div class="absolute inset-0 lucille-card-image" style="background-image: url('{{ $pageHeadingImage }}');"></div>
    @endif
    <div class="absolute inset-0" style="background: {{ $overlay }};"></div>

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
                <a href="#">{{ $category }}</a>@if (! $loop->last) <span class="px-1 text-[#757575]"> </span>@endif
            @endforeach
        </div>
    @endif
</section>
