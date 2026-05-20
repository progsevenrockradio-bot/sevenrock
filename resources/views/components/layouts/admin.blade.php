@props(['title' => 'Seven Rock Radio Admin'])

@php
    $theme = $themeSettings ?? \App\Models\ThemeSetting::current();
    $themeAppearance = $themeAppearance ?? \App\Support\ThemeAppearance::resolved();
    $admin = $themeAppearance['admin_texts'] ?? [];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ $theme->google_fonts_url }}" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="antialiased"
    style="
        --lucille-accent: {{ $theme->accent_color }};
        --lucille-nav: {{ $theme->nav_color }};
        --lucille-surface: {{ $theme->surface_color }};
        --lucille-body: {{ $theme->body_color }};
        --lucille-heading: {{ $theme->heading_color }};
        --lucille-line: {{ $theme->line_color }};
        --lucille-body-font: '{{ $theme->body_font }}';
        --lucille-heading-font: '{{ $theme->heading_font }}';
        --lucille-brand-font: '{{ $theme->brand_mark_font }}';
        --lucille-bg-image: url('{{ $theme->background_url }}');
    "
>
    <div class="lucille-fixed-bg" aria-hidden="true"></div>

    @php
        $brandDisplayMode = $theme->brand_display_mode ?? 'mark';
    @endphp

    <header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
            @if ($brandDisplayMode === 'logo')
                <img src="{{ $theme->logo_url }}" alt="{{ $theme->site_name }}" class="h-10 w-auto">
            @else
                <span class="lucille-brand-mark text-[1.9rem]">{{ $theme->brand_mark ?: $theme->site_name }}</span>
            @endif
            <span class="rounded border border-[#2b2b2b] px-3 py-1 font-display text-xs uppercase tracking-[.18em] text-[#dcdcdc]">
                {{ $admin['admin_suffix'] ?? 'Admin' }}
            </span>
        </a>

        <div class="flex items-center gap-3">
            <a href="{{ route('home') }}" class="lucille-button">{{ $admin['view_site'] }}</a>
            @auth
                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="lucille-button-solid">{{ $admin['logout'] }}</button>
                </form>
            @endauth
        </div>
    </header>

    <div class="mx-auto max-w-6xl px-6 pb-4">
        <nav class="lucille-admin-dropdowns" aria-label="Admin sections">
            <details class="lucille-admin-dropdown">
                <summary class="lucille-admin-dropdown-summary">
                    <span>Site</span>
                    <span class="lucille-admin-dropdown-caret" aria-hidden="true">▾</span>
                </summary>
                <div class="lucille-admin-dropdown-panel">
                    <a href="{{ route('admin.dashboard') }}" class="lucille-admin-link">{{ $admin['dashboard_heading'] ?? 'Dashboard' }}</a>
                    <a href="{{ route('admin.posts.index') }}" class="lucille-admin-link">{{ $admin['posts_heading'] ?? 'Posts' }}</a>
                    <a href="{{ route('admin.events.index') }}" class="lucille-admin-link">{{ $admin['events_heading'] ?? 'Events' }}</a>
                    <a href="{{ route('admin.events.single') }}" class="lucille-admin-link">Single Event</a>
                    <a href="{{ route('admin.audit-logs.index') }}" class="lucille-admin-link">Audit trail</a>
                </div>
            </details>

            <details class="lucille-admin-dropdown">
                <summary class="lucille-admin-dropdown-summary">
                    <span>Programas</span>
                    <span class="lucille-admin-dropdown-caret" aria-hidden="true">▾</span>
                </summary>
                <div class="lucille-admin-dropdown-panel">
                    <a href="{{ route('admin.master-programs.index') }}" class="lucille-admin-link">{{ $admin['master_programs_heading'] ?? 'Master Programs' }}</a>
                    <a href="{{ route('admin.podcast-uploads.index') }}" class="lucille-admin-link">{{ $admin['podcast_uploads_heading'] ?? 'Podcast Uploads' }}</a>
                    <a href="{{ route('admin.songs.index') }}" class="lucille-admin-link">{{ $admin['songs_heading'] ?? 'Songs' }}</a>
                </div>
            </details>

            <details class="lucille-admin-dropdown">
                <summary class="lucille-admin-dropdown-summary">
                    <span>Bandas</span>
                    <span class="lucille-admin-dropdown-caret" aria-hidden="true">▾</span>
                </summary>
                <div class="lucille-admin-dropdown-panel">
                    <a href="{{ route('admin.band-profiles.index') }}" class="lucille-admin-link">{{ $admin['bands_heading'] ?? 'Band Profiles' }}</a>
                    <a href="{{ route('admin.albums.index') }}" class="lucille-admin-link">{{ $admin['albums_heading'] ?? 'Albums' }}</a>
                    <a href="{{ route('admin.videos.index') }}" class="lucille-admin-link">{{ $admin['videos_heading'] ?? 'Videos' }}</a>
                    <a href="{{ route('admin.gallery.index') }}" class="lucille-admin-link">{{ $admin['gallery_heading'] ?? 'Gallery' }}</a>
                </div>
            </details>
        </nav>
    </div>

    <main class="mx-auto max-w-6xl px-6 pb-16">
        {{ $slot }}
    </main>
</body>
</html>
