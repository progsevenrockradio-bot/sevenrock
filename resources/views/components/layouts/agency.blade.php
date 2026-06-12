@props([
    'title' => 'Portal de Agencias',
    'agency' => null,
])

@php
    $agency ??= auth('agency')->user();
    $logoUrl = \App\Models\ThemeSetting::current()?->logo_url ?? asset('assets/lucille/logo.png');
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
</head>
<body class="min-h-screen bg-[#0b0f12] text-[#d8d8d8] antialiased">
    <header class="border-b border-white/10 bg-[#10161b]">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-4">
            <a href="{{ route('agency.dashboard') }}" class="font-display text-sm uppercase tracking-[.18em] text-white">Agencias Colaboradoras</a>
            <nav class="flex flex-wrap items-center gap-2 text-sm">
                <a href="{{ route('agency.dashboard') }}" class="lucille-button">Panel</a>
                <a href="{{ route('agency.bands') }}" class="lucille-button">Mis Bandas</a>
                <a href="{{ route('agency.profile') }}" class="lucille-button">Perfil de Agencia</a>
                <form method="POST" action="{{ route('agency.logout') }}">
                    @csrf
                    <button type="submit" class="lucille-button-solid">Cerrar sesión</button>
                </form>
            </nav>
        </div>
    </header>

    <div class="mx-auto grid max-w-7xl gap-6 px-5 py-6 lg:grid-cols-[280px_1fr]">
        <aside class="border border-white/10 bg-[#10161b] p-5">
            <div class="font-display text-xs uppercase tracking-[.18em] text-[#8f9aa3]">Agencia</div>
            <div class="mt-2 text-2xl font-semibold text-white truncate">{{ $agency?->name }}</div>
            <div class="mt-1 text-sm text-[#9aa7b1]">{{ $agency?->email }}</div>

            @if($agency && $agency->logo_path)
                <div class="mt-6 border border-white/10 p-2 bg-black/45">
                    <img src="{{ $agency->logo_url }}" alt="{{ $agency->name }}" class="w-full h-auto object-contain">
                </div>
            @endif

            <div class="mt-6 space-y-3 text-sm text-[#c7d0d8]">
                <div class="flex items-center justify-between border-b border-white/10 pb-2">
                    <span>Bandas Representadas</span>
                    <span>{{ $agency?->radioArtists()->count() ?? 0 }}</span>
                </div>
                @if($agency && $agency->website_url)
                    <div class="pt-2 text-xs truncate">
                        <a href="{{ $agency->website_url }}" target="_blank" rel="noreferrer" class="text-[var(--lucille-accent)] hover:underline">{{ $agency->website_url }}</a>
                    </div>
                @endif
                @if($agency)
                    <div class="pt-4 border-t border-white/5">
                        <a href="{{ route('agency.public-profile', $agency->slug) }}" target="_blank" class="w-full text-center block border border-white/10 hover:border-[var(--lucille-accent)] hover:text-white py-2 text-xs font-display uppercase tracking-wider rounded-[6px] transition-all">
                            🔗 Ver Página Pública
                        </a>
                    </div>
                @endif
            </div>
        </aside>

        <main>
            {{ $slot }}
        </main>
    </div>

    @stack('scripts')
</body>
</html>
