<x-layouts.admin title="Bitácora de auditoría - {{ $themeSettings->site_name ?? 'Seven Rock Radio' }}">
    @php
        $levelLabels = [
            'info' => 'Informativo',
            'warning' => 'Advertencia',
            'error' => 'Error',
        ];
        $categoryLabels = [
            'http' => 'Petición HTTP',
            'model' => 'Cambio en modelo',
            'system' => 'Evento de sistema',
        ];
        $categories = $categories ?? array_keys($categoryLabels);
        $levels = $levels ?? array_keys($levelLabels);
        $hasFilters = collect($filters ?? [])->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty();
    @endphp

    <div class="space-y-5 font-sans">
        {{-- Cabecera compacta --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="flex items-center gap-2 text-[11px] font-medium uppercase tracking-wider text-gray-400">
                    <span>Sistema</span>
                    <span class="text-white/20">/</span>
                    <span class="text-[#c32720]">Bitácora de auditoría</span>
                </div>
                <h1 class="mt-1 font-display text-2xl font-bold uppercase tracking-wide text-[#dcdcdc]">Bitácora de auditoría</h1>
                <p class="mt-1 text-xs text-gray-400 max-w-3xl">
                    Registro cronológico de solo lectura de peticiones administrativas, cambios en modelos y eventos del sistema.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ url('/log-viewer') }}" target="_blank" rel="noopener"
                   class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3.5 text-xs font-medium text-gray-200 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                    <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                    </svg>
                    <span>Abrir visor de logs técnicos</span>
                </a>
            </div>
        </header>

        {{-- Contadores globales con alcance y periodo explícitos --}}
        <section aria-label="Resumen global de la bitácora" class="space-y-2">
            <div class="flex items-center justify-between text-[11px] text-gray-400">
                <span>Alcance de los indicadores: totales globales almacenados en la tabla de auditoría (no varían al aplicar filtros de búsqueda).</span>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <article class="rounded-xl border border-white/10 bg-[#101012] p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Total histórico</span>
                        <span class="rounded bg-white/5 px-2 py-0.5 text-[10px] text-gray-400">Global acumulado</span>
                    </div>
                    <p class="mt-2 font-display text-2xl font-bold text-[#dcdcdc]">{{ number_format($stats['total'] ?? 0) }}</p>
                    <p class="mt-0.5 text-[11px] text-gray-500">Todos los eventos conservados en la base de datos.</p>
                </article>

                <article class="rounded-xl border border-white/10 bg-[#101012] p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Actividad de hoy</span>
                        <span class="rounded bg-sky-500/10 px-2 py-0.5 text-[10px] text-sky-300">{{ now()->format('d/m/Y') }}</span>
                    </div>
                    <p class="mt-2 font-display text-2xl font-bold text-white">{{ number_format($stats['today'] ?? 0) }}</p>
                    <p class="mt-0.5 text-[11px] text-gray-500">Eventos registrados desde las 00:00 h de hoy.</p>
                </article>

                <article class="rounded-xl border border-white/10 bg-[#101012] p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-amber-300/90">Advertencias</span>
                        <span class="rounded bg-amber-500/10 px-2 py-0.5 text-[10px] text-amber-300">Histórico global</span>
                    </div>
                    <p class="mt-2 font-display text-2xl font-bold text-amber-300">{{ number_format($stats['warnings'] ?? 0) }}</p>
                    <p class="mt-0.5 text-[11px] text-gray-500">Respuestas HTTP 4xx o avisos de validación acumulados.</p>
                </article>

                <article class="rounded-xl border border-white/10 bg-[#101012] p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-red-300/90">Errores</span>
                        <span class="rounded bg-red-500/10 px-2 py-0.5 text-[10px] text-red-300">Histórico global</span>
                    </div>
                    <p class="mt-2 font-display text-2xl font-bold text-red-300">{{ number_format($stats['errors'] ?? 0) }}</p>
                    <p class="mt-0.5 text-[11px] text-gray-500">Respuestas HTTP 5xx o fallos críticos acumulados.</p>
                </article>
            </div>
        </section>

        {{-- Guía rápida de interpretación (HTTP 302, protección de datos y solo lectura) --}}
        <section aria-label="Notas de lectura de la auditoría" class="rounded-xl border border-white/10 bg-[#101012] px-4 py-3 text-xs text-gray-300">
            <div class="grid gap-3 md:grid-cols-3">
                <div class="flex items-start gap-2.5">
                    <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded bg-emerald-500/15 text-[10px] font-mono font-bold text-emerald-300">302</span>
                    <div>
                        <span class="font-semibold text-[#dcdcdc]">Código HTTP 302 (Redirección normal):</span>
                        <p class="mt-0.5 text-[11px] text-gray-400 leading-relaxed">
                            Al guardar un formulario o ejecutar una acción en Laravel, el servidor responde con <code>HTTP 302</code> para redirigir de vuelta a la vista con mensaje de confirmación. No indica un fallo.
                        </p>
                    </div>
                </div>
                <div class="flex items-start gap-2.5">
                    <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded bg-sky-500/15 text-[10px] font-mono font-bold text-sky-300">🔒</span>
                    <div>
                        <span class="font-semibold text-[#dcdcdc]">Protección de datos sensibles:</span>
                        <p class="mt-0.5 text-[11px] text-gray-400 leading-relaxed">
                            Contraseñas, tokens, claves API y secretos SMTP/FTP se enmascaran automáticamente como <code>[redacted]</code> antes de guardarse en la bitácora.
                        </p>
                    </div>
                </div>
                <div class="flex items-start gap-2.5">
                    <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded bg-white/10 text-[10px] font-mono font-bold text-gray-300">ℹ️</span>
                    <div>
                        <span class="font-semibold text-[#dcdcdc]">Registro inmutable desde el panel:</span>
                        <p class="mt-0.5 text-[11px] text-gray-400 leading-relaxed">
                            Esta pantalla es de consulta. El botón <strong>Restablecer filtros</strong> solo limpia la búsqueda en pantalla y nunca elimina registros de auditoría.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Filtros con etiquetas visibles y Restablecer filtros --}}
        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="sm:col-span-2">
                    <label for="filter-q" class="mb-1 block text-[11px] font-medium text-gray-300">Buscar por resumen, ruta, usuario o modelo</label>
                    <input type="text" id="filter-q" name="q" value="{{ $filters['q'] ?? '' }}"
                           placeholder="Ej: settings, RadioProgram, correo@dominio.com..."
                           class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                </div>

                <div>
                    <label for="filter-category" class="mb-1 block text-[11px] font-medium text-gray-300">Categoría</label>
                    <select id="filter-category" name="category"
                            class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                        <option value="">Todas las categorías</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>
                                {{ $categoryLabels[$category] ?? strtoupper($category) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="filter-event" class="mb-1 block text-[11px] font-medium text-gray-300">Evento exacto</label>
                    <input type="text" id="filter-event" name="event" value="{{ $filters['event'] ?? '' }}"
                           placeholder="Ej: request.completed"
                           class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                </div>

                <div>
                    <label for="filter-level" class="mb-1 block text-[11px] font-medium text-gray-300">Nivel</label>
                    <select id="filter-level" name="level"
                            class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                        <option value="">Todos los niveles</option>
                        @foreach ($levels as $level)
                            <option value="{{ $level }}" @selected(($filters['level'] ?? '') === $level)>
                                {{ $levelLabels[$level] ?? strtoupper($level) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-white/5">
                <p class="text-[11px] text-gray-400">
                    Mostrando <strong class="text-gray-200">{{ $logs->count() }}</strong> de <strong class="text-gray-200">{{ number_format($logs->total()) }}</strong> eventos coincidentes.
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    @if($hasFilters)
                        <a href="{{ route('admin.audit-logs.index') }}"
                           title="Limpia únicamente la búsqueda actual sin borrar registros"
                           class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3 text-xs font-medium text-gray-300 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                            <span>Restablecer filtros (no borra registros)</span>
                        </a>
                    @endif
                    <button type="submit"
                            class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-[#a61f19] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                        <span>Filtrar bitácora</span>
                    </button>
                </div>
            </div>
        </form>

        {{-- Listado compacto de eventos con detalles técnicos desplegables --}}
        <section aria-label="Registros de auditoría" class="space-y-2.5">
            @forelse ($logs as $log)
                @php
                    $statusCode = $log->status_code;
                    $isRedirect302 = $statusCode !== null && in_array((int) $statusCode, [301, 302, 303, 307, 308], true);
                    $levelBadge = match($log->level) {
                        'error' => 'border-red-500/30 bg-red-500/10 text-red-300',
                        'warning' => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
                        default => 'border-emerald-500/25 bg-emerald-500/10 text-emerald-300',
                    };
                    $hasTechnicalPayload = !empty($log->request_payload)
                        || !empty($log->changes)
                        || !empty($log->before_state)
                        || !empty($log->after_state)
                        || !empty($log->context)
                        || !empty($log->response_meta)
                        || !empty($log->url)
                        || !empty($log->user_agent);
                @endphp

                <article class="rounded-xl border border-white/10 bg-[#101012] p-3.5 transition-colors hover:border-white/15">
                    {{-- Fila compacta principal: Fecha | Responsable | Acción | Resultado --}}
                    <div class="grid gap-3 md:grid-cols-12 md:items-center text-xs">
                        {{-- 1. Fecha --}}
                        <div class="md:col-span-2 flex items-center justify-between md:block">
                            <span class="font-mono text-xs text-[#dcdcdc] font-medium">
                                {{ optional($log->created_at)->format('d/m/Y H:i:s') }}
                            </span>
                            <span class="block text-[11px] text-gray-500">
                                {{ optional($log->created_at)->diffForHumans() }}
                            </span>
                        </div>

                        {{-- 2. Responsable --}}
                        <div class="md:col-span-3 min-w-0">
                            <div class="font-medium text-[#dcdcdc] truncate">
                                {{ $log->actor_name ?: ($log->actor_type === 'system' ? 'Sistema automático' : 'Visitante / No autenticado') }}
                            </div>
                            <div class="text-[11px] text-gray-400 truncate font-mono">
                                {{ $log->actor_email ?: ($log->ip_address ?: 'Sin IP') }}
                            </div>
                        </div>

                        {{-- 3. Acción --}}
                        <div class="md:col-span-5 min-w-0">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="rounded border border-white/10 bg-white/5 px-1.5 py-0.5 text-[10px] font-medium text-gray-300">
                                    {{ $categoryLabels[$log->category] ?? strtoupper($log->category) }}
                                </span>
                                @if($log->method)
                                    <span class="rounded border border-white/10 bg-black/40 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-gray-200">
                                        {{ $log->method }}
                                    </span>
                                @endif
                                <span class="font-mono text-[11px] text-gray-400">{{ $log->event }}</span>
                            </div>
                            <p class="mt-1 font-medium text-[#dcdcdc] break-words">{{ $log->summary }}</p>
                            @if ($log->subject_type)
                                <p class="mt-0.5 text-[11px] text-gray-400 font-mono">
                                    Objeto: {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}
                                </p>
                            @endif
                        </div>

                        {{-- 4. Resultado --}}
                        <div class="md:col-span-2 flex flex-wrap md:flex-col md:items-end justify-between gap-1.5">
                            <span class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-medium {{ $levelBadge }}">
                                {{ $levelLabels[$log->level] ?? strtoupper($log->level) }}
                            </span>
                            <div class="flex items-center gap-1.5 text-[11px]">
                                @if ($statusCode !== null)
                                    <span class="font-mono text-gray-300" title="{{ $isRedirect302 ? 'Redirección normal tras procesar la acción' : 'Código de respuesta HTTP' }}">
                                        HTTP {{ $statusCode }}{{ $isRedirect302 ? ' (Redirección OK)' : '' }}
                                    </span>
                                @endif
                                @if ($log->duration_ms)
                                    <span class="text-gray-500 font-mono">· {{ $log->duration_ms }} ms</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Detalles técnicos desplegables --}}
                    @if ($hasTechnicalPayload)
                        <details class="mt-2.5 border-t border-white/5 pt-2 group">
                            <summary class="cursor-pointer list-none inline-flex items-center gap-1.5 text-[11px] font-medium text-gray-400 hover:text-white focus:outline-none focus:ring-1 focus:ring-[#c32720] rounded px-1 py-0.5">
                                <svg class="h-3.5 w-3.5 text-gray-500 transition-transform group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span>Detalles técnicos (petición, cambios y respuesta)</span>
                            </summary>

                            <div class="mt-2.5 space-y-2.5 rounded-lg border border-white/5 bg-[#151518] p-3 text-[11px]">
                                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 text-gray-400">
                                    <div>
                                        <span class="block text-[10px] uppercase tracking-wider text-gray-500">Ruta / URL</span>
                                        <span class="font-mono text-gray-200 break-all">{{ $log->route_name ?: '—' }} {{ $log->url ? '('.$log->url.')' : '' }}</span>
                                    </div>
                                    <div>
                                        <span class="block text-[10px] uppercase tracking-wider text-gray-500">Dirección IP</span>
                                        <span class="font-mono text-gray-200">{{ $log->ip_address ?: '—' }}</span>
                                    </div>
                                    <div>
                                        <span class="block text-[10px] uppercase tracking-wider text-gray-500">ID de petición</span>
                                        <span class="font-mono text-gray-200 break-all">{{ $log->request_id ?: '—' }}</span>
                                    </div>
                                    <div>
                                        <span class="block text-[10px] uppercase tracking-wider text-gray-500">Agente de usuario</span>
                                        <span class="font-mono text-gray-300 break-words line-clamp-2" title="{{ $log->user_agent }}">{{ $log->user_agent ?: '—' }}</span>
                                    </div>
                                </div>

                                <div class="grid gap-2.5 lg:grid-cols-2">
                                    @if (!empty($log->request_payload))
                                        <div class="rounded border border-white/10 bg-black/40 p-2.5">
                                            <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Datos enviados (sanitizados)</p>
                                            <pre class="max-h-48 overflow-auto whitespace-pre-wrap font-mono text-[11px] leading-relaxed text-gray-300">{{ json_encode($log->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif

                                    @if (!empty($log->changes))
                                        <div class="rounded border border-white/10 bg-black/40 p-2.5">
                                            <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Cambios aplicados</p>
                                            <pre class="max-h-48 overflow-auto whitespace-pre-wrap font-mono text-[11px] leading-relaxed text-gray-300">{{ json_encode($log->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif

                                    @if (!empty($log->before_state))
                                        <div class="rounded border border-white/10 bg-black/40 p-2.5">
                                            <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Estado anterior</p>
                                            <pre class="max-h-48 overflow-auto whitespace-pre-wrap font-mono text-[11px] leading-relaxed text-gray-300">{{ json_encode($log->before_state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif

                                    @if (!empty($log->after_state))
                                        <div class="rounded border border-white/10 bg-black/40 p-2.5">
                                            <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Estado posterior</p>
                                            <pre class="max-h-48 overflow-auto whitespace-pre-wrap font-mono text-[11px] leading-relaxed text-gray-300">{{ json_encode($log->after_state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif

                                    @if (!empty($log->context))
                                        <div class="rounded border border-white/10 bg-black/40 p-2.5">
                                            <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Contexto adicional</p>
                                            <pre class="max-h-48 overflow-auto whitespace-pre-wrap font-mono text-[11px] leading-relaxed text-gray-300">{{ json_encode($log->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif

                                    @if (!empty($log->response_meta))
                                        <div class="rounded border border-white/10 bg-black/40 p-2.5">
                                            <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Metadatos de respuesta</p>
                                            <pre class="max-h-48 overflow-auto whitespace-pre-wrap font-mono text-[11px] leading-relaxed text-gray-300">{{ json_encode($log->response_meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </details>
                    @endif
                </article>
            @empty
                <div class="rounded-xl border border-white/10 bg-[#101012] p-8 text-center">
                    <p class="text-sm font-medium text-gray-300">No se encontraron eventos de auditoría con los filtros seleccionados</p>
                    <p class="mt-1 text-xs text-gray-500">Prueba a restablecer los filtros para ver el historial completo.</p>
                </div>
            @endforelse
        </section>

        @if ($logs->hasPages())
            <div class="pt-2">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</x-layouts.admin>
