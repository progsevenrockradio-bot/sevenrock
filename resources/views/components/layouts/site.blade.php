@props(['title' => 'Seven Rock Radio'])

@php
    $theme = $themeAppearance;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="Seven Rock Radio, a Laravel Blade recreation of the Lucille Rocks atmosphere.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ $theme['google_fonts_url'] }}" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="antialiased"
    style="
        --lucille-accent: {{ $theme['visual']['accent_color'] }};
        --lucille-nav: {{ $theme['visual']['nav_color'] }};
        --lucille-surface: {{ $theme['visual']['surface_color'] }};
        --lucille-body: {{ $theme['visual']['body_color'] }};
        --lucille-heading: {{ $theme['visual']['heading_color'] }};
        --lucille-line: {{ $theme['visual']['line_color'] }};
        --lucille-body-font: '{{ $theme['brand_font'] }}';
        --lucille-heading-font: '{{ $theme['heading_font'] }}';
        --lucille-brand-font: '{{ $theme['brand_mark_font'] }}';
        --lucille-bg-image: url('{{ $theme['media']['background_url'] }}');
    "
>
    <div class="lucille-fixed-bg" aria-hidden="true"></div>

    <x-navigation.rocks-menu :theme="$theme" />

    <main class="pb-40">
        {{ $slot }}
    </main>

    <x-radio.player />

    <footer class="bg-lucille-surface py-7 text-center text-[13px] text-[#7b7b7b]">
        <div class="mx-auto flex max-w-[1180px] flex-col items-center gap-3 px-5">
            <div class="flex flex-wrap items-center justify-center gap-4">
                @foreach ($theme['social_links'] as $social)
                    <a href="{{ $social['url'] }}" target="_blank" rel="noreferrer" class="transition hover:text-lucille-accent">
                        {{ strtoupper($social['network']) }}
                    </a>
                @endforeach
            </div>
            <div>{{ $theme['site_name'] }} © Laravel Blade Edition</div>
        </div>
    </footer>
</body>
</html>
