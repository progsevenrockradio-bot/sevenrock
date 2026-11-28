<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entregas y Emisiones · Portal de Conductores · Seven Rock Radio</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#151515] text-[#dcdcdc] font-sans">
    <header class="border-b border-[#2b2b2b] bg-[#081a24] px-6 py-4">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
            <div>
                <p class="text-[11px] uppercase tracking-[.22em] text-[#c32720]">Seven Rock Radio · Portal de Conductores</p>
                <div class="font-display text-lg uppercase tracking-[.12em] text-[#dcdcdc]">Mis Entregas y Emisiones</div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-[#9a9a9a]">{{ $conductor->name }}</span>
                <a href="{{ route('conductor.episodes.index') }}" class="lucille-button text-xs">Portal</a>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl p-6">
        @include('admin.podcast-uploads.partials.deliveries-content', [
            'isAdminView' => false,
        ])
    </main>
</body>
</html>

