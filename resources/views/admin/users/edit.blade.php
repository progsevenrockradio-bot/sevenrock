<x-layouts.admin title="Editar administrador - {{ $themeSettings->site_name ?? 'Seven Rock Radio' }}">
    @php
        $isCurrentUser = auth()->id() === $user->id;
        $roleNames = method_exists($user, 'getRoleNames') ? $user->getRoleNames() : collect();
    @endphp
    <div class="mx-auto max-w-2xl space-y-5 font-sans">
        {{-- Cabecera compacta --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="flex items-center gap-2 text-[11px] font-medium uppercase tracking-wider text-gray-400">
                    <a href="{{ route('admin.users.index') }}" class="hover:text-white transition-colors">Gestión de usuarios</a>
                    <span class="text-white/20">/</span>
                    <span class="text-[#c32720]">Editar administrador</span>
                </div>
                <h1 class="mt-1 font-display text-2xl font-bold uppercase tracking-wide text-[#dcdcdc]">
                    Editar administrador: {{ $user->name }}
                </h1>
                <p class="mt-1 text-xs text-gray-400">
                    Actualiza el nombre, correo de acceso o contraseña de esta cuenta.
                </p>
            </div>
            <div>
                <a href="{{ route('admin.users.index') }}"
                   class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3.5 text-xs font-medium text-gray-300 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                    <span>← Volver a usuarios</span>
                </a>
            </div>
        </header>

        {{-- Estado y roles actuales de la cuenta (solo lectura informativa) --}}
        <div class="rounded-xl border border-white/10 bg-[#101012] p-4 flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-gray-400">Roles asignados:</span>
                @forelse ($roleNames as $roleName)
                    <span class="rounded-md border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[11px] font-medium text-amber-300">
                        {{ $roleName }}
                    </span>
                @empty
                    <span class="rounded-md border border-sky-500/30 bg-sky-500/10 px-2 py-0.5 text-[11px] font-medium text-sky-300">
                        Administrador de panel
                    </span>
                @endforelse
            </div>
            @if ($isCurrentUser)
                <span class="rounded-md border border-[#c32720]/40 bg-[#c32720]/15 px-2.5 py-0.5 text-[11px] font-semibold text-red-200">
                    Estás editando tu propia cuenta
                </span>
            @endif
        </div>

        <form action="{{ route('admin.users.update', $user) }}" method="POST" class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">
                    Nombre completo o identificador <span class="text-[#c32720]">*</span>
                </label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                @error('name')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">
                    Correo electrónico de acceso <span class="text-[#c32720]">*</span>
                </label>
                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                @error('email')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-4 border-t border-white/10 pt-4">
                <div>
                    <h2 class="font-display text-sm uppercase tracking-wider text-[#dcdcdc] font-semibold">Cambio de contraseña (opcional)</h2>
                    <p class="mt-0.5 text-[11px] text-gray-400">Deja ambos campos en blanco si deseas conservar la contraseña actual.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="password" class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">
                            Nueva contraseña
                        </label>
                        <input type="password" id="password" name="password"
                               placeholder="Dejar vacío para no cambiar"
                               class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                        @error('password')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">
                            Confirmar nueva contraseña
                        </label>
                        <input type="password" id="password_confirmation" name="password_confirmation"
                               placeholder="Repite la nueva contraseña"
                               class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2.5 border-t border-white/10 pt-4">
                <a href="{{ route('admin.users.index') }}"
                   class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/5 px-4 text-xs font-medium text-gray-300 transition-colors hover:bg-white/10 hover:text-white">
                    Cancelar
                </a>
                <button type="submit"
                        class="inline-flex h-9 items-center rounded-lg bg-[#c32720] px-5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-[#a61f19] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    Actualizar administrador
                </button>
            </div>
        </form>
    </div>
</x-layouts.admin>
