<x-layouts.site :title="'Talentos - Login'">
    <section class="mx-auto max-w-3xl px-5 pt-10" style="margin-top: 180px;">
        @if (session('status'))
            <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
                {{ session('status') }}
            </div>
        @endif

        {{-- P1-3: Errores de autenticación en español --}}
        @if ($errors->any())
            <div class="mb-6 border border-lucille-accent/30 bg-[rgba(195,39,32,.08)] px-4 py-3 text-sm text-[#f5a5a2] flex items-start gap-3">
                <svg class="shrink-0 mt-0.5" viewBox="0 0 20 20" width="16" height="16" fill="none" stroke="#c32720" stroke-width="2">
                    <circle cx="10" cy="10" r="9"/><line x1="10" y1="6" x2="10" y2="10"/><circle cx="10" cy="14" r=".5" fill="#c32720"/>
                </svg>
                <ul class="space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Acceso Talentos</h1>
            <p class="mt-3 text-sm text-[#7b7b7b]">Entra al panel para gestionar tu perfil y tu catálogo.</p>

            <form action="{{ route('talents.login.store') }}" method="POST" class="mt-8 space-y-5" novalidate>
                @csrf
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]" for="email">Email</label>
                    <input
                        type="email" name="email" id="email"
                        value="{{ old('email') }}"
                        class="lucille-product-field w-full @error('email') border-lucille-accent/60 @enderror"
                        autocomplete="email"
                        aria-describedby="email-error"
                    >
                    @error('email')
                        <p id="email-error" class="mt-1.5 text-[11px] text-[#f5a5a2] flex items-center gap-1">
                            <svg viewBox="0 0 12 12" width="10" height="10" fill="none" stroke="#c32720" stroke-width="2"><circle cx="6" cy="6" r="5"/><line x1="6" y1="3.5" x2="6" y2="6"/><circle cx="6" cy="8" r=".4" fill="#c32720"/></svg>
                            {{ $message }}
                        </p>
                    @enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]" for="password">Contraseña</label>
                    <input
                        type="password" name="password" id="password"
                        class="lucille-product-field w-full @error('password') border-lucille-accent/60 @enderror"
                        autocomplete="current-password"
                        aria-describedby="password-error"
                    >
                    @error('password')
                        <p id="password-error" class="mt-1.5 text-[11px] text-[#f5a5a2] flex items-center gap-1">
                            <svg viewBox="0 0 12 12" width="10" height="10" fill="none" stroke="#c32720" stroke-width="2"><circle cx="6" cy="6" r="5"/><line x1="6" y1="3.5" x2="6" y2="6"/><circle cx="6" cy="8" r=".4" fill="#c32720"/></svg>
                            {{ $message }}
                        </p>
                    @enderror
                </div>
                <label class="flex items-center gap-2 text-sm text-[#7b7b7b] select-none cursor-pointer">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 border border-[#3a3a3a] bg-transparent accent-[#c32720]">
                    Recordar sesión
                </label>
                <div class="flex flex-wrap gap-3 items-center">
                    <button type="submit" class="lucille-button-solid">Entrar</button>
                    {{-- P0-3: Aviso contextual de que el registro aún no está disponible --}}
                    <span
                        class="lucille-button cursor-not-allowed opacity-60 relative group"
                        title="El registro de talentos estará disponible muy pronto"
                        tabindex="0"
                        aria-label="Crear cuenta - Próximamente disponible"
                    >
                        Crear cuenta
                        <span class="pointer-events-none absolute -top-9 left-1/2 -translate-x-1/2 whitespace-nowrap rounded bg-[#111] border border-[#2b2b2b] px-2.5 py-1 text-[10px] uppercase tracking-[.1em] text-[#aaa] opacity-0 group-hover:opacity-100 group-focus:opacity-100 transition-opacity duration-200">
                            🔒 Próximamente
                        </span>
                    </span>
                </div>
            </form>
        </div>
    </section>
</x-layouts.site>
