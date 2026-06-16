@props(['title' => 'Seven Rock Radio Admin'])

@php
    $theme = $themeSettings ?? \App\Models\ThemeSetting::current();
    $themeAppearance = $themeAppearance ?? \App\Support\ThemeAppearance::resolved();
    $admin = $themeAppearance['admin_texts'] ?? [];
    $adminHomeUrl = auth()->check() ? route('admin.dashboard') : route('admin.login');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ $theme->logo_url }}">
    <link rel="apple-touch-icon" href="{{ $theme->logo_url }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ $theme->google_fonts_url }}" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
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
        }
    </style>
</head>
<body
    class="antialiased"
    x-data="{ showHelp: false }"
    @keydown.window.escape="showHelp = false"
    >
    <div class="lucille-fixed-bg" aria-hidden="true"></div>

    <div id="admin-confirm-modal" class="fixed inset-0 z-[200] hidden" aria-hidden="true">
        <button
            id="admin-confirm-backdrop"
            type="button"
            class="absolute inset-0 cursor-default bg-[rgba(0,0,0,.78)] backdrop-blur-md"
            aria-label="Cerrar confirmación"
        ></button>

        <section
            role="dialog"
            aria-modal="true"
            aria-labelledby="admin-confirm-title"
            class="absolute left-1/2 top-1/2 w-[min(92vw,24rem)] -translate-x-1/2 -translate-y-1/2 overflow-hidden border border-[rgba(220,220,220,.16)] bg-[rgba(12,12,13,.96)] shadow-[0_30px_90px_rgba(0,0,0,.72)]"
        >
            <div id="admin-confirm-tone" class="h-1 w-full bg-[#c32720]"></div>

            <div class="relative p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <div
                        id="admin-confirm-icon"
                        class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center border text-[10px] font-bold uppercase tracking-[.22em] border-[#5c2a2a] bg-[rgba(195,39,32,.12)] text-[#ffd0d0]"
                    >
                        !
                    </div>

                    <div class="min-w-0 flex-1">
                        <p id="admin-confirm-kicker" class="font-display text-[9px] uppercase tracking-[.28em] text-[#8a8a8a]">Acción destructiva</p>
                        <h2 id="admin-confirm-title" class="mt-1 font-display text-[1.45rem] uppercase tracking-[.06em] text-[#f2f2f2]"></h2>
                        <div class="mt-2 inline-flex items-center gap-2 border border-[rgba(220,220,220,.16)] bg-[rgba(255,255,255,.03)] px-2.5 py-1 text-[9px] uppercase tracking-[.22em] text-[#9d9d9d]">
                            <span>Método</span>
                            <span id="admin-confirm-method"></span>
                        </div>
                        <p id="admin-confirm-message" class="mt-2 text-[13px] leading-6 text-[#c3c3c3]"></p>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <button type="button" id="admin-confirm-cancel" class="lucille-button">Cancelar</button>
                    <button type="button" id="admin-confirm-accept" class="lucille-button-solid">Confirmar</button>
                </div>
            </div>
        </section>
    </div>

    @php
        $brandDisplayMode = $theme->brand_display_mode ?? 'mark';
    @endphp

    <!-- Omnibar Modal (Command Palette) -->
    @auth
    <div id="admin-omnibar-modal" class="fixed inset-0 z-[200] hidden items-start justify-center pt-[15vh] px-4" aria-hidden="true">
        <button
            id="admin-omnibar-backdrop"
            type="button"
            class="absolute inset-0 cursor-default bg-[rgba(0,0,0,.85)] backdrop-blur-sm"
            aria-label="Cerrar buscador"
        ></button>

        <div class="relative w-full max-w-lg overflow-hidden border border-[rgba(220,220,220,.12)] bg-[rgba(12,12,13,.98)] shadow-[0_30px_90px_rgba(0,0,0,.85)] flex flex-col max-h-[60vh] rounded-lg">
            <div class="flex items-center gap-3 px-4 border-b border-[rgba(220,220,220,.08)]">
                <svg class="h-5 w-5 text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input
                    type="text"
                    id="admin-omnibar-input"
                    placeholder="Buscar sección o herramienta..."
                    class="w-full py-4 bg-transparent border-none outline-none text-white text-sm placeholder-gray-500 focus:ring-0 focus:outline-none"
                    autocomplete="off"
                    spellcheck="false"
                >
                <button type="button" id="admin-omnibar-close-btn" class="text-[10px] text-gray-400 hover:text-white px-2 py-0.5 border border-gray-700 rounded transition-colors">ESC</button>
            </div>

            <div id="admin-omnibar-results" class="flex-1 overflow-y-auto p-2 space-y-1 min-h-[150px] max-h-[350px]">
                <!-- Results rendered via JS -->
            </div>
            
            <div class="p-3 bg-[rgba(255,255,255,.01)] border-t border-[rgba(220,220,220,.06)] flex justify-between text-[9px] text-gray-500 uppercase tracking-wider">
                <span>↑↓ para navegar</span>
                <span>Enter para seleccionar</span>
            </div>
        </div>
    </div>
    @endauth

    @auth
    <div class="flex min-h-screen">
        <!-- Sidebar Navigation -->
        <aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-[rgba(220,220,220,.08)] bg-[rgba(12,12,13,.95)] backdrop-blur-md transition-transform duration-300 md:translate-x-0">
            <!-- Brand -->
            <div class="flex h-20 items-center justify-between px-6 border-b border-[rgba(220,220,220,.08)]">
                <a href="{{ $adminHomeUrl }}" class="flex items-center gap-3">
                    @if ($brandDisplayMode === 'logo' || $brandDisplayMode === 'both')
                        <img src="{{ $theme->logo_url }}" alt="{{ $theme->site_name }}" loading="lazy" class="h-8 w-auto">
                    @else
                        <span class="lucille-brand-mark text-[1.4rem]">{{ $theme->brand_mark ?: $theme->site_name }}</span>
                    @endif
                    <span class="rounded border border-[#2b2b2b] px-2 py-0.5 font-display text-[9px] uppercase tracking-[.18em] text-[#dcdcdc]">
                        {{ $admin['admin_suffix'] ?? 'Admin' }}
                    </span>
                </a>
                <button type="button" id="sidebar-close-btn" class="text-gray-400 hover:text-white md:hidden" aria-label="Cerrar menú">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Navigation Links -->
            <div class="flex-1 overflow-y-auto px-4 py-6 space-y-6">
                <!-- Group 1: General -->
                <div>
                    <h3 class="px-3 text-[10px] font-semibold uppercase tracking-[.2em] text-[#8a8a8a]">General</h3>
                    <ul class="mt-2 space-y-1">
                        <li>
                            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                <span>{{ $admin['dashboard_heading'] ?? 'Dashboard' }}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.posts.index') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.posts.*') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                                <span>{{ $admin['posts_heading'] ?? 'Posts' }}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.comments.index') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.comments.*') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                <span>Comentarios</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.submissions.index') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.submissions.*') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <span>Buzón de Maquetas</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.events.index') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.events.index') || request()->routeIs('admin.events.create') || request()->routeIs('admin.events.edit') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>{{ $admin['events_heading'] ?? 'Events' }}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.events.single') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.events.single') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span>Evento Único</span>
                            </a>
                        </li>
                        @role('Super Admin')
                        <li>
                            <a href="{{ route('admin.audit-logs.index') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.audit-logs.*') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span>Bitácora Auditoría</span>
                            </a>
                        </li>
                        @endrole
                    </ul>
                </div>

                <!-- Group 2: Programación -->
                <div>
                    <h3 class="px-3 text-[10px] font-semibold uppercase tracking-[.2em] text-[#8a8a8a]">Programación</h3>
                    <ul class="mt-2 space-y-1">
                        <li>
                            <a href="{{ route('admin.master-programs.index') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.master-programs.*') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>{{ $admin['master_programs_heading'] ?? 'Master Programs' }}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.podcast-uploads.index') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.podcast-uploads.*') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                <span>{{ $admin['podcast_uploads_heading'] ?? 'Podcast Uploads' }}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.page-countdowns.index') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.page-countdowns.*') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Páginas en Espera</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.songs.index') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.songs.*') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                                <span>{{ $admin['songs_heading'] ?? 'Songs' }}</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Group 3: Bandas -->
                <div>
                    <h3 class="px-3 text-[10px] font-semibold uppercase tracking-[.2em] text-[#8a8a8a]">Bandas</h3>
                    <ul class="mt-2 space-y-1">
                        <li>
                            <a href="{{ route('admin.albums.index') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.albums.*') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                <span>{{ $admin['albums_heading'] ?? 'Albums' }}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.videos.index') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.videos.*') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                <span>{{ $admin['videos_heading'] ?? 'Videos' }}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.gallery.index') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.gallery.*') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>{{ $admin['gallery_heading'] ?? 'Gallery' }}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.radio-artists.index') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.radio-artists.*') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                <span>Band profiles</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.new-releases.index') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.new-releases.*') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                                <span>Nuevos Lanzamientos</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Group 4: Convocatorias & Talento -->
                <div>
                    <h3 class="px-3 text-[10px] font-semibold uppercase tracking-[.2em] text-[#8a8a8a]">Talento & Conv.</h3>
                    <ul class="mt-2 space-y-1">
                        <li>
                            <a href="{{ route('admin.talents.index') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.talents.index') || request()->routeIs('admin.talents.edit') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <span>Talentos</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.talents.media') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.talents.media') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>Media de Talentos</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.programs.index') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.programs.*') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                                <span>🎙️ Programas Conv.</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.outreach.index') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.outreach.*') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <span>Outreach</span>
                            </a>
                        </li>
                        @if (auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Admin'))
                            <a href="{{ route('admin.marketing.index') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.marketing.*') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                                <span>Email Marketing</span>
                            </a>
                        @endif

                        @if (auth()->user()->hasRole('Super Admin'))
                            <a href="{{ route('admin.email-templates.index') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.email-templates.*') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <span>Plantillas Correo</span>
                            </a>

                            <a href="{{ route('admin.email-logs.index') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.email-logs.*') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span>Historial Correos</span>
                            </a>

                            <a href="{{ url('/log-viewer') }}" target="_blank" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                <span>Visor de Logs</span>
                            </a>

                            <div class="my-4 border-t border-[rgba(255,255,255,.05)]"></div>
                        @endif
                    </ul>
                </div>

                <!-- Group 5: Sistema -->
                @role('Super Admin')
                <div>
                    <h3 class="px-3 text-[10px] font-semibold uppercase tracking-[.2em] text-[#8a8a8a]">Sistema</h3>
                    <ul class="mt-2 space-y-1">
                        <li>
                            <a href="{{ route('admin.settings.edit') }}" class="flex items-center gap-3 px-3 py-2 text-xs rounded transition-colors {{ request()->routeIs('admin.settings.*') ? 'bg-[rgba(255,255,255,.05)] text-[var(--lucille-accent)] font-medium border-l-2 border-[var(--lucille-accent)]' : 'text-gray-300 hover:bg-[rgba(255,255,255,.02)] hover:text-white' }}">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span>Configuración</span>
                            </a>
                        </li>
                    </ul>
                </div>
                @endrole
            </div>

            <!-- Footer info / Logout button -->
            <div class="p-4 border-t border-[rgba(220,220,220,.08)] flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-white truncate">{{ auth()->user()->name }}</p>
                    <p class="text-[10px] text-gray-400 truncate">{{ auth()->user()->email }}</p>
                </div>
                <form action="{{ route('admin.logout') }}" method="POST" class="shrink-0">
                    @csrf
                    <button type="submit" class="p-1.5 text-gray-400 hover:text-white rounded hover:bg-[rgba(255,255,255,.05)]" title="Cerrar sesión">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content Wrapper -->
        <div class="flex-1 md:pl-64 flex flex-col min-w-0">
            <!-- Header bar -->
            <header class="flex h-20 items-center justify-between px-6 border-b border-[rgba(220,220,220,.08)] bg-[rgba(12,12,13,.35)] backdrop-blur-md sticky top-0 z-40">
                <div class="flex items-center gap-3">
                    <!-- Hamburger for mobile -->
                    <button type="button" id="sidebar-open-btn" class="text-gray-400 hover:text-white md:hidden" aria-label="Abrir menú">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <!-- Breadcrumbs -->
                    <nav class="hidden sm:flex items-center space-x-2 text-[10px] text-gray-400 uppercase tracking-widest">
                        <a href="{{ route('admin.dashboard') }}" class="hover:text-white transition-colors">Admin</a>
                        <span class="text-gray-600">/</span>
                        <span class="text-white font-medium truncate max-w-[200px]">
                            {{ $title }}
                        </span>
                    </nav>
                </div>

                <div class="flex items-center gap-4">
                    <!-- Omnibar Search Button -->
                    <button type="button" id="omnibar-trigger-btn" class="flex items-center gap-2 px-3 py-1.5 rounded border border-[rgba(220,220,220,.12)] bg-[rgba(255,255,255,.02)] text-xs text-gray-400 hover:text-white hover:border-gray-500 transition-colors" title="Buscar sección (Ctrl + K)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <span class="hidden md:inline">Buscar sección...</span>
                        <kbd class="hidden md:inline-flex px-1.5 py-0.5 text-[9px] bg-gray-800 border border-gray-700 rounded text-gray-500 font-sans font-normal leading-3">Ctrl+K</kbd>
                    </button>

                    <!-- Help Button -->
                    <button
                        type="button"
                        @click="showHelp = true"
                        class="flex items-center gap-2 px-3 py-1.5 rounded border border-[rgba(220,220,220,.12)] bg-[rgba(255,255,255,.02)] text-xs text-gray-400 hover:text-white hover:border-gray-500 transition-colors"
                        title="Ver ayuda de esta sección"
                    >
                        <span>💡</span>
                        <span class="hidden sm:inline">Manual</span>
                    </button>

                    <a href="{{ route('home') }}" class="lucille-button py-1.5 text-xs">{{ $admin['view_site'] }}</a>
                </div>
            </header>

            <!-- Content Area -->
            <main class="flex-1 p-6 md:p-8 max-w-6xl w-full mx-auto pb-16">
                {{ $slot }}
            </main>
        </div>
    </div>
    @else
        <!-- Guest View (Login, etc) -->
        <header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
            <a href="{{ $adminHomeUrl }}" class="flex items-center gap-3">
                @if ($brandDisplayMode === 'logo' || $brandDisplayMode === 'both')
                    <img src="{{ $theme->logo_url }}" alt="{{ $theme->site_name }}" loading="lazy" class="h-10 w-auto">
                @else
                    <span class="lucille-brand-mark text-[1.9rem]">{{ $theme->brand_mark ?: $theme->site_name }}</span>
                @endif
                <span class="rounded border border-[#2b2b2b] px-3 py-1 font-display text-xs uppercase tracking-[.18em] text-[#dcdcdc]">
                    {{ $admin['admin_suffix'] ?? 'Admin' }}
                </span>
            </a>

            <div class="flex items-center gap-3">
                <a href="{{ route('home') }}" class="lucille-button">{{ $admin['view_site'] }}</a>
            </div>
        </header>

        <main class="mx-auto max-w-lg px-6 py-12">
            {{ $slot }}
        </main>
    @endauth

    <script>
        (() => {
            // Confirm Modal Scripts
            const modal = document.getElementById('admin-confirm-modal');
            if (!modal) {
                return;
            }

            const backdrop = document.getElementById('admin-confirm-backdrop');
            const titleEl = document.getElementById('admin-confirm-title');
            const messageEl = document.getElementById('admin-confirm-message');
            const methodEl = document.getElementById('admin-confirm-method');
            const kickerEl = document.getElementById('admin-confirm-kicker');
            const toneBar = document.getElementById('admin-confirm-tone');
            const icon = document.getElementById('admin-confirm-icon');
            const cancelBtn = document.getElementById('admin-confirm-cancel');
            const acceptBtn = document.getElementById('admin-confirm-accept');

            let activeForm = null;
            let activeSubmitter = null;

            const resetTone = () => {
                toneBar.className = 'h-1 w-full bg-[#c32720]';
                icon.className = 'mt-1 flex h-10 w-10 shrink-0 items-center justify-center border text-[10px] font-bold uppercase tracking-[.22em] border-[#5c2a2a] bg-[rgba(195,39,32,.12)] text-[#ffd0d0]';
                kickerEl.textContent = 'Acción destructiva';
            };

            const setTone = (tone) => {
                if (tone === 'soft') {
                    toneBar.className = 'h-1 w-full bg-[var(--lucille-accent)]';
                    icon.className = 'mt-1 flex h-10 w-10 shrink-0 items-center justify-center border text-[10px] font-bold uppercase tracking-[.22em] border-[rgba(220,220,220,.16)] bg-[rgba(255,255,255,.03)] text-[#dcdcdc]';
                    kickerEl.textContent = 'Confirmación';
                    return;
                }

                resetTone();
            };

            const openModal = (form, submitter) => {
                activeForm = form;
                activeSubmitter = submitter ?? null;

                const message = (form.dataset.confirm ?? '').trim();
                const title = (form.dataset.confirmTitle ?? '').trim() || 'Confirmar acción';
                const confirmLabel = (form.dataset.confirmAction ?? '').trim() || 'Confirmar';
                const cancelLabel = (form.dataset.confirmCancel ?? '').trim() || 'Cancelar';
                const tone = (form.dataset.confirmTone ?? 'danger').trim();
                const method = (form.querySelector('input[name="_method"]')?.value || form.method || 'POST').toUpperCase();

                titleEl.textContent = title;
                messageEl.textContent = message;
                methodEl.textContent = method;
                cancelBtn.textContent = cancelLabel;
                acceptBtn.textContent = confirmLabel;
                setTone(tone);

                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.classList.add('overflow-hidden');
                cancelBtn.focus({ preventScroll: true });
            };

            const closeModal = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
                activeForm = null;
                activeSubmitter = null;
            };

            const submitActiveForm = () => {
                if (!activeForm) {
                    closeModal();
                    return;
                }

                const form = activeForm;
                const submitter = activeSubmitter;

                closeModal();
                form.dataset.confirmBypass = '1';

                window.setTimeout(() => {
                    delete form.dataset.confirmBypass;
                }, 0);

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(submitter ?? undefined);
                    return;
                }

                form.submit();
            };

            document.addEventListener('submit', (event) => {
                const form = event.target;
                if (! (form instanceof HTMLFormElement)) {
                    return;
                }

                if (form.dataset.confirmBypass === '1') {
                    return;
                }

                const message = (form.dataset.confirm ?? '').trim();
                if (message === '') {
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();
                openModal(form, event.submitter ?? null);
            }, true);

            backdrop?.addEventListener('click', closeModal);
            cancelBtn?.addEventListener('click', closeModal);
            acceptBtn?.addEventListener('click', submitActiveForm);
            window.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });

            resetTone();
            closeModal();

            // Mobile Sidebar Toggle
            const sidebar = document.getElementById('admin-sidebar');
            const openSidebarBtn = document.getElementById('sidebar-open-btn');
            const closeSidebarBtn = document.getElementById('sidebar-close-btn');

            if (sidebar) {
                openSidebarBtn?.addEventListener('click', () => {
                    sidebar.classList.remove('-translate-x-full');
                });

                closeSidebarBtn?.addEventListener('click', () => {
                    sidebar.classList.add('-translate-x-full');
                });
            }

            // Omnibar (Command Palette) Scripts
            @auth
            const sections = [
                { name: 'Dashboard / Resumen', url: '{{ route("admin.dashboard") }}', category: 'General' },
                { name: 'Posts / Artículos', url: '{{ route("admin.posts.index") }}', category: 'General' },
                { name: 'Comentarios', url: '{{ route("admin.comments.index") }}', category: 'General' },
                { name: 'Eventos / Conciertos', url: '{{ route("admin.events.index") }}', category: 'General' },
                { name: 'Evento Único', url: '{{ route("admin.events.single") }}', category: 'General' },
                @role('Super Admin')
                { name: 'Bitácora de Auditoría', url: '{{ route("admin.audit-logs.index") }}', category: 'Sistema' },
                { name: 'Configuración Global', url: '{{ route("admin.settings.edit") }}', category: 'Sistema' },
                @endrole
                { name: 'Programas Master', url: '{{ route("admin.master-programs.index") }}', category: 'Programación' },
                { name: 'Subir Podcasts / Episodios', url: '{{ route("admin.podcast-uploads.index") }}', category: 'Programación' },
                { name: 'Canciones / Pistas', url: '{{ route("admin.songs.index") }}', category: 'Programación' },
                { name: 'Álbumes / Discografía', url: '{{ route("admin.albums.index") }}', category: 'Bandas' },
                { name: 'Videos', url: '{{ route("admin.videos.index") }}', category: 'Bandas' },
                { name: 'Galería de Fotos', url: '{{ route("admin.gallery.index") }}', category: 'Bandas' },
                { name: 'Artistas de Radio', url: '{{ route("admin.radio-artists.index") }}', category: 'Bandas' },
                { name: 'Nuevos Lanzamientos', url: '{{ route("admin.new-releases.index") }}', category: 'Bandas' },
                { name: 'Talentos', url: '{{ route("admin.talents.index") }}', category: 'Talento' },
                { name: 'Media de Talentos', url: '{{ route("admin.talents.media") }}', category: 'Talento' },
                { name: 'Programas Convocatoria', url: '{{ route("admin.programs.index") }}', category: 'Talento' },
                { name: 'Outreach / Campañas', url: '{{ route("admin.outreach.index") }}', category: 'Talento' },
                { name: 'Email Marketing y Contactos', url: '{{ route("admin.marketing.index") }}', category: 'Talento' },
            ];

            const omnibarModal = document.getElementById('admin-omnibar-modal');
            const omnibarInput = document.getElementById('admin-omnibar-input');
            const omnibarResults = document.getElementById('admin-omnibar-results');
            const omnibarBackdrop = document.getElementById('admin-omnibar-backdrop');
            const omnibarTrigger = document.getElementById('omnibar-trigger-btn');
            const omnibarClose = document.getElementById('admin-omnibar-close-btn');

            let selectedIndex = 0;
            let currentResults = [];

            const openOmnibar = () => {
                if (!omnibarModal) return;
                omnibarModal.classList.remove('hidden');
                omnibarModal.classList.add('flex');
                omnibarInput.value = '';
                filterSections('');
                omnibarInput.focus();
                document.body.classList.add('overflow-hidden');
            };

            const closeOmnibar = () => {
                if (!omnibarModal) return;
                omnibarModal.classList.add('hidden');
                omnibarModal.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            };

            const filterSections = (query) => {
                const term = query.toLowerCase().trim();
                const filtered = sections.filter(s => s.name.toLowerCase().includes(term) || s.category.toLowerCase().includes(term));
                renderSections(filtered);
            };

            const renderSections = (list) => {
                currentResults = list;
                selectedIndex = 0;
                if (!omnibarResults) return;

                if (list.length === 0) {
                    omnibarResults.innerHTML = `<p class="p-4 text-xs text-gray-500 text-center">No se encontraron secciones</p>`;
                    return;
                }
                
                omnibarResults.innerHTML = list.map((item, index) => `
                    <a href="${item.url}" class="flex items-center justify-between px-4 py-2.5 rounded text-xs transition-colors omnibar-item ${index === 0 ? 'bg-[var(--lucille-accent)] text-white' : 'text-gray-300 hover:bg-[rgba(255,255,255,.04)] hover:text-white'}" data-index="${index}">
                        <span class="font-medium">${item.name}</span>
                        <span class="text-[9px] uppercase tracking-wider text-gray-400 border border-[rgba(220,220,220,.12)] px-2 py-0.5 rounded">${item.category}</span>
                    </a>
                `).join('');
            };

            const updateSelection = (newIndex) => {
                const items = omnibarResults.querySelectorAll('.omnibar-item');
                if (items.length === 0) return;
                
                items[selectedIndex]?.classList.remove('bg-[var(--lucille-accent)]', 'text-white');
                items[selectedIndex]?.classList.add('text-gray-300', 'hover:bg-[rgba(255,255,255,.04)]', 'hover:text-white');
                
                selectedIndex = (newIndex + items.length) % items.length;
                
                items[selectedIndex]?.classList.add('bg-[var(--lucille-accent)]', 'text-white');
                items[selectedIndex]?.classList.remove('text-gray-300', 'hover:bg-[rgba(255,255,255,.04)]', 'hover:text-white');
                
                // Keep selected item in viewport
                const containerHeight = omnibarResults.clientHeight;
                const itemTop = items[selectedIndex].offsetTop;
                const itemHeight = items[selectedIndex].clientHeight;
                const scrollPos = omnibarResults.scrollTop;

                if (itemTop < scrollPos) {
                    omnibarResults.scrollTop = itemTop;
                } else if (itemTop + itemHeight > scrollPos + containerHeight) {
                    omnibarResults.scrollTop = itemTop + itemHeight - containerHeight;
                }
            };

            omnibarTrigger?.addEventListener('click', openOmnibar);
            omnibarBackdrop?.addEventListener('click', closeOmnibar);
            omnibarClose?.addEventListener('click', closeOmnibar);

            omnibarInput?.addEventListener('input', (e) => {
                filterSections(e.target.value);
            });

            window.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    openOmnibar();
                }
                
                if (e.key === 'Escape' && omnibarModal && !omnibarModal.classList.contains('hidden')) {
                    closeOmnibar();
                }
                
                if (omnibarModal && !omnibarModal.classList.contains('hidden')) {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        updateSelection(selectedIndex + 1);
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        updateSelection(selectedIndex - 1);
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        if (currentResults[selectedIndex]) {
                            window.location.href = currentResults[selectedIndex].url;
                        }
                    }
                }
            });
            @endauth
        })();
    </script>
    @auth
        @include('admin._global_help_modal')
    @endauth
    @stack('scripts')
</body>
</html>
