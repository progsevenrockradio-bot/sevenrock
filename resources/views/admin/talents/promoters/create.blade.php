<x-layouts.admin :title="'Nuevo código de promotor'">
    <div class="mx-auto max-w-4xl space-y-5 font-sans">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Bandas y comunidad · Referidos</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">Generar código de promotor</h1>
                <p class="mt-1 text-xs text-gray-400">Crea un código único para que un conductor, colaborador o banda refiera nuevas bandas al Muro del Rock.</p>
            </div>
            <a href="{{ route('admin.talents.promoters.index') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                <span>Volver al listado</span>
            </a>
        </header>

        <form method="POST" action="{{ route('admin.talents.promoters.store') }}" class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-4">
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="promoter-owner-name" class="mb-1.5 block text-xs font-medium text-gray-300">Nombre del titular</label>
                    <input
                        id="promoter-owner-name"
                        type="text"
                        name="owner_name"
                        value="{{ old('owner_name') }}"
                        required
                        placeholder="Ej: Programa Rock Nite o nombre del promotor"
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >
                    @error('owner_name') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="promoter-owner-email" class="mb-1.5 block text-xs font-medium text-gray-300">Correo electrónico</label>
                    <input
                        id="promoter-owner-email"
                        type="email"
                        name="owner_email"
                        value="{{ old('owner_email') }}"
                        required
                        placeholder="correo@ejemplo.com"
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >
                    @error('owner_email') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="promoter-owner-type" class="mb-1.5 block text-xs font-medium text-gray-300">Tipo de promotor</label>
                    <select
                        id="promoter-owner-type"
                        name="owner_type"
                        required
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >
                        <option value="conductor" @selected(old('owner_type') === 'conductor')>Conductor</option>
                        <option value="persona" @selected(old('owner_type') === 'persona')>Persona</option>
                        <option value="banda" @selected(old('owner_type') === 'banda')>Banda</option>
                    </select>
                    @error('owner_type') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="promoter-campaign" class="mb-1.5 block text-xs font-medium text-gray-300">Campaña</label>
                    <input
                        id="promoter-campaign"
                        type="text"
                        name="campaign"
                        value="{{ old('campaign', 'pilot_2026') }}"
                        placeholder="pilot_2026"
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >
                    @error('campaign') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="promoter-max-bands" class="mb-1.5 block text-xs font-medium text-gray-300">Tope de bandas (18 por defecto)</label>
                    <input
                        id="promoter-max-bands"
                        type="number"
                        name="max_bands"
                        value="{{ old('max_bands', 18) }}"
                        min="1"
                        placeholder="18"
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >
                    @error('max_bands') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                </div>
            </div>

            <div>
                <label for="promoter-assigned-talent" class="mb-1.5 block text-xs font-medium text-gray-300">Banda vinculada (opcional)</label>
                <select
                    id="promoter-assigned-talent"
                    name="assigned_talent_id"
                    class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                >
                    <option value="">Ninguna</option>
                    @foreach ($talents as $talent)
                        <option value="{{ $talent->id }}" @selected(old('assigned_talent_id') == $talent->id)>{{ $talent->band_name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-gray-400">Si el promotor corresponde a una banda ya registrada en el Muro, puedes asociarla aquí.</p>
                @error('assigned_talent_id') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="promoter-notes" class="mb-1.5 block text-xs font-medium text-gray-300">Notas internas (opcional)</label>
                <textarea
                    id="promoter-notes"
                    name="notes"
                    rows="3"
                    placeholder="Observaciones internas sobre la asignación del código..."
                    class="w-full rounded-lg border border-white/10 bg-[#151518] p-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                >{{ old('notes') }}</textarea>
                @error('notes') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
            </div>

            <div class="rounded-lg border border-white/5 bg-[#151518] px-3.5 py-2.5 text-xs text-gray-300">
                El código alfanumérico único se generará automáticamente al guardar el registro.
            </div>

            <div class="flex flex-wrap items-center gap-2.5 pt-2 border-t border-white/5">
                <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#a8201a] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    Generar código
                </button>
                <a href="{{ route('admin.talents.promoters.index') }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3.5 text-xs font-medium text-gray-300 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</x-layouts.admin>
