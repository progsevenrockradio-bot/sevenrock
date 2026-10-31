@props([
    'title' => 'Portal de bandas',
    'talent' => null,
    'plan' => null,
    'limits' => null,
    'usage' => null,
    'subscription' => null,
    'storageProgress' => 0,
])

@php
    $talent ??= auth('talent')->user();
    $plan ??= $talent?->planDefinition() ?? \App\Support\TalentPlan::definition('free');
    $limits ??= $talent?->planLimits() ?? \App\Support\TalentPlan::definition('free')['limits'];
    $usage ??= [
        'photos' => (int) ($talent?->media()->where('type', 'photo')->count() ?? 0),
        'songs' => (int) ($talent?->media()->where('type', 'mp3')->count() ?? 0),
        'documents' => (int) ($talent?->media()->where('type', 'document')->count() ?? 0),
        'videos' => (int) ($talent?->media()->where('type', 'video')->count() ?? 0),
        'visits' => (int) ($talent?->interacts ?? 0),
        'storage_used_mb' => round(((int) ($talent?->storageUsed() ?? 0)) / 1024 / 1024, 2),
    ];
    $renewalDate = $subscription?->end_date?->format('d/m/Y') ?? ($talent?->created_at?->addMonth()->format('d/m/Y') ?? 'N/D');
    $storageLimit = (int) ($limits['storage_mb'] ?? 0);
    $storageUsed = (float) ($usage['storage_used_mb'] ?? 0);
    $logoUrl = \App\Models\ThemeSetting::current()?->logo_url ?? asset('assets/lucille/logo.png');
    $esActivo = fn (string $ruta) => request()->routeIs($ruta);

    $navItems = [
        ['label' => 'Panel', 'url' => route('talents.dashboard'), 'active' => $esActivo('talents.dashboard')],
        ['label' => 'Suscripción', 'url' => route('talents.subscriptions.plans'), 'active' => $esActivo('talents.subscriptions.*')],
        ['label' => 'Mi Perfil', 'url' => route('talents.profile'), 'active' => $esActivo('talents.profile*')],
        ['label' => 'Notificaciones', 'url' => route('talents.notifications.edit'), 'active' => $esActivo('talents.notifications.*')],
        ['label' => 'Mis archivos', 'url' => route('talents.media.index'), 'active' => $esActivo('talents.media.*')],
        ['label' => 'Mi Tienda', 'url' => route('talents.store.index'), 'active' => $esActivo('talents.store.*')],
        ['label' => 'Mis Álbumes', 'url' => route('talents.albums.index'), 'active' => $esActivo('talents.albums.*')],
    ];

    $currentSection = 'Portal de bandas';
    foreach ($navItems as $item) {
        if ($item['active']) {
            $currentSection = $item['label'];
            break;
        }
    }
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ $logoUrl }}">
    <link rel="apple-touch-icon" href="{{ $logoUrl }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .is-active {
            border-color: #ffffff;
            color: #ffffff;
            background: rgba(255, 255, 255, .10);
            font-weight: 600;
        }
    </style>
