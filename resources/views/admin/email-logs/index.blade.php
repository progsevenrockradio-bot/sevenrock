<x-layouts.admin :title="'Historial de correos - '.(optional($themeSettings)->site_name ?? config('app.name'))">
    @php
        $hasActiveFilters = trim((string) request('search')) !== '' || trim((string) request('status')) !== '';
    @endphp

    <div class="mx-auto max-w-7xl space-y-5 font-sans">
        {{-- Cabecera compacta --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Comunicación · Registro de despachos</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">Historial de correos</h1>
                <p class="mt-1 max-w-3xl text-xs text-gray-400">
                    Registro cronológico de los correos procesados por la plataforma ({{ $logs->total() }} registros conservados).
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.direct-email.create') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#c32720] px-3.5 text-xs font-semibold text-white hover:bg-[#a8201a]">
                    <span>Redactar correo</span>
                </a>
                <a href="{{ route('admin.email-templates.index') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                    <span>Plantillas de correo</span>
                </a>
            </div>
        </header>

        {{-- Nota técnica sobre el significado de «Enviado» y preservación íntegra de registros --}}
        <div class="rounded-xl border border-white/10 bg-[#101012] px-4 py-3 text-xs text-gray-300">
            <strong class="font-semibold text-white">Alcance del estado de envío:</strong>
            el estado <span class="text-emerald-300 font-medium">«Enviado (despacho SMTP)»</span> indica que el mensaje fue entregado al servidor de salida SMTP configurado; <strong>no constituye confirmación de recepción en bandeja de entrada ni de lectura por el destinatario</strong>. Se conservan todos los registros individuales en orden cronológico, incluidos reintentos o envíos con el mismo asunto.
        </div>

        {{-- Filtros con etiquetas visibles --}}
        <form method="GET" action="{{ route('admin.email-logs.index') }}" class="rounded-xl border border-white/10 bg-[#101012] p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_220px_auto] items-end">
                <div>
                    <label for="log-search" class="mb-1 block text-xs font-medium text-gray-300">Buscar destinatario o asunto</label>
                    <input
                        id="log-search"
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Correo del destinatario o palabras del asunto..."
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >
                </div>
                <div>
                    <label for="log-status" class="mb-1 block text-xs font-medium text-gray-300">Estado de despacho</label>
                    <select
                        id="log-status"
                        name="status"
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >
                        <option value="">Todos los registros</option>
                        <option value="sent" @selected(request('status') === 'sent')>Enviado (despacho SMTP)</option>
                        <option value="failed" @selected(request('status') === 'failed')>Fallido en envío</option>
                    </select>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-[#c32720] px-3.5 text-xs font-semibold text-white hover:bg-[#a8201a]">
                        Filtrar
                    </button>
                    @if ($hasActiveFilters)
                        <a href="{{ route('admin.email-logs.index') }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-300 hover:bg-white/[0.08] hover:text-white">
                            Restablecer filtros
                        </a>
                    @endif
                </div>
            </div>
        </form>

        {{-- Tabla compacta en escritorio --}}
        <section class="hidden md:block rounded-xl border border-white/10 bg-[#101012] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-white/10 bg-[#151518] text-[11px] font-semibold text-gray-400">
                        <tr>
                            <th class="px-4 py-3 whitespace-nowrap">Fecha y hora</th>
                            <th class="px-4 py-3">Destinatario</th>
                            <th class="px-4 py-3">Asunto y detalles técnicos</th>
                            <th class="px-4 py-3 whitespace-nowrap">Estado de despacho</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse($logs as $log)
                            <tr class="hover:bg-white/[0.02] align-top">
                                <td class="px-4 py-3 whitespace-nowrap text-gray-300">
                                    <div>{{ $log->created_at?->format('d/m/Y H:i:s') ?? 'N/D' }}</div>
                                    <div class="font-mono text-[10px] text-gray-500">ID #{{ $log->id }}</div>
                                </td>
                                <td class="px-4 py-3 font-mono text-[11px] text-gray-200">
                                    {{ $log->to_email }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-white">{{ $log->subject }}</div>
                                    @if ($log->body || $log->track_submission_id)
                                        <details class="mt-1.5 group">
                                            <summary class="cursor-pointer select-none text-[11px] font-medium text-gray-400 hover:text-white">
                                                Ver contenido y detalles técnicos
                                            </summary>
                                            <div class="mt-2 rounded-lg border border-white/10 bg-[#151518] p-3 space-y-2 text-[11px] text-gray-300">
                                                <div class="flex flex-wrap gap-x-4 gap-y-1 text-[10px] text-gray-400 border-b border-white/5 pb-1.5">
                                                    <span>Registro: <strong class="text-gray-200">#{{ $log->id }}</strong></span>
                                                    <span>Fecha: <strong class="text-gray-200">{{ $log->created_at?->format('Y-m-d H:i:s') }}</strong></span>
                                                    @if ($log->track_submission_id)
                                                        <span>Maqueta vinculada: <strong class="text-gray-200">#{{ $log->track_submission_id }} ({{ $log->trackSubmission?->band_name ?? 'Buzón de maquetas' }})</strong></span>
                                                    @endif
                                                </div>
                                                @if ($log->body)
                                                    <div class="max-h-44 overflow-y-auto whitespace-pre-wrap font-mono text-[11px] leading-relaxed text-gray-300">{{ strip_tags((string) $log->body) ?: $log->body }}</div>
                                                @endif
                                            </div>
                                        </details>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($log->status === 'sent')
                                        <span class="inline-flex items-center rounded-md border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-300" title="Aceptado por el servidor SMTP de salida; no confirma recepción ni lectura">
                                            Enviado (despacho SMTP)
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-md border border-red-500/30 bg-red-500/10 px-2 py-0.5 text-[11px] font-medium text-red-300">
                                            Fallido ({{ $log->status }})
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-xs text-gray-400">
                                    No hay registros de correos que coincidan con la consulta.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Tarjetas en móvil (390 px) --}}
        <section class="space-y-3 md:hidden">
            @forelse($logs as $log)
                <article class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-2.5 text-xs">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="font-semibold text-white">{{ $log->subject }}</div>
                            <div class="mt-0.5 font-mono text-[11px] text-gray-400 break-all">{{ $log->to_email }}</div>
                        </div>
                        @if($log->status === 'sent')
                            <span class="shrink-0 inline-flex items-center rounded border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-medium text-emerald-300">
                                Enviado (SMTP)
                            </span>
                        @else
                            <span class="shrink-0 inline-flex items-center rounded border border-red-500/30 bg-red-500/10 px-2 py-0.5 text-[10px] font-medium text-red-300">
                                Fallido
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center justify-between text-[11px] text-gray-400 border-t border-white/5 pt-2">
                        <span>{{ $log->created_at?->format('d/m/Y H:i:s') }}</span>
                        <span class="font-mono text-[10px]">ID #{{ $log->id }}</span>
                    </div>

                    @if ($log->body || $log->track_submission_id)
                        <details class="pt-1">
                            <summary class="cursor-pointer text-[11px] font-medium text-gray-300">
                                Detalles técnicos
                            </summary>
                            <div class="mt-2 max-h-36 overflow-y-auto rounded-lg border border-white/10 bg-[#151518] p-2.5 whitespace-pre-wrap font-mono text-[10px] text-gray-300">{{ strip_tags((string) $log->body) ?: $log->body }}</div>
                        </details>
                    @endif
                </article>
            @empty
                <div class="rounded-xl border border-white/10 bg-[#101012] p-6 text-center text-xs text-gray-400">
                    No hay registros de correos enviados todavía.
                </div>
            @endforelse
        </section>

        <div>
            {{ $logs->links() }}
        </div>
    </div>
</x-layouts.admin>
