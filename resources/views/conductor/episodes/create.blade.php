<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nueva Entrega · Portal de Conductores · Seven Rock Radio</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#151515] text-[#dcdcdc] font-sans">
    <header class="border-b border-[#2b2b2b] bg-[#081a24] px-6 py-4">
        <div class="mx-auto flex max-w-4xl items-center justify-between gap-4">
            <div>
                <p class="text-[11px] uppercase tracking-[.22em] text-[#c32720]">Portal de Conductores</p>
                <h1 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Entregar Nuevo Episodio</h1>
            </div>
            <a href="{{ route('conductor.episodes.index') }}" class="lucille-button text-xs">Volver a mis entregas</a>
        </div>
    </header>

    <main class="mx-auto max-w-4xl space-y-6 p-6">
        @if (!empty($deadlineInfo['is_closed']))
            <div class="border border-rose-500/50 bg-rose-950/40 p-4 text-xs text-rose-200">
                <strong class="font-bold uppercase tracking-wider block text-rose-300">Plazo de entrega cerrado</strong>
                El plazo límite para la fecha de emisión seleccionada venció el {{ $deadlineInfo['deadline_formatted'] ?? '' }} ({{ $deadlineInfo['remaining_human'] ?? '' }}).
                No se admiten nuevas entregas para esta fecha. Si requieres autorización para una entrega tardía, contacta con la administración de la radio.
            </div>
        @endif

        <section class="border border-[#2b2b2b] bg-[#101012] p-6">
            <h2 class="font-display text-base uppercase tracking-[.12em] text-[#dcdcdc]">Datos generales del Programa Maestro</h2>
            <div class="mt-3 grid gap-3 md:grid-cols-3 text-xs text-[#9a9a9a]">
                <div><strong class="text-[#dcdcdc]">Programa:</strong> {{ $selectedProgram->nombre }}</div>
                <div><strong class="text-[#dcdcdc]">Conductor titular:</strong> {{ $selectedProgram->conductor }}</div>
                <div><strong class="text-[#dcdcdc]">Horario base:</strong> {{ $selectedProgram->dia_transmision }} {{ substr((string) $selectedProgram->hora_transmision, 0, 5) }} ({{ $selectedProgram->timezone ?: config('app.timezone', 'Europe/Madrid') }})</div>
            </div>

            {{-- PLAZO DE ENTREGA Y REVISIÓN EDITORIAL --}}
            <div class="mt-4 border-t border-[#242424] pt-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-display uppercase tracking-[.15em] text-[#dcdcdc] text-sm">Plazo de Entrega y Emisión</span>
                    @if (empty($deadlineInfo) || ($deadlineInfo['status'] ?? '') === 'undetermined')
                        <span class="text-[11px] text-amber-400 bg-amber-500/10 border border-amber-500/30 px-2 py-0.5">Horario no determinado</span>
                    @elseif (!empty($deadlineInfo['is_closed']))
                        <span class="text-[11px] font-semibold text-rose-300 bg-rose-500/20 border border-rose-500/40 px-2 py-0.5">Plazo cerrado ({{ $deadlineInfo['remaining_human'] }})</span>
                    @else
                        <span class="text-[11px] font-semibold text-emerald-300 bg-emerald-500/20 border border-emerald-500/40 px-2 py-0.5">{{ $deadlineInfo['remaining_human'] }}</span>
                    @endif
                </div>

                @if (!empty($deadlineInfo) && in_array(($deadlineInfo['status'] ?? ''), ['ok', 'calculated'], true))
                    <div class="mt-3 grid gap-3 md:grid-cols-2 text-xs bg-[#151515] p-3 border border-[#242424]">
                        <div>
                            <span class="text-[11px] uppercase tracking-[.12em] text-[#7b7b7b] block">Fecha y hora de emisión programada</span>
                            <span class="text-[#dcdcdc] font-semibold">{{ $deadlineInfo['emission_formatted'] }}</span>
                            @if (!empty($deadlineInfo['crosses_midnight']))
                                <span class="text-[10px] text-amber-300 block">Cruza medianoche respecto al día base.</span>
                            @endif
                        </div>
                        <div>
                            <span class="text-[11px] uppercase tracking-[.12em] text-[#7b7b7b] block">Límite estricto de entrega (cierre obligatorio {{ $deadlineInfo['cutoff_human'] ?? '24 horas' }} antes)</span>
                            <span class="text-[#dcdcdc] font-semibold">{{ $deadlineInfo['deadline_formatted'] }}</span>
                        </div>
                    </div>
                @elseif (!empty($deadlineInfo) && ($deadlineInfo['status'] ?? '') === 'undetermined')
                    <p class="mt-2 text-xs text-amber-300/80">
                        {{ $deadlineInfo['reason'] ?? 'No se pudo determinar el horario con exactitud.' }}
                        Por favor contacta con administración para verificar el horario oficial en parrilla.
                    </p>
                @endif

                <div class="mt-3 text-[11px] text-[#9a9a9a] space-y-1">
                    <p>• <strong class="text-amber-300">Petición promocional:</strong> Se solicita comunicar el tema y los datos editoriales del episodio con al menos 24 horas de antelación para preparar la promoción y producción de vídeos en redes.</p>
                    <p>• <strong class="text-[#c32720]">Cierre obligatorio de audio:</strong> El archivo MP3 debe haber terminado de subir y la entrega debe haberse enviado antes del límite fijado para este programa ({{ $deadlineInfo['cutoff_human'] ?? 'según programa' }} antes de la emisión). Comenzar la subida antes no reserva el plazo.</p>
                    <p>• <strong class="text-[#b0b0b0]">Revisión editorial:</strong> Entregar un episodio no equivale a su aprobación editorial ni a su inclusión en RadioBOSS. Toda entrega pasa por revisión del equipo antes de ser emitida.</p>
                </div>
            </div>
            @php
                $guide = is_array($editorialGuide ?? null) ? $editorialGuide : [];
                $identity = is_string($guide['identity_and_audience'] ?? null) ? trim($guide['identity_and_audience']) : '';
                $tone = is_string($guide['tone_and_style'] ?? null) ? trim($guide['tone_and_style']) : '';
                $structure = $guide['structure_rules'] ?? [];
                $structureGuidelines = is_array($structure) ? trim((string) ($structure['guidelines'] ?? '')) : (is_string($structure) ? trim($structure) : '');
                $structureLimits = [];
                if (is_array($structure)) {
                    if (!empty($structure['title_min_length'])) {
                        $structureLimits[] = 'Título: mín. ' . (int) $structure['title_min_length'] . ' car.' . (!empty($structure['title_max_length']) ? ' (máx. ' . (int) $structure['title_max_length'] . ')' : '');
                    }
                    if (!empty($structure['summary_min_length'])) {
                        $structureLimits[] = 'Resumen: mín. ' . (int) $structure['summary_min_length'] . ' car.';
                    }
                    if (!empty($structure['description_min_length'])) {
                        $structureLimits[] = 'Reseña: mín. ' . (int) $structure['description_min_length'] . ' car.';
                    }
                }
                $coverReq = $guide['cover_requirements'] ?? [];
                $coverGuidelines = is_array($coverReq) ? trim((string) ($coverReq['guidelines'] ?? '')) : (is_string($coverReq) ? trim($coverReq) : '');
                $particular = is_string($guide['particular_rules'] ?? null) ? trim($guide['particular_rules']) : '';
                $examples = is_string($guide['optional_examples'] ?? null) ? trim($guide['optional_examples']) : '';
                $hasAnyGuide = $identity !== '' || $tone !== '' || $structureGuidelines !== '' || $structureLimits !== [] || $coverGuidelines !== '' || $particular !== '' || $examples !== '';
            @endphp

            @if ($hasAnyGuide)
                <div class="mt-4 border-t border-[#242424] pt-4 text-xs space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="font-display uppercase tracking-[.15em] text-[#dcdcdc] text-sm">Guía Editorial del Programa</span>
                        <span class="text-[10px] uppercase tracking-[.18em] text-[#c32720]">Criterios de entrega</span>
                    </div>

                    @if ($identity !== '')
                        <div>
                            <span class="text-[11px] uppercase tracking-[.12em] text-[#7b7b7b] block">Identidad y Público</span>
                            <p class="text-[#b0b0b0] mt-0.5 leading-relaxed">{{ $identity }}</p>
                        </div>
                    @endif

                    @if ($tone !== '')
                        <div>
                            <span class="text-[11px] uppercase tracking-[.12em] text-[#7b7b7b] block">Tono y Estilo</span>
                            <p class="text-[#b0b0b0] mt-0.5 leading-relaxed">{{ $tone }}</p>
                        </div>
                    @endif

                    @if ($structureGuidelines !== '' || $structureLimits !== [])
                        <div>
                            <span class="text-[11px] uppercase tracking-[.12em] text-[#7b7b7b] block">Estructura y Extensión</span>
                            @if ($structureGuidelines !== '')
                                <p class="text-[#b0b0b0] mt-0.5 leading-relaxed">{{ $structureGuidelines }}</p>
                            @endif
                            @if ($structureLimits !== [])
                                <p class="text-[#9a9a9a] mt-1 text-[11px]">{{ implode(' · ', $structureLimits) }}</p>
                            @endif
                        </div>
                    @endif

                    @if ($coverGuidelines !== '')
                        <div>
                            <span class="text-[11px] uppercase tracking-[.12em] text-[#7b7b7b] block">Portada</span>
                            <p class="text-[#b0b0b0] mt-0.5 leading-relaxed">{{ $coverGuidelines }}</p>
                        </div>
                    @endif

                    @if ($particular !== '')
                        <div>
                            <span class="text-[11px] uppercase tracking-[.12em] text-[#7b7b7b] block">Instrucciones Específicas</span>
                            <p class="text-[#b0b0b0] mt-0.5 leading-relaxed">{{ $particular }}</p>
                        </div>
                    @endif

                    @if ($examples !== '')
                        <div>
                            <span class="text-[11px] uppercase tracking-[.12em] text-[#7b7b7b] block">Ejemplos Recomendados</span>
                            <p class="text-[#888888] italic mt-0.5 leading-relaxed">{{ $examples }}</p>
                        </div>
                    @endif
                </div>
            @endif
        </section>

        <form action="{{ route('conductor.episodes.store') }}" method="POST" enctype="multipart/form-data" class="border border-[#2b2b2b] bg-[#101012] p-6 space-y-5">
            @csrf

            <div class="grid gap-5 md:grid-cols-3">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Programa autorizado</label>
                    <select name="master_program_id" class="lucille-product-field lucille-select-field w-full" required>
                        @foreach ($authorizedPrograms as $program)
                            <option value="{{ $program->id }}" @selected((int) old('master_program_id', $selectedProgram->id) === (int) $program->id)>
                                {{ $program->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('master_program_id')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Número de episodio</label>
                    <input type="number" min="1" name="numero_episodio" value="{{ old('numero_episodio', $nextEpisodeNumber) }}" class="lucille-product-field w-full" required>
                    @error('numero_episodio')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Fecha de emisión</label>
                    <input type="date" name="fecha_emision" value="{{ old('fecha_emision', now()->toDateString()) }}" class="lucille-product-field w-full" required>
                    @error('fecha_emision')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Título específico del episodio</label>
                <input type="text" name="live_title" value="{{ old('live_title') }}" class="lucille-product-field w-full" required placeholder="Ej. Especial Pioneros del Heavy Metal Latino">
                @error('live_title')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Resumen corto editorial</label>
                <textarea name="editorial_summary" rows="3" class="lucille-product-field w-full" required placeholder="Síntesis breve del episodio para portada y boletín...">{{ old('editorial_summary') }}</textarea>
                @error('editorial_summary')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Descripción / Reseña completa del episodio</label>
                <textarea name="resena" rows="5" class="lucille-product-field w-full" required placeholder="Descripción detallada del contenido, bandas e historia del episodio...">{{ old('resena') }}</textarea>
                @error('resena')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Biografía o notas del invitado (opcional)</label>
                <textarea name="biografia_invitado" rows="3" class="lucille-product-field w-full">{{ old('biografia_invitado') }}</textarea>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Archivo de audio (MP3)</label>
                    <p class="mb-2 text-[11px] text-amber-300/90">⚠️ La subida completa y el envío del formulario deben completarse antes del límite. Iniciar la subida con anticipación no reserva el plazo.</p>
                    <input type="file" name="archivo_mp3" accept=".mp3,audio/mpeg" class="lucille-product-field w-full" @disabled(!empty($deadlineInfo['is_closed']))>
                    @error('archivo_mp3')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Portada del episodio (opcional si el máster tiene carátula)</label>
                    <input type="file" name="imagen_portada" accept="image/*" class="lucille-product-field w-full" @disabled(!empty($deadlineInfo['is_closed']))>
                    @error('imagen_portada')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="pt-4 flex items-center gap-4">
                @if (!empty($deadlineInfo['is_closed']))
                    <button type="button" disabled class="lucille-button opacity-50 cursor-not-allowed">Plazo de entrega cerrado</button>
                    <span class="text-xs text-rose-400">El plazo venció el {{ $deadlineInfo['deadline_formatted'] ?? '' }}. Contacta con administración para solicitar una excepción.</span>
                @else
                    <button type="submit" class="lucille-button-solid">Enviar episodio a revisión editorial</button>
                @endif
            </div>
        </form>
    </main>
</body>
</html>

