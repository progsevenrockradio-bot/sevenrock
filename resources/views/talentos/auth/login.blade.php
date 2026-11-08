<x-layouts.site :title="'Talentos - Login'">
    <section class="mx-auto max-w-3xl px-4 sm:px-5 py-6 sm:py-10 mt-[100px] sm:mt-[180px]">
        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-4 sm:p-8">
            <h1 class="font-display text-2xl sm:text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Acceso Talentos</h1>
            <p class="mt-3 text-sm text-[#7b7b7b]">Entra al panel para gestionar tu perfil y tu catálogo.</p>

            @if (session('status'))
                <div class="mt-4 border border-emerald-500/30 bg-emerald-500/10 p-4 text-xs text-emerald-300 rounded-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-4 border border-red-500/30 bg-red-500/10 p-4 text-xs text-red-300 rounded-sm">
                    <ul class="list-disc pl-4 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('talents.login.store') }}" method="POST" class="mt-8 space-y-5">
                @csrf
                @if(request()->filled('redirect_to'))
                    <input type="hidden" name="redirect_to" value="{{ request('redirect_to') }}">
                @endif
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="lucille-product-field w-full">
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Password</label>
                    <input type="password" name="password" class="lucille-product-field w-full">
                </div>
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-[#7b7b7b]">
                        <input type="checkbox" name="remember" value="1" class="h-4 w-4 border border-[#3a3a3a] bg-transparent">
                        Recordarme
                    </label>
                    <a href="{{ route('talents.password.request') }}" class="text-xs text-[var(--lucille-accent)] hover:underline">¿Olvidaste tu contraseña?</a>
                </div>
                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="submit" class="lucille-button-solid w-full sm:w-auto text-center">Entrar</button>
                    <a href="{{ route('talents.register') }}" class="lucille-button w-full sm:w-auto text-center">Crear cuenta</a>
                </div>
            </form>
        </div>
    </section>
</x-layouts.site>
