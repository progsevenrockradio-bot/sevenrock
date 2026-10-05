<x-layouts.admin :title="'Nuevo Código de Promotor'">
    <section class="space-y-6 max-w-3xl">
        <div>
            <a href="{{ route('admin.talents.promoters.index') }}" class="text-xs uppercase tracking-[.18em] text-[#9a9a9a] hover:text-white transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Volver al listado
            </a>
            <h1 class="mt-4 font-display text-2xl sm:text-3xl uppercase tracking-[.12em] text-white">Generar Nuevo Código</h1>
            <p class="mt-2 text-sm text-[#8b8b8b]">Crea un código único para que un conductor o banda refiera talentos al portal.</p>
        </div>

        <form method="POST" action="{{ route('admin.talents.promoters.store') }}" class="space-y-5 border border-white/10 bg-[#10161b] p-4 sm:p-6">
            @csrf

            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Nombre del dueño</label>
                <input type="text" name="owner_name" value="{{ old('owner_name') }}" class="lucille-product-field w-full" required placeholder="Ej: John Doe, Programa X">
                @error('owner_name') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Correo electrónico</label>
                <input type="email" name="owner_email" value="{{ old('owner_email') }}" class="lucille-product-field w-full" required placeholder="correo@ejemplo.com">
                <p class="mt-1 text-xs text-[#8b8b8b]">A este correo se le podrá enviar su código generado.</p>
                @error('owner_email') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Tipo</label>
                    <select name="owner_type" class="lucille-product-field w-full" required>
                        <option value="conductor" @selected(old('owner_type') === 'conductor')>Conductor</option>
                        <option value="persona" @selected(old('owner_type') === 'persona')>Persona</option>
                        <option value="banda" @selected(old('owner_type') === 'banda')>Banda</option>
                    </select>
                    @error('owner_type') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Tope de bandas (18 por defecto)</label>
                    <input type="number" name="max_bands" value="{{ old('max_bands', 18) }}" class="lucille-product-field w-full" min="1" placeholder="Dejar vacío para sin límite">
                    @error('max_bands') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
                </div>
            </div>

            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Banda asignada (opcional)</label>
                <select name="assigned_talent_id" class="lucille-product-field w-full">
                    <option value="">Ninguna</option>
                    @foreach($talents as $talent)
                        <option value="{{ $talent->id }}" @selected(old('assigned_talent_id') == $talent->id)>{{ $talent->band_name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-[#8b8b8b]">Si el promotor es una banda registrada, puedes enlazarla aquí.</p>
                @error('assigned_talent_id') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Notas (opcional)</label>
                <textarea name="notes" rows="3" class="lucille-product-field w-full" placeholder="Notas internas para este código...">{{ old('notes') }}</textarea>
                @error('notes') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
            </div>

            <div class="border border-[#1e4d2b] bg-[rgba(16,64,30,.18)] px-4 py-3 text-sm text-[#b8e6c3]">
                <strong>Info:</strong> El código alfanumérico se generará automáticamente al guardar.
            </div>

            <div class="pt-4">
                <button type="submit" class="lucille-button-solid w-full sm:w-auto text-center">Generar Código</button>
            </div>
        </form>
    </section>
</x-layouts.admin>
