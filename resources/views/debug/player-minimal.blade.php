@php
    $themeAppearance = $themeAppearance ?? \App\Support\ThemeAppearance::resolved();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Debug Player Minimal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ $themeAppearance['google_fonts_url'] }}" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased" style="
    margin:0;
    background: #050505;
    color: #f4f1ea;
    --lucille-accent: {{ $themeAppearance['visual']['accent_color'] }};
    --lucille-nav: {{ $themeAppearance['visual']['nav_color'] }};
    --lucille-surface: {{ $themeAppearance['visual']['surface_color'] }};
    --lucille-body: {{ $themeAppearance['visual']['body_color'] }};
    --lucille-heading: {{ $themeAppearance['visual']['heading_color'] }};
    --lucille-line: {{ $themeAppearance['visual']['line_color'] }};
    --lucille-body-font: '{{ $themeAppearance['visual']['body_font'] }}';
    --lucille-heading-font: '{{ $themeAppearance['visual']['heading_font'] }}';
    --lucille-brand-font: '{{ $themeAppearance['visual']['brand_mark_font'] }}';
    --lucille-bg-image: url('{{ $themeAppearance['background_url'] }}');
">
    <main style="min-height:100vh;">
        <x-radio.player mode="popup" />
    </main>
</body>
</html>
