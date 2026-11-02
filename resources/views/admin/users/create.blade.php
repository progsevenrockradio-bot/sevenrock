<x-layouts.admin title="Nuevo administrador - {{ $themeSettings->site_name ?? 'Seven Rock Radio' }}">
    <div class="mx-auto max-w-2xl space-y-5 font-sans">
        {{-- Cabecera compacta --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="flex items-center gap-2 text-[11px] font-medium uppercase tracking-wider text-gray-400">
                    <a href="{{ route('admin.users.index') }}" class="hover:text-white transition-colors">Gestión de usuarios</a>
                    <span class="text-white/20">/</span>
                    <span class="text-[#c32720]">Nuevo administrador</span>
                </div>
                <h1 class="mt-1 font-display text-2xl font-bold uppercase tracking-wide text-[#dcdcdc]">Crear nuevo administrador</h1>
                <p class="mt-1 text-xs text-gray-400">
                    Registra una nueva cuenta con acceso al panel de administración (`is_admin`).
                </p>
            </div>
            <div>
                <a href="{{ route('admin.users.index') }}"
                   class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3.5 text-xs font-medium text-gray-300 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                    <span>← Volver a usuarios</span>
                </a>
            </div>
        </header>

        <form action="{{ route('admin.users.store') }}" method="POST" class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-5">
            @csrf

            <div>
                <label for="name" class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">
                    Nombre completo o identificador <span class="text-[#c32720]">*</span>
                </label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required
                       placeholder="Ej: Redacción Seven Rock"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                @error('name')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">
                    Correo electrónico de acceso <span class="text-[#c32720]">*</span>
                </label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required
                       placeholder="usuario@sevenrockradio.com"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                @error('email')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2 border-t border-white/10 pt-4">
                <div>
                    <label for="password" class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">
                        Contraseña <span class="text-[#c32720]">*</span>
                    </label>
                    <input type="password" id="password" name="password" required
                           class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                    <p class="mt-1 text-[11px] text-gray-400">Mínimo 8 caracteres.</p>
                    @error('password')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">
                        Confirmar contraseña <span class="text-[#c32720]">*</span>
                    </label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required
                           class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2.5 border-t border-white/10 pt-4">
                <a href="{{ route('admin.users.index') }}"
                   class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/5 px-4 text-xs font-medium text-gray-300 transition-colors hover:bg-white/10 hover:text-white">
                    Cancelar
                </a>
                <button type="submit"
                        class="inline-flex h-9 items-center rounded-lg bg-[#c32720] px-5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-[#a61f19] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    Guardar administrador
                </button>
            </div>
        </form>
    </div>
</x-layouts.admin>
