<x-layouts.admin title="Editar Página en Espera">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="font-display text-2xl uppercase tracking-[.08em] text-[#dcdcdc]">Editar Página en Espera</h1>
            <p class="text-sm text-[#7b7b7b] mt-1">Configuración actual para: {{ $pageCountdown->route_path }}</p>
        </div>
        <a href="{{ route('admin.page-countdowns.index') }}" class="lucille-button">Volver al listado</a>
    </div>

    <form action="{{ route('admin.page-countdowns.update', $pageCountdown) }}" method="POST" class="lucille-card">
        @csrf
        @method('PUT')
        <div class="grid gap-6 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="mb-2 block text-[10px] font-semibold uppercase tracking-[.18em] text-[#8a8a8a]">Ruta a bloquear</label>
                <input type="text" name="route_path" value="{{ old('route_path', $pageCountdown->route_path) }}" class="lucille-form-field w-full" required>
                @error('route_path') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="mb-2 block text-[10px] font-semibold uppercase tracking-[.18em] text-[#8a8a8a]">Título Público (Coming Soon)</label>
                <input type="text" name="title" value="{{ old('title', $pageCountdown->title) }}" class="lucille-form-field w-full" required>
                @error('title') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="mb-2 block text-[10px] font-semibold uppercase tracking-[.18em] text-[#8a8a8a]">Descripción / Mensaje</label>
                <textarea name="description" rows="3" class="lucille-form-field w-full">{{ old('description', $pageCountdown->description) }}</textarea>
                @error('description') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-2 block text-[10px] font-semibold uppercase tracking-[.18em] text-[#8a8a8a]">Fecha y Hora de Activación</label>
                <input type="datetime-local" name="active_at" value="{{ old('active_at', $pageCountdown->active_at ? $pageCountdown->active_at->format('Y-m-d\TH:i') : '') }}" class="lucille-form-field w-full">
                @error('active_at') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-2 block text-[10px] font-semibold uppercase tracking-[.18em] text-[#8a8a8a]">Estado</label>
                <label class="flex items-center gap-3 mt-4">
                    <input type="checkbox" name="is_enabled" value="1" class="w-4 h-4 text-[var(--lucille-accent)] bg-transparent border-gray-600 rounded focus:ring-0" {{ old('is_enabled', $pageCountdown->is_enabled) ? 'checked' : '' }}>
                    <span class="text-sm text-[#dcdcdc]">Habilitar este bloqueo ahora mismo</span>
                </label>
            </div>
        </div>

        <div class="mt-8 border-t border-white/5 pt-6 flex justify-end">
            <button type="submit" class="lucille-button-solid">Actualizar Configuración</button>
        </div>
    </form>
</x-layouts.admin>
