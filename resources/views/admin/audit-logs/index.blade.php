<x-layouts.admin :title="'Audit trail - '.$themeSettings->site_name">
    <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Audit trail</h1>
        <p class="mt-3 max-w-3xl text-[#7b7b7b]">
            Registro detallado de actividad en el panel admin: quién hizo qué, desde dónde, con qué payload y con qué resultado.
        </p>

        <div class="mt-6 grid gap-4 md:grid-cols-4">
            <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.2)] p-4">
                <div class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Total</div>
                <div class="mt-2 text-2xl font-semibold text-[#e0e0e0]">{{ $stats['total'] }}</div>
            </div>
            <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.2)] p-4">
                <div class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Today</div>
                <div class="mt-2 text-2xl font-semibold text-[#e0e0e0]">{{ $stats['today'] }}</div>
            </div>
            <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.2)] p-4">
                <div class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Warnings</div>
                <div class="mt-2 text-2xl font-semibold text-[#ffcf7a]">{{ $stats['warnings'] }}</div>
            </div>
            <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.2)] p-4">
                <div class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Errors</div>
                <div class="mt-2 text-2xl font-semibold text-[#ff9e9e]">{{ $stats['errors'] }}</div>
            </div>
        </div>

        <form method="GET" class="mt-6 grid gap-4 md:grid-cols-4">
            <input name="q" value="{{ $filters['q'] }}" placeholder="Buscar..." class="lucille-product-field w-full">
            <input name="category" value="{{ $filters['category'] }}" placeholder="Category" class="lucille-product-field w-full">
            <input name="event" value="{{ $filters['event'] }}" placeholder="Event" class="lucille-product-field w-full">
            <select name="level" class="lucille-product-field lucille-select-field w-full">
                <option value="">All levels</option>
                @foreach (['info' => 'info', 'warning' => 'warning', 'error' => 'error'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['level'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="md:col-span-4 flex flex-wrap gap-3">
                <button type="submit" class="lucille-button-solid">Filtrar</button>
                <a href="{{ route('admin.audit-logs.index') }}" class="lucille-button">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="mt-8 space-y-4">
        @forelse ($logs as $log)
            <article class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="font-display text-lg uppercase tracking-[.12em] text-[#e0e0e0]">{{ $log->summary ?: $log->event }}</div>
                        <div class="mt-1 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">
                            {{ $log->category }} · {{ $log->event }} · {{ $log->level }}
                        </div>
                        <div class="mt-2 text-sm text-[#9d9d9d]">
                            {{ $log->actor_name ?: 'System' }}{{ $log->actor_email ? ' <'.$log->actor_email.'>' : '' }}
                        </div>
                        <div class="mt-1 text-xs text-[#7b7b7b]">
                            {{ $log->method }} {{ $log->route_name ?: $log->url }}
                            @if ($log->status_code)
                                · HTTP {{ $log->status_code }}
                            @endif
                            @if ($log->duration_ms !== null)
                                · {{ $log->duration_ms }} ms
                            @endif
                        </div>
                    </div>
                    <div class="text-right text-xs uppercase tracking-[.18em] text-[#7b7b7b]">
                        <div>{{ optional($log->created_at)->format('d/m/Y H:i:s') }}</div>
                        <div>{{ $log->ip_address ?: 'sin IP' }}</div>
                    </div>
                </div>

                <details class="mt-4 border border-[#2b2b2b] bg-[rgba(0,0,0,.22)] p-4">
                    <summary class="cursor-pointer text-sm uppercase tracking-[.16em] text-[#dcdcdc]">Ver detalles</summary>
                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <div>
                            <div class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Request</div>
                            <pre class="mt-2 overflow-auto whitespace-pre-wrap break-words text-xs leading-6 text-[#dcdcdc]">{{ json_encode($log->request_payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                        <div>
                            <div class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Changes</div>
                            <pre class="mt-2 overflow-auto whitespace-pre-wrap break-words text-xs leading-6 text-[#dcdcdc]">{{ json_encode($log->changes ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                        <div>
                            <div class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Context</div>
                            <pre class="mt-2 overflow-auto whitespace-pre-wrap break-words text-xs leading-6 text-[#dcdcdc]">{{ json_encode($log->context ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                        <div>
                            <div class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Response</div>
                            <pre class="mt-2 overflow-auto whitespace-pre-wrap break-words text-xs leading-6 text-[#dcdcdc]">{{ json_encode($log->response_meta ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    </div>
                </details>
            </article>
        @empty
            <p class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8 text-sm text-[#7b7b7b]">Todavía no hay entradas de auditoría.</p>
        @endforelse
    </section>

    <div class="mt-8">
        {{ $logs->links() }}
    </div>
</x-layouts.admin>
