<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>{{ $themeAppearance['site_name'] ?? 'Seven Rock Radio' }} - Player</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ $themeAppearance['google_fonts_url'] }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Bebas+Neue&family=Dancing+Script:wght@700&family=Orbitron:wght@700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased overflow-hidden" style="
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
    <div class="lucille-fixed-bg" aria-hidden="true"></div>
    <main class="min-h-screen" style="overflow:hidden;">
        <x-radio.player mode="popup" />
    </main>
</body>
</html>
