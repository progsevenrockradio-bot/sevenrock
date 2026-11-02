<div class="space-y-3">
    @forelse ($recentUploads as $upload)
        @php
            $editorialStatus = (string) ($upload->editorial_status ?: 'not_required');
            $isEditorialApproved = $upload->isEditorialApproved();

            $isProcessedInR2 = $upload->isProcessedInR2();
            $isProcessingAudio = $upload->processing_started_at !== null && $upload->processing_finished_at === null;
            $hasAudioFailed = $upload->delivery_status === 'delivery_failed' && ! $upload->isProcessedPath();

            $radioStatus = (string) ($upload->radioboss_status ?? ($upload->enviado_radioboss ? 'radioboss_verified' : 'radioboss_pending'));
            $archiveStatus = (string) ($upload->archive_org_status ?? 'archive_pending');
            $deliveryStatus = (string) ($upload->delivery_status ?? 'delivery_pending');

            $hasActivePipeline = in_array($radioStatus, ['radioboss_pending', 'processing', 'uploading'], true)
                || in_array($archiveStatus, ['archive_pending', 'archive_pending_indexing', 'processing', 'uploading'], true)
                || in_array($deliveryStatus, ['delivery_pending', 'delivery_partial', 'processing', 'uploading'], true)
                || $isProcessingAudio;

            $hasErrors = $hasAudioFailed
                || $radioStatus === 'radioboss_error'
                || $archiveStatus === 'archive_error'
                || $deliveryStatus === 'delivery_failed'
                || $editorialStatus === 'retained_incident';

            $isReady = $radioStatus === 'radioboss_verified' && in_array($archiveStatus, ['archive_verified', 'archive_skipped'], true);
        @endphp

        <article
            class="rounded-xl border border-white/5 bg-[#141418] hover:border-white/10 p-4 transition-all"
            data-status="{{ $deliveryStatus }}"
            data-podcast-state="{{ $deliveryStatus }}"
            data-podcast-refresh-active="{{ $hasActivePipeline ? '1' : '0' }}"
            x-data="{ expanded: false }"
        >
            {{-- Fila Principal: Título, Metadatos y Resumen de Estado --}}
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h3 class="font-display text-sm sm:text-base font-bold uppercase tracking-wide text-white break-words">
                            {{ $upload->live_title ?: $upload->titulo_programa }}
                        </h3>
                    </div>

                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs font-sans text-gray-400">
                        <span class="text-gray-300 font-medium">{{ $upload->masterProgram?->nombre ?? 'Sin programa maestro' }}</span>
                        <span class="text-gray-600">·</span>
                        <span class="inline-flex items-center rounded bg-white/5 px-1.5 py-0.5 text-[11px] font-mono text-gray-300">
                            Ep. {{ $upload->numero_episodio ?? 'S/N' }}
                        </span>
                        <span class="text-gray-600">·</span>
                        <span>{{ optional($upload->fecha_emision)->format('d/m/Y') ?? 'Sin fecha' }}</span>
                    </div>
                </div>

                {{-- Resumen de Estado Consolidado y Botón Detalles --}}
                <div class="flex items-center gap-2.5 shrink-0">
                    @if ($hasErrors)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-rose-500/30 bg-rose-500/10 px-2.5 py-1 text-xs font-medium text-rose-300 font-sans">
                            <span class="h-1.5 w-1.5 rounded-full bg-rose-400"></span>
                            <span>Requiere atención</span>
                        </span>
                    @elseif ($hasActivePipeline)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-xs font-medium text-amber-300 font-sans">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                            <span>En proceso</span>
                        </span>
                    @elseif ($isReady)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-300 font-sans">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                            <span>Listo en emisión</span>
                        </span>
                    @elseif ($isProcessedInR2)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-sky-500/30 bg-sky-500/10 px-2.5 py-1 text-xs font-medium text-sky-300 font-sans">
                            <span class="h-1.5 w-1.5 rounded-full bg-sky-400"></span>
                            <span>Audio en R2</span>
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-xs font-medium text-gray-400 font-sans">
                            <span class="h-1.5 w-1.5 rounded-full bg-gray-500"></span>
                            <span>Sin procesar</span>
                        </span>
                    @endif

                    <button
                        type="button"
                        @click="expanded = !expanded"
                        class="h-8 px-2.5 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-xs font-medium text-gray-300 hover:text-white transition-colors inline-flex items-center gap-1"
                        :aria-expanded="expanded"
                        aria-label="Ver detalles técnicos de {{ $upload->live_title ?: $upload->titulo_programa }}"
                    >
                        <span x-text="expanded ? 'Menos' : 'Detalles'"></span>
                        <svg class="h-3 w-3 text-gray-400 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Fila de Acciones: Descargar Visible, Reproductor, Más Acciones y Eliminar Separado --}}
            <div class="mt-3 pt-3 border-t border-white/5 flex flex-wrap items-center justify-between gap-2.5">
                <div class="flex flex-wrap items-center gap-2">
                    {{-- Acción Principal Visible: Descargar MP3 --}}
                    <a
                        href="{{ route('admin.podcast-uploads.download', $upload) }}"
                        class="h-8 px-3 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-xs font-medium text-gray-200 hover:text-white transition-colors inline-flex items-center gap-1.5 font-sans"
                        title="Descargar {{ $upload->readableDownloadFilename() }}"
                    >
                        <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        <span>Descargar MP3</span>
                    </a>

                    {{-- Reproductor de Audio Inline (si está disponible) --}}
                    @if ($upload->audioPlaybackUrl() !== '')
                        <audio
                            preload="none"
                            controls
                            src="{{ $upload->audioPlaybackUrl() }}"
                            data-podcast-playback-url="{{ $upload->audioPlaybackUrl() }}"
                            class="h-8 max-w-[190px] sm:max-w-[240px]"
                        ></audio>
                    @endif

                    {{-- Desplegable Accesible: Más Acciones --}}
                    <div class="relative" x-data="{ menuOpen: false }">
                        <button
                            type="button"
                            @click="menuOpen = !menuOpen"
                            class="h-8 px-3 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-xs font-medium text-gray-200 hover:text-white transition-colors inline-flex items-center gap-1.5 font-sans"
                            :aria-expanded="menuOpen"
                            aria-label="Más acciones para {{ $upload->live_title ?: $upload->titulo_programa }}"
                        >
                            <span>Más acciones</span>
                            <svg class="h-3 w-3 text-gray-400 transition-transform" :class="menuOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div
                            x-show="menuOpen"
                            x-cloak
                            @click.outside="menuOpen = false"
                            @keydown.escape.window="menuOpen = false"
                            class="absolute left-0 mt-1.5 w-56 rounded-xl border border-white/10 bg-[#16161a] p-1 shadow-2xl z-30 text-xs font-sans"
                        >
                            <a
                                href="{{ route('admin.podcast-uploads.editorial.show', $upload) }}"
                                class="flex items-center gap-2 px-3 py-2 rounded-lg text-gray-300 hover:bg-white/5 hover:text-white transition-colors"
                            >
                                <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                <span>Revisión editorial</span>
                            </a>

                            <div class="border-t border-white/5 my-1"></div>

                            <form action="{{ route('admin.podcast-uploads.retry', $upload) }}" method="POST">
                                @csrf
                                <button
                                    type="submit"
                                    name="target"
                                    value="all"
                                    class="w-full text-left px-3 py-2 rounded-lg text-gray-300 hover:bg-white/5 hover:text-white transition-colors"
                                >
                                    Reintentar pipeline completo
                                </button>
                                <button
                                    type="submit"
                                    name="target"
                                    value="radioboss"
                                    class="w-full text-left px-3 py-2 rounded-lg text-gray-300 hover:bg-white/5 hover:text-white transition-colors"
                                >
                                    Reintentar solo RadioBOSS
                                </button>
                                <button
                                    type="submit"
                                    name="target"
                                    value="archive"
                                    class="w-full text-left px-3 py-2 rounded-lg text-gray-300 hover:bg-white/5 hover:text-white transition-colors"
                                >
                                    Reintentar solo Archive.org
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Botón Eliminar: Separado y con Estilo de Peligro Suave --}}
                <form
                    action="{{ route('admin.podcast-uploads.destroy', $upload->id) }}"
                    method="POST"
                    data-confirm="¿Seguro que deseas eliminar permanentemente el episodio '{{ addslashes($upload->live_title ?: $upload->titulo_programa) }}'? Esta acción eliminará el registro y sus archivos asociados."
                    data-confirm-title="Eliminar emisión"
                    data-confirm-action="Eliminar"
                    data-confirm-tone="danger"
                >
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        class="h-8 px-3 rounded-lg border border-rose-900/40 bg-rose-950/20 hover:bg-rose-900/30 text-rose-300 hover:text-white text-xs font-medium transition-colors inline-flex items-center gap-1.5 font-sans"
                    >
                        <svg class="h-3.5 w-3.5 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span>Eliminar</span>
                    </button>
                </form>
            </div>

            {{-- Panel Desplegable: Detalles Técnicos, Badges Operativos y Diagnóstico --}}
            <div
                x-show="expanded"
                x-cloak
                x-transition.opacity.duration.150ms
                class="mt-3 rounded-xl border border-white/5 bg-black/40 p-4 space-y-3"
            >
                {{-- Nombre de archivo legible --}}
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 text-xs">
                    <span class="text-gray-400 font-sans">Archivo generado:</span>
                    <span class="font-mono text-gray-200 text-[11px] break-all select-all" data-podcast-readable-filename="{{ $upload->readableDownloadFilename() }}">
                        {{ $upload->readableDownloadFilename() }}
                    </span>
                </div>

                {{-- Desglose de Servicios Operativos --}}
                <div class="pt-2 border-t border-white/5">
                    <span class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2 font-sans">
                        Servicios y plataformas
                    </span>
                    <div class="flex flex-wrap gap-1.5 text-[11px] font-mono">
                        {{-- 1. Revisión Editorial --}}
                        @if ($isEditorialApproved)
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-emerald-500/30 bg-emerald-500/10 text-emerald-300">
                                Editorial · Aprobado (v{{ (int) ($upload->editorial_version ?: 1) }})
                            </span>
                        @elseif ($editorialStatus === 'pending_editorial_review')
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-amber-500/30 bg-amber-500/10 text-amber-300">
                                Editorial · Pendiente revisión
                            </span>
                        @elseif ($editorialStatus === 'retained_incident')
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-rose-500/30 bg-rose-500/10 text-rose-300">
                                Editorial · Retenido
                            </span>
                        @elseif ($editorialStatus === 'changes_requested')
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-amber-500/30 bg-amber-500/10 text-amber-300">
                                Editorial · Cambios solicitados
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-white/10 bg-white/5 text-gray-400">
                                Editorial · No requerida
                            </span>
                        @endif

                        {{-- 2. Audio (R2) --}}
                        @if ($isProcessedInR2)
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-emerald-500/30 bg-emerald-500/10 text-emerald-300">
                                Audio · Procesado en R2
                            </span>
                        @elseif ($isProcessingAudio)
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-amber-500/30 bg-amber-500/10 text-amber-300 animate-pulse">
                                Audio · Procesando...
                            </span>
                        @elseif ($hasAudioFailed)
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-rose-500/30 bg-rose-500/10 text-rose-300">
                                Audio · Falló proceso
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-white/10 bg-white/5 text-gray-400">
                                Audio · Pendiente
                            </span>
                        @endif

                        {{-- 3. RadioBOSS --}}
                        @if ($radioStatus === 'radioboss_verified')
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-emerald-500/30 bg-emerald-500/10 text-emerald-300">
                                RadioBOSS · Verificado (FTP)
                            </span>
                        @elseif ($radioStatus === 'radioboss_error')
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-rose-500/30 bg-rose-500/10 text-rose-300">
                                RadioBOSS · Error
                            </span>
                        @elseif (in_array($radioStatus, ['radioboss_pending', 'processing', 'uploading'], true))
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-amber-500/30 bg-amber-500/10 text-amber-300">
                                RadioBOSS · Pendiente
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-white/10 bg-white/5 text-gray-400">
                                RadioBOSS · Pendiente
                            </span>
                        @endif

                        @if ($upload->schedule_inclusion_status === 'scheduled_verified')
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-emerald-500/30 bg-emerald-500/10 text-emerald-300">
                                Parrilla · Programado
                            </span>
                        @endif

                        {{-- 4. Archive.org --}}
                        @if ($archiveStatus === 'archive_verified')
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-emerald-500/30 bg-emerald-500/10 text-emerald-300">
                                Archive · Verificado
                            </span>
                        @elseif ($archiveStatus === 'archive_pending_indexing')
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-amber-500/30 bg-amber-500/10 text-amber-300">
                                Archive · Indexando
                            </span>
                        @elseif ($archiveStatus === 'archive_error')
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-rose-500/30 bg-rose-500/10 text-rose-300">
                                Archive · Error
                            </span>
                        @elseif ($archiveStatus === 'archive_skipped')
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-sky-500/30 bg-sky-500/10 text-sky-300">
                                Archive · Omitido
                            </span>
                        @elseif (in_array($archiveStatus, ['archive_pending', 'processing', 'uploading'], true))
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-amber-500/30 bg-amber-500/10 text-amber-300">
                                Archive · Pendiente
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-white/10 bg-white/5 text-gray-400">
                                Archive · Pendiente
                            </span>
                        @endif

                        {{-- 5. Entrega / Notificación --}}
                        @if ($deliveryStatus === 'delivery_verified')
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-emerald-500/30 bg-emerald-500/10 text-emerald-300">
                                Correo · Notificado
                            </span>
                        @elseif ($deliveryStatus === 'delivery_partial')
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-amber-500/30 bg-amber-500/10 text-amber-300">
                                Correo · Parcial
                            </span>
                        @elseif ($deliveryStatus === 'delivery_failed')
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-rose-500/30 bg-rose-500/10 text-rose-300">
                                Correo · Error envío
                            </span>
                        @elseif ($deliveryStatus === 'skipped')
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-sky-500/30 bg-sky-500/10 text-sky-300">
                                Correo · Omitido
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 border-white/10 bg-white/5 text-gray-400">
                                Correo · Pendiente
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Mensaje de estado --}}
                @if ($upload->status_message)
                    <div class="rounded-lg border border-white/5 bg-white/[0.02] p-2.5 text-xs text-gray-300 font-sans">
                        <span class="text-gray-500">Estado:</span> {{ $upload->status_message }}
                    </div>
                @endif

                {{-- Errores detallados si existen --}}
                @if ($upload->radioboss_last_error)
                    <div class="rounded-lg border border-rose-900/40 bg-rose-950/20 p-3 text-xs text-rose-300 font-sans space-y-1">
                        <div class="font-semibold text-rose-200">Error en RadioBOSS FTP:</div>
                        <p class="leading-relaxed">{{ $upload->radioboss_last_error }}</p>
                    </div>
                @endif
                @if ($upload->archive_org_last_error)
                    <div class="rounded-lg border border-rose-900/40 bg-rose-950/20 p-3 text-xs text-rose-300 font-sans space-y-1">
                        <div class="font-semibold text-rose-200">Error en Archive.org:</div>
                        <p class="leading-relaxed">{{ $upload->archive_org_last_error }}</p>
                    </div>
                @endif
            </div>
        </article>
    @empty
        <div class="rounded-xl border border-white/5 bg-[#141418] p-8 text-center">
            <p class="text-sm text-gray-400 font-sans">Todavía no hay emisiones registradas en este criterio.</p>
        </div>
    @endforelse

    @if (method_exists($recentUploads, 'hasPages') && $recentUploads->hasPages())
        <div class="mt-4">
            {{ $recentUploads->links() }}
        </div>
    @endif
</div>
