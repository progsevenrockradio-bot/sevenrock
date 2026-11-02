<x-layouts.admin title="Editar regla de espera - {{ $themeSettings->site_name ?? 'Seven Rock Radio' }}">
    <div class="mx-auto max-w-3xl space-y-5 font-sans">
        {{-- Cabecera compacta --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="flex items-center gap-2 text-[11px] font-medium uppercase tracking-wider text-gray-400">
                    <a href="{{ route('admin.page-countdowns.index') }}" class="hover:text-white transition-colors">Páginas en espera</a>
                    <span class="text-white/20">/</span>
                    <span class="text-[#c32720]">Editar regla</span>
                </div>
                <h1 class="mt-1 font-display text-2xl font-bold uppercase tracking-wide text-[#dcdcdc]">
                    Editar regla: <span class="font-mono text-lg text-gray-300">/{{ ltrim($pageCountdown->route_path, '/') }}</span>
                </h1>
                <p class="mt-1 text-xs text-gray-400">
                    Modifica la ruta, los textos o la fecha de apertura de esta pantalla de espera.
                </p>
            </div>
            <div>
                <a href="{{ route('admin.page-countdowns.index') }}"
                   class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3.5 text-xs font-medium text-gray-300 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                    <span>← Volver al listado</span>
                </a>
            </div>
        </header>

        {{-- Resumen del estado actual de esta regla --}}
        @php
            $hasDate = $pageCountdown->active_at !== null;
            $isFuture = $hasDate && $pageCountdown->active_at->isFuture();
            $isBlocking = $pageCountdown->is_enabled && $isFuture;
        @endphp
        <div class="rounded-xl border border-white/10 bg-[#101012] p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs">
            <div class="space-y-0.5">
                <span class="text-[11px] text-gray-400 uppercase tracking-wider font-semibold">Estado efectivo actual para visitantes públicos:</span>
                @if(!$pageCountdown->is_enabled)
                    <p class="text-gray-300 font-medium">Regla deshabilitada — la ruta está abierta al público.</p>
                @elseif($isBlocking)
                    <p class="text-amber-300 font-medium">En espera activa — bloqueando visitantes públicos hasta {{ $pageCountdown->active_at->format('d/m/Y H:i') }}.</p>
                @elseif($hasDate && !$isFuture)
                    <p class="text-emerald-300 font-medium">Abierta por fecha cumplida — la fecha ({{ $pageCountdown->active_at->format('d/m/Y H:i') }}) ya pasó.</p>
                @else
                    <p class="text-sky-300 font-medium">Sin fecha configurada — no bloquea el acceso público hasta que asignes una fecha futura.</p>
                @endif
            </div>
            <span class="shrink-0 rounded-md border border-white/10 bg-white/5 px-2.5 py-1 text-[11px] text-gray-300">
                Admins autenticados: acceso directo activo
            </span>
        </div>

        <form action="{{ route('admin.page-countdowns.update', $pageCountdown) }}" method="POST" class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-5">
            @csrf
            @method('PUT')

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="route_path" class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">
                        Ruta objetivo (URL relativa) <span class="text-[#c32720]">*</span>
                    </label>
                    <input type="text" name="route_path" id="route_path" value="{{ old('route_path', $pageCountdown->route_path) }}"
                           required
                           class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                    <p class="mt-1 text-[11px] text-gray-400">
                        Sin dominio inicial. Usa <code>*</code> al final para cubrir subrutas.
                    </p>
                    @error('route_path')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="active_at" class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">
                        Fecha y hora de apertura (Fin de la espera)
                    </label>
                    <input type="datetime-local" name="active_at" id="active_at"
                           value="{{ old('active_at', $pageCountdown->active_at ? $pageCountdown->active_at->format('Y-m-d\TH:i') : '') }}"
                           class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                    <p class="mt-1 text-[11px] text-gray-400">
                        Requiere una fecha futura para mostrar la espera. Al cumplirse, la ruta vuelve a abrirse sola.
                    </p>
                    @error('active_at')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="title" class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">
                    Título visible en la pantalla de espera <span class="text-[#c32720]">*</span>
                </label>
                <input type="text" name="title" id="title" value="{{ old('title', $pageCountdown->title) }}"
                       required
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                @error('title')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="description" class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">
                    Mensaje descriptivo (opcional)
                </label>
                <textarea name="description" id="description" rows="3"
                          class="w-full rounded-lg border border-white/10 bg-[#151518] p-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">{{ old('description', $pageCountdown->description) }}</textarea>
                @error('description')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="rounded-lg border border-white/10 bg-[#151518] p-3.5">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="is_enabled" value="1"
                           {{ old('is_enabled', $pageCountdown->is_enabled) ? 'checked' : '' }}
                           class="mt-0.5 h-4 w-4 rounded border-white/20 bg-black text-[#c32720] focus:ring-[#c32720]">
                    <div>
                        <span class="block text-xs font-semibold text-[#dcdcdc]">Habilitar regla de espera</span>
                        <span class="mt-0.5 block text-[11px] text-gray-400">
                            Si está marcada y la fecha de apertura es futura, los visitantes públicos verán la pantalla de espera. Desmárcala para abrir el acceso público inmediatamente.
                        </span>
                    </div>
                </label>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2.5 border-t border-white/10 pt-4">
                <a href="{{ route('admin.page-countdowns.index') }}"
                   class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/5 px-4 text-xs font-medium text-gray-300 transition-colors hover:bg-white/10 hover:text-white">
                    Cancelar
                </a>
                <button type="submit"
                        class="inline-flex h-9 items-center rounded-lg bg-[#c32720] px-5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-[#a61f19] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    Actualizar regla
                </button>
            </div>
        </form>
    </div>
</x-layouts.admin>
