<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirmar Aprobación Editorial · Seven Rock Radio</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#151515] text-[#dcdcdc] font-sans flex items-center justify-center p-6">
    <div class="w-full max-w-2xl border border-[#2b2b2b] bg-[#101012] p-8 space-y-6 shadow-[0_28px_72px_rgba(0,0,0,.58)]">
        <div class="border-b border-[#2b2b2b] pb-4">
            <p class="text-xs uppercase tracking-[.22em] text-[#c32720]">Seven Rock Radio · Control Editorial</p>
            <h1 class="mt-1 font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Confirmar Aprobación de Episodio</h1>
            <p class="mt-2 text-xs text-[#9a9a9a]">
                La apertura de este enlace no aprueba automáticamente el episodio. Revisa los datos de la versión vinculada y confirma con el botón inferior.
            </p>
        </div>

        <div class="space-y-2 text-sm border border-[#242424] bg-[#151515] p-4">
            <div><strong class="text-[#9a9a9a]">Programa:</strong> {{ $radioProgram->masterProgram?->nombre ?? $radioProgram->titulo_programa }}</div>
            <div><strong class="text-[#9a9a9a]">Episodio:</strong> #{{ $radioProgram->numero_episodio }} — {{ $radioProgram->live_title ?: $radioProgram->titulo_programa }}</div>
            <div><strong class="text-[#9a9a9a]">Fecha de emisión:</strong> {{ optional($radioProgram->fecha_emision)->format('d/m/Y') }}</div>
            <div><strong class="text-[#9a9a9a]">Versión del enlace:</strong> v{{ $requestedVersion }} (Versión actual en sistema: v{{ (int) ($radioProgram->editorial_version ?: 1) }})</div>
            <div><strong class="text-[#9a9a9a]">Resumen:</strong> {{ $radioProgram->editorial_summary }}</div>
        </div>

        @if ($alreadyApprovedForThisVersion)
            <div class="border border-amber-500/40 bg-amber-500/10 p-4 text-sm text-amber-200">
                Esta versión (v{{ $requestedVersion }}) ya fue aprobada anteriormente. No se permiten aprobaciones repetidas.
            </div>
        @elseif (!$versionMatches)
            <div class="border border-rose-500/40 bg-rose-500/10 p-4 text-sm text-rose-200">
                El enlace corresponde a la versión v{{ $requestedVersion }}, pero el episodio fue modificado posteriormente (versión actual v{{ (int) ($radioProgram->editorial_version ?: 1) }}). Este enlace ha quedado invalidado.
            </div>
        @else
            <form action="{{ $postActionUrl }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="version" value="{{ $requestedVersion }}">
                <input type="hidden" name="vtoken" value="{{ $requestedToken }}">

                <p class="text-xs text-[#9a9a9a]">
                    Nota: Aprobar editorialmente autoriza el procesamiento definitivo del MP3 en Cloudflare R2 y su distribución a RadioBOSS / Archive.org. No publica automáticamente en redes sociales.
                </p>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="lucille-button-solid">Confirmar y Aprobar Versión v{{ $requestedVersion }}</button>
                    <a href="{{ route('admin.podcast-uploads.editorial.show', $radioProgram) }}" class="lucille-button">Abrir en panel para revisar o corregir</a>
                </div>
            </form>
        @endif
    </div>
</body>
</html>