</head>
<body class="min-h-screen bg-[#0b0f12] text-[#d8d8d8] antialiased">
    <header class="border-b border-white/10 bg-[#10161b]">
        <div class="mx-auto max-w-7xl px-4 py-3 sm:px-5 sm:py-4">
            <div x-data="{ mobileNavOpen: false }" class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                {{-- Barra superior con branding y trigger móvil --}}
                <div class="flex items-center justify-between w-full lg:w-auto">
                    <a href="{{ route('talents.dashboard') }}" class="font-display text-sm sm:text-base uppercase tracking-[.18em] text-white shrink-0 flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-[var(--lucille-accent)]"></span>
                        <span>Portal de bandas</span>
                    </a>

                    {{-- Botón menú desplegable en pantallas pequeñas/medianas (< lg) --}}
                    <button type="button" 
                            @click="mobileNavOpen = !mobileNavOpen" 
                            @keydown.escape="mobileNavOpen = false"
                            class="lg:hidden flex items-center gap-2 px-3 py-2 rounded-[6px] border border-white/15 bg-black/40 text-xs font-mono text-[#dcdcdc] focus:outline-none focus:ring-2 focus:ring-[var(--lucille-accent)] min-h-[44px]"
                            aria-label="Menú de navegación" 
                            :aria-expanded="mobileNavOpen.toString()">
                        <span class="text-[10px] text-[#8b8b8b] uppercase tracking-wider">Sección:</span>
                        <span class="font-semibold text-white truncate max-w-[130px]">{{ $currentSection }}</span>
                        <svg class="w-4 h-4 ml-0.5 transition-transform duration-200" :class="mobileNavOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>

                {{-- Menú móvil desplegable (< lg) --}}
                <div x-show="mobileNavOpen" 
                     x-cloak 
                     @click.away="mobileNavOpen = false"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-2"
                     class="lg:hidden mt-2 p-3 rounded-[8px] border border-white/10 bg-[#141416] shadow-2xl space-y-1 z-40">
                    <nav class="flex flex-col space-y-1">
                        @foreach ($navItems as $item)
                            <a href="{{ $item['url'] }}" 
                               @click="mobileNavOpen = false"
                               class="flex items-center justify-between px-3 py-2.5 rounded-[6px] text-xs font-medium transition-colors min-h-[44px] {{ $item['active'] ? 'bg-[var(--lucille-accent)] text-white font-semibold' : 'text-[#dcdcdc] hover:bg-white/5' }}">
                                <span>{{ $item['label'] }}</span>
                                @if ($item['active'])
                                    <span class="text-[9px] font-mono uppercase bg-black/40 px-1.5 py-0.5 rounded text-white/90">Activo</span>
                                @endif
                            </a>
                        @endforeach
                    </nav>

                    {{-- Separador y Cerrar Sesión claramente separado --}}
                    <div class="pt-2.5 mt-2 border-t border-white/10">
                        <form method="POST" action="{{ route('talents.logout') }}" class="w-full">
                            @csrf
                            <button type="submit" class="w-full flex items-center justify-center gap-2 px-3 py-2.5 rounded-[6px] text-xs font-medium border border-red-900/40 bg-red-950/20 text-red-300 hover:bg-red-950/40 hover:text-red-200 transition-colors min-h-[44px]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                <span>Cerrar sesión</span>
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Navegación Escritorio (>= lg) --}}
                <nav class="hidden lg:flex items-center gap-2 flex-wrap text-sm">
                    @foreach ($navItems as $item)
                        <a href="{{ $item['url'] }}" class="lucille-button shrink-0 {{ $item['active'] ? 'is-active' : '' }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                    <form method="POST" action="{{ route('talents.logout') }}" class="shrink-0 ml-1">
                        @csrf
                        <button type="submit" class="lucille-button-solid shrink-0 text-xs min-h-[40px] px-3">Cerrar sesión</button>
                    </form>
                </nav>
            </div>
        </div>
    </header>

    <div class="mx-auto grid max-w-7xl gap-6 px-4 py-4 sm:px-5 sm:py-6 lg:grid-cols-[280px_1fr]">
        {{-- Resumen de plan: Compacto y plegable en móvil (< lg), siempre visible en escritorio (>= lg) --}}
        <aside x-data="{ planExpanded: false }" class="border border-white/10 bg-[#10161b] p-4 sm:p-5 rounded-[8px] h-fit">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <div class="font-display text-[10px] sm:text-xs uppercase tracking-[.18em] text-[#8f9aa3]">Plan actual</div>
                    <div class="mt-0.5 text-xl sm:text-2xl font-semibold text-white flex items-center gap-2">
                        <span>{{ ucfirst((string) ($talent?->plan ?? 'free')) }}</span>
                        <span class="text-xs font-mono font-normal text-[#8f9aa3]">({{ number_format($storageUsed, 1) }}/{{ $storageLimit }} MB)</span>
                    </div>
                    <div class="mt-0.5 text-xs text-[#9aa7b1] truncate max-w-[190px] sm:max-w-none">{{ $talent?->band_name ?? 'Sin nombre' }}</div>
                </div>

                {{-- Botón plegable en móvil --}}
                <button type="button" 
                        @click="planExpanded = !planExpanded" 
                        class="lg:hidden flex items-center gap-1.5 px-3 py-2 rounded-[6px] border border-white/10 bg-white/5 text-xs text-[#dcdcdc] min-h-[44px] hover:bg-white/10 transition-colors"
                        :aria-expanded="planExpanded.toString()">
                    <span x-text="planExpanded ? 'Ocultar' : 'Ver límites'"></span>
                    <svg class="w-4 h-4 transition-transform duration-200" :class="planExpanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
            </div>

            <div :class="planExpanded ? 'block' : 'hidden lg:block'" class="mt-4 pt-4 border-t border-white/10">
                <div class="space-y-2 text-xs sm:text-sm text-[#c7d0d8]">
                    <div class="flex items-center justify-between border-b border-white/5 pb-1.5">
                        <span>Fotos</span>
                        <span class="font-mono text-white">{{ $usage['photos'] ?? 0 }}/{{ $limits['photos'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-white/5 pb-1.5">
                        <span>Canciones</span>
                        <span class="font-mono text-white">{{ $usage['songs'] ?? 0 }}/{{ $limits['songs'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-white/5 pb-1.5">
                        <span>Documentos</span>
                        <span class="font-mono text-white">{{ $usage['documents'] ?? 0 }}/{{ $limits['documents'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-white/5 pb-1.5">
                        <span>Videos</span>
                        <span class="font-mono text-white">{{ $usage['videos'] ?? 0 }}/{{ $limits['videos'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-white/5 pb-1.5">
                        <span>Visitas</span>
                        <span class="font-mono text-white">{{ $usage['visits'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-white/5 pb-1.5">
                        <span>Renovación</span>
                        <span class="font-mono text-white">{{ $renewalDate }}</span>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="flex items-center justify-between text-xs uppercase tracking-[.18em] text-[#8f9aa3]">
                        <span>Almacenamiento</span>
                        <span class="font-mono text-white">{{ number_format($storageUsed, 2) }} / {{ $storageLimit }} MB</span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden bg-white/10 rounded-full">
                        <div class="h-full bg-[var(--lucille-accent)] rounded-full transition-all duration-300" style="width: {{ (int) $storageProgress }}%"></div>
                    </div>
                </div>
            </div>
        </aside>

        <main class="min-w-0">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
