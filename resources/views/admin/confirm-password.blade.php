<x-layouts.admin title="Confirmar contraseña - {{ $themeSettings->site_name ?? 'Seven Rock Radio' }}">
    <div class="mx-auto mt-8 max-w-md font-sans">
        <div class="rounded-xl border border-white/10 bg-[#101012] p-6 space-y-5">
            <header class="border-b border-white/10 pb-4">
                <div class="flex items-center gap-2 text-[11px] font-medium uppercase tracking-wider text-[#c32720]">
                    <span>Verificación de seguridad</span>
                </div>
                <h1 class="mt-1 font-display text-xl font-bold uppercase tracking-wide text-[#dcdcdc]">Confirmar contraseña de administrador</h1>
                <p class="mt-1 text-xs text-gray-400 leading-relaxed">
                    Esta sección modifica ajustes sensibles del sistema. Introduce tu contraseña actual para continuar.
                </p>
            </header>

            @if ($errors->any())
                <div class="rounded-lg border border-red-500/30 bg-red-500/10 px-3.5 py-2.5 text-xs text-red-200" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('password.confirm') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="confirm-admin-password" class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">
                        Contraseña actual
                    </label>
                    <input type="password" id="confirm-admin-password" name="password" required autofocus
                           class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-white/10">
                    <a href="{{ route('admin.dashboard') }}"
                       class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/5 px-4 text-xs font-medium text-gray-300 transition-colors hover:bg-white/10 hover:text-white">
                        Cancelar
                    </a>
                    <button type="submit"
                            class="inline-flex h-9 items-center rounded-lg bg-[#c32720] px-5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-[#a61f19] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                        Confirmar acceso
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
