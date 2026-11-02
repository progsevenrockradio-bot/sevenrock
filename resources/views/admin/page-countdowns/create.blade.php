<x-layouts.admin title="Nueva regla de espera - {{ $themeSettings->site_name ?? 'Seven Rock Radio' }}">
    <div class="mx-auto max-w-3xl space-y-5 font-sans">
        {{-- Cabecera compacta --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="flex items-center gap-2 text-[11px] font-medium uppercase tracking-wider text-gray-400">
                    <a href="{{ route('admin.page-countdowns.index') }}" class="hover:text-white transition-colors">Páginas en espera</a>
                    <span class="text-white/20">/</span>
                    <span class="text-[#c32720]">Nueva regla</span>
                </div>
                <h1 class="mt-1 font-display text-2xl font-bold uppercase tracking-wide text-[#dcdcdc]">Nueva regla de espera</h1>
                <p class="mt-1 text-xs text-gray-400">
                    Configura una pantalla temporal «Próximamente» para una ruta pública hasta su fecha y hora de apertura.
                </p>
            </div>
            <div>
                <a href="{{ route('admin.page-countdowns.index') }}"
                   class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3.5 text-xs font-medium text-gray-300 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                    <span>← Volver al listado</span>
                </a>
            </div>
        </header>

        {{-- Aviso sobre el comportamiento real del middleware --}}
        <div class="rounded-xl border border-amber-500/25 bg-amber-500/10 p-4 text-xs text-amber-200 space-y-1.5">
            <p class="font-semibold text-amber-100">Condiciones para que el bloqueo público se active:</p>
            <ul class="list-disc list-inside space-y-1 text-[11px] text-amber-200/90">
                <li>La opción <strong>Habilitar regla de espera</strong> debe estar marcada y la <strong>fecha de apertura</strong> debe estar en el futuro.</li>
                <li>Si dejas la fecha vacía o la fecha ya pasó, el middleware mantendrá la página abierta al público.</li>
                <li>Los <strong>administradores autenticados</strong> nunca son bloqueados: siempre ven la página real para poder revisarla antes del lanzamiento.</li>
            </ul>
        </div>

        <form action="{{ route('admin.page-countdowns.store') }}" method="POST" class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-5">
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="route_path" class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">
                        Ruta objetivo (URL relativa) <span class="text-[#c32720]">*</span>
                    </label>
                    <input type="text" name="route_path" id="route_path" value="{{ old('route_path') }}"
                           placeholder="Ej: muro-del-rock, events o talentos*"
                           required
                           class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                    <p class="mt-1 text-[11px] text-gray-400">
                        Sin dominio inicial. Puedes usar <code>*</code> al final (ej. <code>muro-del-rock*</code>) para cubrir subrutas.
                    </p>
                    @error('route_path')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="active_at" class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">
                        Fecha y hora de apertura (Fin de la espera)
                    </label>
                    <input type="datetime-local" name="active_at" id="active_at" value="{{ old('active_at') }}"
                           class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                    <p class="mt-1 text-[11px] text-gray-400">
                        Indica una fecha futura para activar la pantalla de espera. Al cumplirse la fecha, la ruta se abrirá sola.
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
                <input type="text" name="title" id="title" value="{{ old('title', 'Próximamente disponible') }}"
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
                          placeholder="Ej: Estamos preparando esta sección. Vuelve cuando termine la cuenta regresiva."
                          class="w-full rounded-lg border border-white/10 bg-[#151518] p-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">{{ old('description') }}</textarea>
                @error('description')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="rounded-lg border border-white/10 bg-[#151518] p-3.5">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="is_enabled" value="1"
                           {{ old('is_enabled', true) ? 'checked' : '' }}
                           class="mt-0.5 h-4 w-4 rounded border-white/20 bg-black text-[#c32720] focus:ring-[#c32720]">
                    <div>
                        <span class="block text-xs font-semibold text-[#dcdcdc]">Habilitar regla de espera</span>
                        <span class="mt-0.5 block text-[11px] text-gray-400">
                            Si está marcada y la fecha de apertura es futura, los visitantes públicos verán la cuenta regresiva. Si la desmarcas, la ruta quedará abierta de inmediato.
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
                    Guardar regla de espera
                </button>
            </div>
        </form>
    </div>
</x-layouts.admin>
