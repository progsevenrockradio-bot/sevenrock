<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis Entregas · Portal de Conductores · Seven Rock Radio</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#151515] text-[#dcdcdc] font-sans">
    <header class="border-b border-[#2b2b2b] bg-[#081a24] px-6 py-4">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4">
            <div>
                <p class="text-[11px] uppercase tracking-[.22em] text-[#c32720]">Seven Rock Radio · Portal de Conductores</p>
                <h1 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Entregas de Episodios</h1>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-[#9a9a9a]">{{ $conductor->name }}</span>
                <a href="{{ route('conductor.episodes.create') }}" class="lucille-button-solid text-xs">+ Nueva Entrega</a>
                <form action="{{ route('conductor.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="lucille-button text-xs">Salir</button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-5xl space-y-8 p-6">
        @if (session('status'))
            <div class="border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        <section class="border border-[#2b2b2b] bg-[#101012] p-6">
            <h2 class="font-display text-lg uppercase tracking-[.12em] text-[#dcdcdc]">Mis Programas Autorizados</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @forelse ($authorizedPrograms as $program)
                    <div class="border border-[#242424] bg-[#151515] p-4 flex items-center justify-between gap-4">
                        <div>
                            <div class="font-display text-base uppercase tracking-[.1em] text-[#dcdcdc]">{{ $program->nombre }}</div>
                            <div class="text-xs text-[#7b7b7b]">{{ $program->dia_transmision }} · {{ substr((string) $program->hora_transmision, 0, 5) }} · {{ $program->genero }}</div>
                        </div>
                        <a href="{{ route('conductor.episodes.create', $program) }}" class="lucille-button text-xs">Entregar episodio</a>
                    </div>
                @empty
                    <p class="text-sm text-[#7b7b7b]">No tienes programas asignados actualmente.</p>
                @endforelse
            </div>
        </section>

        <section class="border border-[#2b2b2b] bg-[#101012] p-6">
            <h2 class="font-display text-lg uppercase tracking-[.12em] text-[#dcdcdc]">Historial de Entregas</h2>
            <div class="mt-4 space-y-3">
                @forelse ($episodes as $ep)
                    @php
                        $states = $ep->operationalStates();
                    @endphp
                    <div class="border border-[#242424] bg-[#151515] p-4 flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <div class="font-display text-sm uppercase tracking-[.1em] text-white">
                                Ep. {{ $ep->numero_episodio }} · {{ $ep->live_title ?: $ep->titulo_programa }}
                            </div>
                            <div class="mt-1 text-xs text-[#9a9a9a]">
                                {{ $ep->masterProgram?->nombre }} · Emisión: {{ optional($ep->fecha_emision)->format('d/m/Y') }} · Versión v{{ (int) ($ep->editorial_version ?: 1) }}
                            </div>
                            <div class="mt-2 text-xs text-amber-300">
                                @if ($ep->editorial_status === 'pending_editorial_review')
                                    Recibido, pendiente de revisión editorial
                                @elseif ($ep->editorial_status === 'retained_incident')
                                    Retenido para revisión editorial por incidencia
                                @elseif ($ep->editorial_status === 'approved')
                                    Aprobado editorialmente
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($ep->canConductorShareAndDownload())
                                <a href="{{ route('conductor.episodes.download', $ep) }}" class="lucille-button-solid text-xs" title="Descargar MP3 definitivo">Descargar MP3</a>
                                <a href="{{ $ep->publicShareUrl() }}" target="_blank" rel="noopener" class="lucille-button text-xs" title="Abrir ficha pública">Ficha pública</a>
                            @endif
                            <a href="{{ route('conductor.episodes.show', $ep) }}" class="lucille-button text-xs">Ver estado</a>
                            <a href="{{ route('conductor.episodes.edit', $ep) }}" class="lucille-button text-xs">Modificar</a>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-[#7b7b7b]">Todavía no has enviado episodios.</p>
                @endforelse
            </div>
        </section>
    </main>
</body>
</html>

