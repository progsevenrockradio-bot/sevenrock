<x-layouts.site :title="'Agencias - Iniciar Sesión'">
    <section class="mx-auto max-w-3xl px-5 pt-16 pb-24">
        @if (session('status'))
            <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
                {{ session('status') }}
            </div>
        @endif

        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8 shadow-2xl rounded-[12px]">
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Acceso Agencias</h1>
            <p class="mt-3 text-sm text-[#7b7b7b]">Inicia sesión en tu portal para gestionar el perfil de tu agencia y las bandas representadas.</p>

            <form action="{{ route('agency.login.store') }}" method="POST" class="mt-8 space-y-5">
                @csrf
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Correo Electrónico</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="lucille-product-field w-full" required autofocus>
                    @error('email')
                        <span class="mt-1 block text-xs text-red-400 font-mono uppercase tracking-wider">{{ $message }}</span>
                    @enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Contraseña</label>
                    <input type="password" name="password" class="lucille-product-field w-full" required>
                    @error('password')
                        <span class="mt-1 block text-xs text-red-400 font-mono uppercase tracking-wider">{{ $message }}</span>
                    @enderror
                </div>
                <label class="flex items-center gap-2 text-sm text-[#7b7b7b] cursor-pointer">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 border border-[#3a3a3a] bg-transparent rounded">
                    Recordarme en este equipo
                </label>
                <div class="flex flex-wrap gap-3 pt-3">
                    <button type="submit" class="lucille-button-solid">Entrar</button>
                    <a href="{{ route('contact') }}" class="lucille-button">Solicitar Afiliación</a>
                </div>
            </form>
        </div>
    </section>
</x-layouts.site>
