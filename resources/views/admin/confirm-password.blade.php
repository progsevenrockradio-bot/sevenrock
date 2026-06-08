<x-layouts.admin :title="'Confirmar Contraseña - '.$themeSettings->site_name">
    <div class="mx-auto mt-10 max-w-xl">
        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8 md:p-10 rounded">
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Confirmar Contraseña</h1>
            <p class="mt-3 text-sm text-[#7b7b7b]">Por seguridad, confirma tu contraseña de administrador antes de continuar a esta sección.</p>

            @if ($errors->any())
                <div class="mt-6 border border-[#5a1d1a] bg-[rgba(195,39,32,.08)] px-4 py-3 text-sm text-[#f3b6b1] rounded">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('password.confirm') }}" method="POST" class="mt-8 space-y-6">
                @csrf
                <div>
                    <label class="mb-2 block font-display text-xs uppercase tracking-[.18em] text-[#dcdcdc]">Contraseña del Administrador</label>
                    <input type="password" name="password" class="lucille-product-field w-full" required autofocus>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="lucille-button-solid py-2 px-6">Confirmar</button>
                    <a href="{{ route('admin.dashboard') }}" class="lucille-button py-2 px-6 text-xs uppercase tracking-wider text-gray-400 hover:text-white transition-colors">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
