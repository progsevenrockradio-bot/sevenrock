<x-layouts.admin title="Agregar Página en Espera">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="font-display text-2xl uppercase tracking-[.08em] text-[#dcdcdc]">Agregar Página en Espera</h1>
            <p class="text-sm text-[#7b7b7b] mt-1">Bloquea temporalmente una ruta pública.</p>
        </div>
        <a href="{{ route('admin.page-countdowns.index') }}" class="lucille-button">Volver al listado</a>
    </div>

    <form action="{{ route('admin.page-countdowns.store') }}" method="POST" class="lucille-card">
        @csrf
        <div class="grid gap-6 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="mb-2 block text-[10px] font-semibold uppercase tracking-[.18em] text-[#8a8a8a]">Ruta a bloquear (Ej: talentos/register)</label>
                <input type="text" name="route_path" value="{{ old('route_path') }}" class="lucille-form-field w-full" placeholder="Ej: talentos/register o albums/*" required>
                @error('route_path') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="mb-2 block text-[10px] font-semibold uppercase tracking-[.18em] text-[#8a8a8a]">Título Público (Coming Soon)</label>
                <input type="text" name="title" value="{{ old('title', 'Página en Construcción') }}" class="lucille-form-field w-full" required>
                @error('title') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="mb-2 block text-[10px] font-semibold uppercase tracking-[.18em] text-[#8a8a8a]">Descripción / Mensaje</label>
                <textarea name="description" rows="3" class="lucille-form-field w-full" placeholder="Ej: Estamos preparando algo genial...">{{ old('description', 'Estamos preparando este contenido. Vuelve pronto.') }}</textarea>
                @error('description') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-2 block text-[10px] font-semibold uppercase tracking-[.18em] text-[#8a8a8a]">Fecha y Hora de Activación</label>
                <input type="datetime-local" name="active_at" value="{{ old('active_at') }}" class="lucille-form-field w-full">
                <p class="text-xs text-[#7b7b7b] mt-1">Si la dejas vacía, se bloqueará indefinidamente.</p>
                @error('active_at') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-2 block text-[10px] font-semibold uppercase tracking-[.18em] text-[#8a8a8a]">Estado</label>
                <label class="flex items-center gap-3 mt-4">
                    <input type="checkbox" name="is_enabled" value="1" class="w-4 h-4 text-[var(--lucille-accent)] bg-transparent border-gray-600 rounded focus:ring-0" checked>
                    <span class="text-sm text-[#dcdcdc]">Habilitar este bloqueo ahora mismo</span>
                </label>
            </div>
        </div>

        <div class="mt-8 border-t border-white/5 pt-6 flex justify-end">
            <button type="submit" class="lucille-button-solid">Guardar Configuración</button>
        </div>
    </form>
</x-layouts.admin>
