<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detalle de Entrega · Portal de Conductores · Seven Rock Radio</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#151515] text-[#dcdcdc] font-sans">
    <header class="border-b border-[#2b2b2b] bg-[#081a24] px-6 py-4">
        <div class="mx-auto flex max-w-4xl items-center justify-between gap-4">
            <div>
                <p class="text-[11px] uppercase tracking-[.22em] text-[#c32720]">Portal de Conductores</p>
                <h1 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">
                    Ep. {{ $radioProgram->numero_episodio }} · {{ $radioProgram->live_title ?: $radioProgram->titulo_programa }}
                </h1>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('conductor.episodes.edit', $radioProgram) }}" class="lucille-button text-xs">Modificar entrega</a>
                <a href="{{ route('conductor.episodes.index') }}" class="lucille-button text-xs">Volver</a>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-4xl space-y-6 p-6">
        @if (session('status'))
            <div class="border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm text-emerald-200" data-conductor-status-banner>
                {{ session('status') }}
            </div>
        @endif

        {{-- COMPARTIR Y DESCARGAR (SOLO CUANDO ESTÁ APROBADO Y PROCESADO EN R2) --}}
        @if ($canShareAndDownload)
            <section class="border border-emerald-500/30 bg-emerald-950/20 p-6 space-y-4" data-share-and-download-panel>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <span class="text-[10px] uppercase tracking-[.22em] text-emerald-400 font-bold block">Episodio Aprobado y Disponible</span>
                        <h2 class="font-display text-lg uppercase tracking-[.1em] text-white">Compartir y Descargar Archivo Definitivo</h2>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('conductor.episodes.download', $radioProgram) }}" class="lucille-button-solid text-xs">
                            Descargar programa (MP3)
                        </a>
                        <button type="button" id="btnCopyShareLink" class="lucille-button text-xs" onclick="copyConductorShareLink()">
                            Copiar enlace para compartir
                        </button>
                    </div>
                </div>

                <div class="border-t border-emerald-500/20 pt-3 text-xs text-[#b0b0b0] space-y-2">
                    <p>
                        El archivo de audio definitivo está alojado en Cloudflare R2 con el nombre oficial <code>{{ $radioProgram->readableDownloadFilename() }}</code> y metadatos incrustados.
                    </p>
                    <div id="copyFeedback" class="hidden text-emerald-300 font-semibold text-xs">
                        ✓ ¡Enlace copiado al portapapeles!
                    </div>
                    <div id="fallbackCopyContainer" class="hidden pt-2">
                        <label for="shareLinkInput" class="block text-[11px] uppercase tracking-wider text-[#9a9a9a] mb-1">
                            Enlace público del episodio (selecciona y pulsa Ctrl+C para copiar):
                        </label>
                        <div class="flex gap-2">
                            <input id="shareLinkInput" type="text" readonly value="{{ $publicShareUrl }}" class="lucille-product-field text-xs w-full select-all">
                            <a href="{{ $publicShareUrl }}" target="_blank" rel="noopener" class="lucille-button text-xs whitespace-nowrap">Abrir ficha</a>
                        </div>
                    </div>
                </div>
            </section>

            <script>
                function copyConductorShareLink() {
                    const shareUrl = @json($publicShareUrl);
                    const feedback = document.getElementById('copyFeedback');
                    const fallback = document.getElementById('fallbackCopyContainer');
                    const input = document.getElementById('shareLinkInput');

                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(shareUrl).then(() => {
                            if (feedback) {
                                feedback.classList.remove('hidden');
                                setTimeout(() => feedback.classList.add('hidden'), 4000);
                            }
                        }).catch(() => {
                            showFallback();
                        });
                    } else {
                        showFallback();
                    }

                    function showFallback() {
                        if (fallback) fallback.classList.remove('hidden');
                        if (input) {
                            input.focus();
                            input.select();
                        }
                    }
                }
            </script>
        @endif

        {{-- INFORMACIÓN DE PLAZO DE ENTREGA Y EMISIÓN --}}
        <section class="border border-[#2b2b2b] bg-[#101012] p-6 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="font-display uppercase tracking-[.15em] text-[#dcdcdc] text-sm">Plazo de Entrega y Emisión</span>
                @if ($hasLateException)
                    <span class="text-[11px] font-semibold text-emerald-300 bg-emerald-500/20 border border-emerald-500/40 px-2 py-0.5">Excepción tardía activa</span>
                @elseif (empty($deadlineInfo) || ($deadlineInfo['status'] ?? '') === 'undetermined')
                    <span class="text-[11px] text-amber-400 bg-amber-500/10 border border-amber-500/30 px-2 py-0.5">Horario no determinado</span>
                @elseif (!empty($deadlineInfo['is_closed']))
                    <span class="text-[11px] font-semibold text-rose-300 bg-rose-500/20 border border-rose-500/40 px-2 py-0.5">Plazo cerrado ({{ $deadlineInfo['remaining_human'] }})</span>
                @else
                    <span class="text-[11px] font-semibold text-emerald-300 bg-emerald-500/20 border border-emerald-500/40 px-2 py-0.5">{{ $deadlineInfo['remaining_human'] }}</span>
                @endif
            </div>

            @if (!empty($deadlineInfo) && in_array(($deadlineInfo['status'] ?? ''), ['ok', 'calculated'], true))
                <div class="grid gap-3 md:grid-cols-2 text-xs bg-[#151515] p-3 border border-[#242424]">
                    <div>
                        <span class="text-[11px] uppercase tracking-[.12em] text-[#7b7b7b] block">Fecha y hora de emisión programada</span>
                        <span class="text-[#dcdcdc] font-semibold">{{ $deadlineInfo['emission_formatted'] }}</span>
                        @if (!empty($deadlineInfo['crosses_midnight']))
                            <span class="text-[10px] text-amber-300 block">Cruza medianoche respecto al día base.</span>
                        @endif
                    </div>
                    <div>
                        <span class="text-[11px] uppercase tracking-[.12em] text-[#7b7b7b] block">Límite estricto de entrega</span>
                        <span class="text-[#dcdcdc] font-semibold">{{ $deadlineInfo['deadline_formatted'] }} (cierre obligatorio {{ $deadlineInfo['cutoff_human'] ?? '24 horas' }} antes)</span>
                    </div>
                </div>
            @endif

            @if ($hasLateException)
                <div class="text-xs text-emerald-300 bg-emerald-500/10 border border-emerald-500/30 p-2.5">
                    <strong>Excepción administrativa de entrega concedida:</strong>
                    {{ $radioProgram->late_submission_reason }}
                </div>
            @endif

            <div class="text-[11px] text-[#9a9a9a] space-y-1">
                <p>• <strong class="text-amber-300">Petición promocional:</strong> Se solicita comunicar el tema con al menos 24 horas de antelación para preparar la promoción en redes.</p>
                <p>• <strong>Aviso editorial:</strong> La entrega de un episodio no equivale a su aprobación editorial ni a su emisión en RadioBOSS. Requiere revisión editorial y validación técnica antes de su puesta al aire.</p>
            </div>
        </section>

        <section class="border border-[#2b2b2b] bg-[#101012] p-6 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Estado actual de la entrega</p>
                    <h2 class="mt-1 font-display text-lg uppercase tracking-[.1em] text-amber-300">
                        @if ($radioProgram->editorial_status === 'pending_editorial_review')
                            Recibido, pendiente de revisión editorial
                        @elseif ($radioProgram->editorial_status === 'retained_incident')
                            Retenido para revisión editorial
                        @elseif ($radioProgram->editorial_status === 'approved')
                            Aprobado editorialmente
                        @else
                            {{ $radioProgram->editorial_status }}
                        @endif
                    </h2>
                </div>
                <div class="text-xs text-[#9a9a9a]">
                    Versión editorial: <strong>v{{ (int) ($radioProgram->editorial_version ?: 1) }}</strong>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-4 pt-3 border-t border-[#242424] text-xs">
                @foreach ($operationalStates as $key => $stateInfo)
                    <div class="border border-[#242424] bg-[#151515] p-3">
                        <div class="uppercase tracking-[.12em] text-[#7b7b7b]">{{ $stateInfo['label'] }}</div>
                        <div class="mt-1 font-semibold text-[#dcdcdc]">{{ $stateInfo['description'] }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="border border-[#2b2b2b] bg-[#101012] p-6 space-y-4">
            <h3 class="font-display text-base uppercase tracking-[.12em] text-[#dcdcdc]">Contenido entregado</h3>
            <div class="space-y-3 text-sm">
                <div>
                    <span class="text-xs uppercase tracking-[.15em] text-[#7b7b7b] block">Título del episodio</span>
                    <p class="text-[#dcdcdc]">{{ $radioProgram->live_title ?: $radioProgram->titulo_programa }}</p>
                </div>
                <div>
                    <span class="text-xs uppercase tracking-[.15em] text-[#7b7b7b] block">Resumen corto</span>
                    <p class="text-[#dcdcdc]">{{ $radioProgram->editorial_summary }}</p>
                </div>
                <div>
                    <span class="text-xs uppercase tracking-[.15em] text-[#7b7b7b] block">Descripción / Reseña</span>
                    <p class="text-[#dcdcdc] whitespace-pre-line">{{ $radioProgram->resena }}</p>
                </div>
            </div>
        </section>
    </main>
</body>
</html>

