@props([
    'title' => 'Talentos',
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
            <a href="{{ route('talents.dashboard') }}" class="font-display text-sm uppercase tracking-[.18em] text-white">Talentos</a>
            <nav class="flex flex-wrap items-center gap-2 text-sm">
                <a href="{{ route('talents.dashboard') }}" class="lucille-button">Panel</a>
                <a href="{{ route('talents.subscriptions.plans') }}" class="lucille-button">Suscripción</a>
                <a href="{{ route('talents.profile') }}" class="lucille-button">Mi Perfil</a>
                <a href="{{ route('talents.notifications.edit') }}" class="lucille-button">Notificaciones</a>
                <a href="{{ route('talents.media.index') }}" class="lucille-button">Mi Música</a>
                <a href="{{ route('talentos.store.index') }}" class="lucille-button">Mi Tienda</a>
                <a href="{{ route('talents.albums.index') }}" class="lucille-button">Mis Álbumes</a>
                <form method="POST" action="{{ route('talents.logout') }}">
                    @csrf
                    <button type="submit" class="lucille-button-solid">Cerrar sesión</button>
                </form>
            </nav>
        </div>
    </header>

    <div class="mx-auto grid max-w-7xl gap-6 px-5 py-6 lg:grid-cols-[280px_1fr]">
        <aside class="border border-white/10 bg-[#10161b] p-5">
            <div class="font-display text-xs uppercase tracking-[.18em] text-[#8f9aa3]">Plan actual</div>
            <div class="mt-2 text-2xl font-semibold text-white">{{ ucfirst((string) ($talent?->plan ?? 'free')) }}</div>
            <div class="mt-1 text-sm text-[#9aa7b1]">{{ $talent?->band_name ?? 'Sin nombre' }}</div>

            <div class="mt-5 space-y-3 text-sm text-[#c7d0d8]">
                <div class="flex items-center justify-between border-b border-white/10 pb-2">
                    <span>Fotos</span>
                    <span>{{ $usage['photos'] ?? 0 }}/{{ $limits['photos'] ?? 0 }}</span>
                </div>
                <div class="flex items-center justify-between border-b border-white/10 pb-2">
                    <span>Canciones</span>
                    <span>{{ $usage['songs'] ?? 0 }}/{{ $limits['songs'] ?? 0 }}</span>
                </div>
                <div class="flex items-center justify-between border-b border-white/10 pb-2">
                    <span>Documentos</span>
                    <span>{{ $usage['documents'] ?? 0 }}/{{ $limits['documents'] ?? 0 }}</span>
                </div>
                <div class="flex items-center justify-between border-b border-white/10 pb-2">
                    <span>Videos</span>
                    <span>{{ $usage['videos'] ?? 0 }}/{{ $limits['videos'] ?? 0 }}</span>
                </div>
                <div class="flex items-center justify-between border-b border-white/10 pb-2">
                    <span>Visitas</span>
                    <span>{{ $usage['visits'] ?? 0 }}</span>
                </div>
                <div class="flex items-center justify-between border-b border-white/10 pb-2">
                    <span>Renovación</span>
                    <span>{{ $renewalDate }}</span>
                </div>
            </div>

            <div class="mt-5">
                <div class="flex items-center justify-between text-xs uppercase tracking-[.18em] text-[#8f9aa3]">
                    <span>Almacenamiento</span>
                    <span>{{ number_format($storageUsed, 2) }} / {{ $storageLimit }} MB</span>
                </div>
                <div class="mt-2 h-2 overflow-hidden bg-white/10">
                    <div class="h-full bg-white" style="width: {{ (int) $storageProgress }}%"></div>
                </div>
            </div>
        </aside>

        <main>
            {{ $slot }}
        </main>
    </div>
</body>
</html>
