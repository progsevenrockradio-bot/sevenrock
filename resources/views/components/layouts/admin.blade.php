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
    x-data="adminConfirmDialog()"
    x-on:keydown.escape.window="close()"
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

    <template x-if="open">
        <div
            x-cloak
            x-transition.opacity.duration.150ms
            class="fixed inset-0 z-[200] flex items-center justify-center p-4"
            aria-hidden="true"
        >
            <button
                type="button"
                class="absolute inset-0 cursor-default bg-[rgba(0,0,0,.78)] backdrop-blur-md"
                @click="close()"
                aria-label="Cerrar confirmación"
            ></button>

            <section
                role="dialog"
                aria-modal="true"
                aria-labelledby="admin-confirm-title"
                class="relative w-full max-w-lg overflow-hidden border border-[rgba(220,220,220,.16)] bg-[rgba(12,12,13,.96)] shadow-[0_30px_90px_rgba(0,0,0,.72)]"
            >
                <div class="h-1 w-full" :class="tone === 'danger' ? 'bg-[#c32720]' : 'bg-[var(--color-lucille-accent)]'"></div>

                <div class="relative p-6 sm:p-7">
                    <div class="flex items-start gap-4">
                        <div
                            class="mt-1 flex h-12 w-12 shrink-0 items-center justify-center border text-sm font-bold uppercase tracking-[.22em]"
                            :class="tone === 'danger'
                                ? 'border-[#5c2a2a] bg-[rgba(195,39,32,.12)] text-[#ffd0d0]'
                                : 'border-[rgba(220,220,220,.16)] bg-[rgba(255,255,255,.03)] text-[#dcdcdc]'"
                        >
                            !
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="font-display text-[10px] uppercase tracking-[.32em] text-[#8a8a8a]" x-text="tone === 'danger' ? 'Acción destructiva' : 'Confirmación'"></p>
                            <h2 id="admin-confirm-title" class="mt-2 font-display text-2xl uppercase tracking-[.08em] text-[#f2f2f2]" x-text="title"></h2>
                            <div class="mt-3 inline-flex items-center gap-2 border border-[rgba(220,220,220,.16)] bg-[rgba(255,255,255,.03)] px-3 py-1 text-[10px] uppercase tracking-[.22em] text-[#9d9d9d]">
                                <span>Método</span>
                                <span x-text="method"></span>
                            </div>
                            <p class="mt-3 text-sm leading-7 text-[#c3c3c3]" x-text="message"></p>
                        </div>
                    </div>

                    <div class="mt-7 flex flex-wrap justify-end gap-3">
                        <button type="button" class="lucille-button" @click="close()" x-text="cancelLabel"></button>
                        <button type="button" class="lucille-button-solid" @click="confirm()" x-text="confirmLabel"></button>
                    </div>
                </div>
            </section>
        </div>
    </template>

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
