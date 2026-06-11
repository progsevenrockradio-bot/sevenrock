<x-layouts.site :title="'Club de Fans - Iniciar Sesión'">
    <section class="mx-auto max-w-3xl px-5 py-16">
        <div class="border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-8 shadow-xl">
            <div class="text-center md:text-left">
                <span class="text-[10px] uppercase tracking-[.25em] text-[var(--lucille-accent)] font-semibold font-display">Seven Rock Radio</span>
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc] mt-1">Acceso Afiliados (Fan Club)</h1>
                <p class="mt-3 text-sm text-[#7b7b7b]">Inicia sesión para entrar al Muro de la Comunidad y acceder a descargas y música exclusiva de las bandas.</p>
            </div>

            @if ($errors->any())
                <div class="mt-6 border border-red-500/20 bg-red-500/5 rounded-[12px] p-4 text-xs text-red-400">
                    <ul class="list-disc pl-4 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('status'))
                <div class="mt-6 border border-green-500/20 bg-green-500/5 rounded-[12px] p-4 text-xs text-green-400">
                    {{ session('status') }}
                </div>
            @endif

            <form action="{{ route('afiliados.login.store') }}" method="POST" class="mt-8 space-y-5">
                @csrf
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="lucille-product-field w-full rounded-[8px] @error('email') border-red-500/50 @enderror" required>
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Contraseña</label>
                    <input type="password" name="password" class="lucille-product-field w-full rounded-[8px]" required>
                </div>
                <label class="flex items-center gap-2 text-sm text-[#7b7b7b] cursor-pointer select-none">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 border border-[#3a3a3a] bg-transparent rounded">
                    Recordarme en este equipo
                </label>
                <div class="flex flex-wrap gap-3 pt-2">
                    <button type="submit" class="lucille-button-solid rounded-[8px] px-8 py-3">Entrar al Club</button>
                    <a href="{{ route('afiliados.register') }}" class="lucille-button rounded-[8px] px-6 py-3">Registrarse Gratis</a>
                </div>
            </form>
        </div>
    </section>
</x-layouts.site>
