@extends('admin.layouts.app')

@section('title', 'Revisión Editorial de Episodio')

@section('content')
<div class="space-y-8">
    <div class="flex flex-wrap items-start justify-between gap-4 border border-[#2b2b2b] bg-[#101012] p-6">
        <div>
            <p class="text-xs uppercase tracking-[.22em] text-[#c32720]">Control Editorial de Podcasts</p>
            <h1 class="mt-1 font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">
                {{ $radioProgram->masterProgram?->nombre ?? $radioProgram->titulo_programa }} · Ep. {{ $radioProgram->numero_episodio }}
            </h1>
            <p class="mt-1 text-xs text-[#9a9a9a]">
                Entregado por: {{ $radioProgram->submittedBy?->name ?? $radioProgram->conductor }} ·
                Versión editorial actual: <strong>v{{ (int) ($radioProgram->editorial_version ?: 1) }}</strong> ·
                Carpeta R2: <code>programas_procesados/{{ $radioProgram->storageFolderSlug() }}/</code>
            </p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.podcast-uploads.download', $radioProgram) }}" class="lucille-button text-xs">Descargar MP3 ({{ $radioProgram->readableDownloadFilename() }})</a>
            <a href="{{ route('admin.podcast-uploads.index') }}" class="lucille-button text-xs">Volver a Podcast Uploads</a>
        </div>
    </div>

    @if (session('status'))
        <div class="border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    {{-- 4 ESTADOS OPERATIVOS DIFERENCIADOS --}}
    <section class="border border-[#2b2b2b] bg-[#101012] p-6">
        <h2 class="font-display text-base uppercase tracking-[.12em] text-[#dcdcdc]">Estados Operativos Independientes</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-4">
            @foreach ($operationalStates as $key => $state)
                <div class="border border-[#242424] bg-[#151515] p-4" data-operational-state="{{ $key }}">
                    <div class="text-[11px] uppercase tracking-[.16em] text-[#9a9a9a]">{{ $state['label'] }}</div>
                    <div class="mt-1 font-display text-sm uppercase tracking-[.08em] text-white">{{ $state['state'] }}</div>
                    <p class="mt-2 text-xs text-[#9d9d9d]">{{ $state['description'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- COMPROBACIONES OBJETIVAS VS RECOMENDACIONES EDITORIALES --}}
    <section class="grid gap-6 md:grid-cols-2">
        <div class="border border-[#2b2b2b] bg-[#101012] p-6 space-y-3">
            <h2 class="font-display text-base uppercase tracking-[.12em] text-[#dcdcdc]">1. Comprobaciones Objetivas (Campos, formatos, fechas)</h2>
            @php
                $objItems = $evaluation['objective_checks']['items'] ?? [];
            @endphp
            <ul class="space-y-2 text-xs">
                @foreach ($objItems as $item)
                    <li class="flex items-start justify-between gap-3 border-b border-[#1f1f1f] pb-2">
                        <span class="text-[#dcdcdc]">{{ $item['message'] ?? $item['code'] }}</span>
                        <span class="uppercase px-2 py-0.5 rounded {{ !empty($item['passed']) ? 'bg-emerald-500/10 text-emerald-300' : 'bg-rose-500/10 text-rose-300' }}">
                            {{ !empty($item['passed']) ? 'OK' : 'Fallo' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="border border-[#2b2b2b] bg-[#101012] p-6 space-y-3">
            <h2 class="font-display text-base uppercase tracking-[.12em] text-[#dcdcdc]">2. Recomendaciones Editoriales (Criterio humano)</h2>
            <p class="text-[11px] text-amber-300/90 border border-amber-500/30 bg-amber-500/10 p-2.5">
                {{ $evaluation['disclaimer'] ?? 'Las observaciones y propuestas generadas por el asistente son orientativas y no constituyen garantía de exactitud.' }}
            </p>
            @php
                $recItems = $evaluation['editorial_recommendations']['items'] ?? [];
            @endphp
            @if (count($recItems) > 0)
                <ul class="space-y-2 text-xs text-[#dcdcdc]">
                    @foreach ($recItems as $rec)
                        <li class="border border-[#242424] bg-[#151515] p-3">
                            <strong class="uppercase text-amber-300">[{{ $rec['field'] ?? 'general' }}]</strong> {{ $rec['message'] ?? '' }}
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-xs text-emerald-300">La entrega cumple los criterios editoriales sin observaciones adicionales. Se conserva el texto del conductor sin reescritura automática.</p>
            @endif
        </div>
    </section>

    {{-- COMPARATIVA ORIGINAL DEL CONDUCTOR VS PROPUESTA IA --}}
    <section class="border border-[#2b2b2b] bg-[#101012] p-6 space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-display text-base uppercase tracking-[.12em] text-[#dcdcdc]">Comparativa: Texto Original del Conductor vs Propuesta IA</h2>
                <p class="text-xs text-[#9a9a9a]">El texto original entregado por el conductor se conserva íntegro para comparar antes de aceptar, modificar o descartar cualquier sugerencia.</p>
            </div>
            <form action="{{ route('admin.podcast-uploads.editorial.ai-proposal', $radioProgram) }}" method="POST">
                @csrf
                <button type="submit" class="lucille-button text-xs">Solicitar propuesta IA según Guía del Programa</button>
            </form>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div class="border border-[#242424] bg-[#151515] p-4 space-y-3">
                <h3 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">Original del Conductor (Conservado)</h3>
                <div class="text-xs space-y-2">
                    <div><strong class="text-[#9a9a9a]">Título:</strong> {{ $originalPayload['live_title'] ?? $radioProgram->live_title }}</div>
                    <div><strong class="text-[#9a9a9a]">Resumen:</strong> {{ $originalPayload['editorial_summary'] ?? $radioProgram->editorial_summary }}</div>
                    <div><strong class="text-[#9a9a9a]">Descripción:</strong> <div class="mt-1 whitespace-pre-line text-[#c7c7c7]">{{ $originalPayload['resena'] ?? $radioProgram->resena }}</div></div>
                </div>
            </div>

            <div class="border border-[#242424] bg-[#151515] p-4 space-y-3">
                <h3 class="font-display text-sm uppercase tracking-[.12em] text-amber-300">Propuesta Asistida por IA</h3>
                @if (!empty($aiProposal))
                    <p class="text-[11px] text-[#9a9a9a]">{{ $aiProposal['disclaimer'] ?? '' }}</p>
                    <div class="text-xs space-y-2">
                        <div><strong class="text-[#9a9a9a]">Título sugerido:</strong> {{ $aiProposal['proposed']['live_title'] ?? '' }}</div>
                        <div><strong class="text-[#9a9a9a]">Resumen sugerido:</strong> {{ $aiProposal['proposed']['editorial_summary'] ?? '' }}</div>
                        <div><strong class="text-[#9a9a9a]">Descripción sugerida:</strong> <div class="mt-1 whitespace-pre-line text-[#c7c7c7]">{{ $aiProposal['proposed']['resena'] ?? '' }}</div></div>
                        <div><strong class="text-[#9a9a9a]">Estado propuesta:</strong> {{ $aiProposal['status'] ?? 'pending_decision' }}</div>
                    </div>

                    <form action="{{ route('admin.podcast-uploads.editorial.proposal-decision', $radioProgram) }}" method="POST" class="pt-3 border-t border-[#242424] flex flex-wrap gap-2">
                        @csrf
                        <button type="submit" name="decision" value="accept" class="lucille-button-solid text-xs">Aceptar propuesta</button>
                        <button type="submit" name="decision" value="discard" class="lucille-button text-xs">Descartar propuesta (conservar conductor)</button>
                    </form>
                @else
                    <p class="text-xs text-[#7b7b7b]">No se ha solicitado ninguna propuesta de IA para esta entrega.</p>
                @endif
            </div>
        </div>
    </section>

    {{-- DESTINO Y VALIDACIÓN TÉCNICA EN RADIOBOSS --}}
    <section class="border border-[#2b2b2b] bg-[#101012] p-6 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-[.18em] text-[#c32720]">Parámetros Técnicos de Transmisión</p>
                <h2 class="font-display text-base uppercase tracking-[.12em] text-[#dcdcdc]">Destino y Nombre Técnico en RadioBOSS</h2>
            </div>
            @if ($radiobossAnalysis['is_delivered'])
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded bg-sky-500/10 text-sky-300 border border-sky-500/30">
                    <span class="h-1.5 w-1.5 rounded-full bg-sky-400"></span>
                    Entregado y Verificado en RadioBOSS
                </span>
            @elseif ($radiobossAnalysis['has_confirmed'])
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded bg-emerald-500/10 text-emerald-300 border border-emerald-500/30">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                    Nombre Técnico Confirmado por Admin
                </span>
            @elseif ($radiobossAnalysis['original_already_safe'])
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded bg-emerald-500/10 text-emerald-300 border border-emerald-500/30">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                    Nombre ASCII Seguro (No requiere confirmación)
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded bg-amber-500/10 text-amber-300 border border-amber-500/30">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                    Requiere Confirmación Técnica
                </span>
            @endif
        </div>

        <div class="grid gap-4 md:grid-cols-2 text-xs bg-[#151515] p-4 border border-[#242424]">
            <div class="space-y-1.5">
                <div><strong class="text-[#9a9a9a]">Carpeta remota RadioBOSS:</strong> <code class="text-[#dcdcdc]">{{ $radiobossAnalysis['predicted_folder'] ?? 'No determinada' }}</code></div>
                <div><strong class="text-[#9a9a9a]">Nombre derivado original:</strong> <code class="text-[#dcdcdc]">{{ $radiobossAnalysis['original_filename'] }}</code></div>
                <div><strong class="text-[#9a9a9a]">Ruta remota efectiva prevista:</strong> <code class="text-amber-300">{{ $radiobossAnalysis['full_remote_path'] }}</code></div>
            </div>
            <div class="space-y-1.5">
                @if ($radiobossAnalysis['is_delivered'])
                    <p class="text-sky-300/90 leading-relaxed">
                        El archivo físico de emisión ya está entregado y verificado en RadioBOSS en <code class="text-white">{{ $radioProgram->radioboss_metadata['remote_path'] ?? $radiobossAnalysis['full_remote_path'] }}</code>. La edición de ficha posterior es exclusivamente editorial y no renombrará silenciosamente el archivo remoto en la emisora.
                    </p>
                @elseif ($radiobossAnalysis['needs_confirmation'])
                    <div class="text-amber-300 space-y-1">
                        <p class="font-semibold">El nombre original contiene caracteres incompatibles con el FTP de RadioBOSS:</p>
                        <ul class="list-disc pl-4 text-[11px] text-[#c7c7c7] space-y-0.5">
                            @foreach ($radiobossAnalysis['issues'] as $issue)
                                <li>{{ $issue }}</li>
                            @endforeach
                        </ul>
                        <p class="pt-1 text-[11px] text-emerald-300">
                            Propuesta ASCII segura generada: <strong>{{ $radiobossAnalysis['safe_proposal'] }}</strong>
                        </p>
                    </div>
                @else
                    <p class="text-emerald-300/90 leading-relaxed">
                        El nombre técnico es 100% compatible con ASCII y el sistema de archivos del servidor FTP. No se requiere intervención manual previa a la entrega.
                    </p>
                @endif
            </div>
        </div>
    </section>

    {{-- EDICIÓN MANUAL Y CONTROL DE PORTADA / AUDIO --}}
    <section class="border border-[#2b2b2b] bg-[#101012] p-6 space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-display text-base uppercase tracking-[.12em] text-[#dcdcdc]">Edición de Ficha y Datos del Episodio</h2>
                <p class="text-xs text-[#9a9a9a]">Diferenciación estricta entre campos editoriales (web, ficha pública y catálogo) y campos técnicos del archivo.</p>
            </div>
            @if ($isDelivered)
                <span class="text-xs text-amber-300/90 bg-amber-500/10 border border-amber-500/30 px-3 py-1">
                    Episodio entregado: edición limitada a textos editoriales
                </span>
            @endif
        </div>

        <form action="{{ route('admin.podcast-uploads.editorial.update', $radioProgram) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- BLOQUE 1: CAMPOS EDITORIALES --}}
            <div class="space-y-4 border border-[#242424] bg-[#141416] p-4">
                <div class="flex items-center justify-between border-b border-[#242424] pb-2">
                    <h3 class="font-display text-xs uppercase tracking-[.15em] text-emerald-400">1. Campos Editoriales (Web, Ficha Pública y Catálogo)</h3>
                    <span class="text-[10px] text-emerald-300/80 bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">Modificables en cualquier momento sin alterar audios</span>
                </div>

                <div>
                    <label class="mb-1 flex items-center justify-between text-xs uppercase tracking-[.15em] text-[#9a9a9a]">
                        <span>Título de emisión</span>
                        <span class="text-[10px] text-emerald-400 font-mono">[EDITORIAL]</span>
                    </label>
                    <input type="text" name="live_title" value="{{ old('live_title', $radioProgram->live_title ?: $radioProgram->titulo_programa) }}" class="lucille-product-field w-full" required>
                </div>

                <div>
                    <label class="mb-1 flex items-center justify-between text-xs uppercase tracking-[.15em] text-[#9a9a9a]">
                        <span>Resumen corto / sumario editorial</span>
                        <span class="text-[10px] text-emerald-400 font-mono">[EDITORIAL]</span>
                    </label>
                    <textarea name="editorial_summary" rows="2" class="lucille-product-field w-full" required>{{ old('editorial_summary', $radioProgram->editorial_summary) }}</textarea>
                </div>

                <div>
                    <label class="mb-1 flex items-center justify-between text-xs uppercase tracking-[.15em] text-[#9a9a9a]">
                        <span>Descripción / Reseña completa</span>
                        <span class="text-[10px] text-emerald-400 font-mono">[EDITORIAL]</span>
                    </label>
                    <textarea name="resena" rows="4" class="lucille-product-field w-full" required>{{ old('resena', $radioProgram->resena) }}</textarea>
                </div>

                <div>
                    <label class="mb-1 flex items-center justify-between text-xs uppercase tracking-[.15em] text-[#9a9a9a]">
                        <span>Invitado especial / Biografía o nota del invitado</span>
                        <span class="text-[10px] text-emerald-400 font-mono">[EDITORIAL]</span>
                    </label>
                    <textarea name="biografia_invitado" rows="2" class="lucille-product-field w-full" placeholder="Nombre del invitado especial, trayectoria o notas biográficas">{{ old('biografia_invitado', $radioProgram->biografia_invitado) }}</textarea>
                </div>
            </div>

            {{-- BLOQUE 2: CAMPOS TÉCNICOS Y DE EMISIÓN --}}
            <div class="space-y-4 border border-[#242424] bg-[#141416] p-4">
                <div class="flex items-center justify-between border-b border-[#242424] pb-2">
                    <h3 class="font-display text-xs uppercase tracking-[.15em] text-sky-400">2. Parámetros Técnicos y Programación RadioBOSS</h3>
                    <span class="text-[10px] text-sky-300/80 bg-sky-500/10 px-2 py-0.5 rounded border border-sky-500/20">Definen nomenclatura y transmisión física</span>
                </div>

                @if (! $isDelivered)
                    <div>
                        <label class="mb-1 flex items-center justify-between text-xs uppercase tracking-[.15em] text-[#9a9a9a]">
                            <span>Nombre técnico de archivo en RadioBOSS (Pre-entrega)</span>
                            <span class="text-[10px] text-sky-400 font-mono">[TÉCNICO]</span>
                        </label>
                        <input
                            type="text"
                            name="radioboss_custom_filename"
                            value="{{ old('radioboss_custom_filename', $radiobossAnalysis['confirmed_filename'] ?: ($radiobossAnalysis['original_already_safe'] ? $radiobossAnalysis['original_filename'] : $radiobossAnalysis['safe_proposal'])) }}"
                            class="lucille-product-field w-full font-mono text-xs"
                            placeholder="Ej. 014.- CODIGO ZONA MISTERIO 08-11-2026.mp3"
                        >
                        <p class="mt-1 text-[11px] text-[#7b7b7b]">
                            Propuesta ASCII segura generada automáticamente: <code class="text-amber-300">{{ $radiobossAnalysis['safe_proposal'] }}</code>. Guarda la ficha para confirmar este nombre antes de aprobar.
                        </p>
                    </div>
                @else
                    <div class="text-xs text-[#9a9a9a] space-y-1 bg-[#101012] p-3 border border-[#242424]">
                        <div><strong class="text-[#dcdcdc]">Nombre físico en RadioBOSS:</strong> <code class="text-emerald-300">{{ basename((string) data_get($radioProgram->radioboss_metadata, 'remote_path', $radiobossAnalysis['effective_filename'])) }}</code></div>
                        <p class="text-[11px] text-amber-300/90">Este archivo ya fue emitido y verificado. No se renombra silenciosamente para preservar la integridad de la parrilla de la emisora.</p>
                    </div>
                @endif

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs uppercase tracking-[.15em] text-[#9a9a9a]">Estado de inclusión en programación RadioBOSS</label>
                        <select name="schedule_inclusion_status" class="lucille-product-field lucille-select-field w-full">
                            <option value="pending_schedule" @selected(($radioProgram->schedule_inclusion_status ?? 'pending_schedule') === 'pending_schedule')>Pendiente de inclusión en parrilla</option>
                            <option value="scheduled_in_radioboss" @selected(($radioProgram->schedule_inclusion_status ?? '') === 'scheduled_in_radioboss')>Incluido en programación de RadioBOSS</option>
                            <option value="manual_schedule_required" @selected(($radioProgram->schedule_inclusion_status ?? '') === 'manual_schedule_required')>Requiere ajuste manual de horario</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs uppercase tracking-[.15em] text-[#9a9a9a]">Número de episodio</label>
                        <input type="number" name="numero_episodio" min="1" value="{{ old('numero_episodio', $radioProgram->numero_episodio) }}" class="lucille-product-field w-full" {{ $isDelivered ? 'readonly' : '' }}>
                        @if ($isDelivered)
                            <p class="text-[10px] text-[#7b7b7b] mt-0.5">Fijado por entrega previa.</p>
                        @endif
                    </div>
                </div>

                @if (! $isDelivered)
                    <div class="grid gap-4 md:grid-cols-2 border-t border-[#242424] pt-4">
                        <div>
                            <label class="mb-1 block text-xs uppercase tracking-[.15em] text-[#9a9a9a]">Cambiar portada (opcional)</label>
                            <input type="file" name="imagen_portada" accept="image/*" class="lucille-product-field w-full">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs uppercase tracking-[.15em] text-[#9a9a9a]">Alcance del cambio de portada</label>
                            <select name="cover_update_scope" class="lucille-product-field lucille-select-field w-full">
                                <option value="">-- Seleccionar si subes portada --</option>
                                <option value="sheet_only">Solo actualizar ficha del episodio</option>
                                <option value="reembed_and_redistribute">Incrustar en MP3 definitivo y redistribuir</option>
                            </select>
                        </div>
                    </div>

                    <div class="border-t border-[#242424] pt-4 space-y-2">
                        <label class="mb-1 block text-xs uppercase tracking-[.15em] text-[#9a9a9a]">Reemplazar archivo MP3 (opcional)</label>
                        <input type="file" name="archivo_mp3" accept=".mp3,audio/mpeg" class="lucille-product-field w-full">
                        @if ($radioProgram->hasPreparedBroadcastAudio())
                            <label class="flex items-center gap-2 text-xs text-amber-300">
                                <input type="checkbox" name="confirm_replace_prepared_audio" value="1" class="h-4 w-4">
                                Autorizo expresamente reemplazar el audio que ya estaba preparado para emisión.
                            </label>
                        @endif
                    </div>
                @endif
            </div>

            <button type="submit" class="lucille-button">Guardar cambios en la ficha</button>
        </form>
    </section>

    {{-- GESTIÓN DE PLAZOS, EXCEPCIONES Y DESCARGA PÚBLICA --}}
    @php
        $deadline = $radioProgram->emissionDeadlineInfo();
        $hasLateEx = $radioProgram->hasActiveLateSubmissionException();
    @endphp
    <section class="grid gap-6 md:grid-cols-2">
        <div class="border border-[#2b2b2b] bg-[#101012] p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-base uppercase tracking-[.12em] text-[#dcdcdc]">Excepción de Entrega Tardía</h2>
                @if ($hasLateEx)
                    <span class="text-[11px] font-semibold text-emerald-300 bg-emerald-500/20 border border-emerald-500/40 px-2 py-0.5">Excepción autorizada</span>
                @elseif (!empty($deadline['is_closed']))
                    <span class="text-[11px] font-semibold text-rose-300 bg-rose-500/20 border border-rose-500/40 px-2 py-0.5">Plazo vencido</span>
                @else
                    <span class="text-[11px] font-semibold text-emerald-300 bg-emerald-500/20 border border-emerald-500/40 px-2 py-0.5">Plazo abierto</span>
                @endif
            </div>

            <div class="text-xs text-[#9a9a9a] space-y-1 bg-[#151515] p-3 border border-[#242424]">
                <div><strong class="text-[#dcdcdc]">Emisión:</strong> {{ $deadline['emission_formatted'] ?? 'No determinada' }}</div>
                <div><strong class="text-[#dcdcdc]">Límite entrega:</strong> {{ $deadline['deadline_formatted'] ?? 'No determinado' }} (margen de cierre: {{ $deadline['cutoff_human'] ?? '24 horas' }})</div>
                <div><strong class="text-[#dcdcdc]">Estado:</strong> {{ $deadline['remaining_human'] ?? '' }}</div>
            </div>

            @if ($hasLateEx)
                <div class="text-xs text-emerald-300 bg-emerald-500/10 border border-emerald-500/30 p-3 space-y-1">
                    <p><strong>Autorizado por:</strong> {{ $radioProgram->lateSubmissionAuthorizer?->name ?? ('Usuario #' . $radioProgram->late_submission_authorized_by) }} el {{ optional($radioProgram->late_submission_authorized_at)->format('d/m/Y H:i') }}</p>
                    <p><strong>Motivo:</strong> {{ $radioProgram->late_submission_reason }}</p>
                </div>
            @else
                <form action="{{ route('admin.podcast-uploads.editorial.authorize-late-submission', $radioProgram) }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs uppercase tracking-[.15em] text-[#9a9a9a]">Motivo de la excepción para entrega tardía</label>
                        <input type="text" name="reason" class="lucille-product-field w-full" placeholder="Ej. Retraso justificado por problemas técnicos de grabación" required>
                    </div>
                    <button type="submit" class="lucille-button text-xs">Autorizar excepción para este episodio</button>
                    <p class="text-[11px] text-[#7b7b7b]">Nota: La excepción no aprueba, no publica ni envía automáticamente el episodio.</p>
                </form>
            @endif
        </div>

        <div class="border border-[#2b2b2b] bg-[#101012] p-6 space-y-4">
            <h2 class="font-display text-base uppercase tracking-[.12em] text-[#dcdcdc]">Ficha Pública y Descarga Web</h2>
            <div class="text-xs text-[#9a9a9a] space-y-2">
                <p>
                    Estado de descarga pública:
                    <strong class="{{ $radioProgram->allow_public_download ? 'text-emerald-300' : 'text-amber-300' }}">
                        {{ $radioProgram->allow_public_download ? 'Habilitada' : 'Desactivada (por defecto)' }}
                    </strong>
                </p>
                <p class="text-[11px]">
                    La descarga pública permite a los oyentes descargar el MP3 final con nombre legible y carátula incrustada directamente desde R2.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-2">
                <form action="{{ route('admin.podcast-uploads.editorial.toggle-public-download', $radioProgram) }}" method="POST">
                    @csrf
                    <button type="submit" class="lucille-button text-xs">
                        {{ $radioProgram->allow_public_download ? 'Desactivar descarga pública' : 'Activar descarga pública' }}
                    </button>
                </form>
                <a href="{{ $radioProgram->publicShareUrl() }}" target="_blank" rel="noopener" class="lucille-button text-xs">
                    Ver ficha pública del episodio ↗
                </a>
            </div>
        </div>
    </section>

    {{-- APROBACIÓN EDITORIAL Y BORRADOR SOCIAL SEPARADO --}}
    <section class="grid gap-6 md:grid-cols-2">
        <div class="border border-[#2b2b2b] bg-[#101012] p-6 space-y-4">
            <h2 class="font-display text-base uppercase tracking-[.12em] text-[#dcdcdc]">Aprobación Editorial</h2>
            <p class="text-xs text-[#9a9a9a]">
                Al aprobar esta versión (v{{ (int) ($radioProgram->editorial_version ?: 1) }}), se autoriza la generación del MP3 definitivo en R2 y su distribución a RadioBOSS. No realiza publicaciones en redes sociales.
            </p>
            <form action="{{ route('admin.podcast-uploads.editorial.approve', $radioProgram) }}" method="POST">
                @csrf
                <input type="hidden" name="version" value="{{ (int) ($radioProgram->editorial_version ?: 1) }}">
                <input type="hidden" name="vtoken" value="{{ $radioProgram->editorial_version_token }}">
                @if (!empty($radiobossAnalysis['confirmed_filename']))
                    <input type="hidden" name="radioboss_custom_filename" value="{{ $radiobossAnalysis['confirmed_filename'] }}">
                @elseif (!empty($radiobossAnalysis['safe_proposal']) && !empty($radiobossAnalysis['needs_confirmation']))
                    <input type="hidden" name="radioboss_custom_filename" value="{{ $radiobossAnalysis['safe_proposal'] }}">
                @endif
                <button type="submit" class="lucille-button-solid">Aprobar Episodio (v{{ (int) ($radioProgram->editorial_version ?: 1) }})</button>
            </form>
        </div>

        <div class="border border-[#2b2b2b] bg-[#101012] p-6 space-y-4">
            <h2 class="font-display text-base uppercase tracking-[.12em] text-[#dcdcdc]">Borrador Social (Acción separada)</h2>
            <p class="text-xs text-[#9a9a9a]">
                Generar un borrador para redes sociales es independiente de la aprobación editorial y nunca publica automáticamente.
            </p>
            <form action="{{ route('admin.podcast-uploads.editorial.social-draft', $radioProgram) }}" method="POST">
                @csrf
                <button type="submit" class="lucille-button text-xs">Generar borrador social (sin publicar)</button>
            </form>
        </div>
    </section>
</div>
@endsection

