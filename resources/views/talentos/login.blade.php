<x-layouts.site :title="'Talentos - Login'">
    <section class="mx-auto max-w-3xl px-5 pt-10" style="margin-top: 180px;">
        @if (session('status'))
            <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
                {{ session('status') }}
            </div>
        @endif

        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Acceso Talentos</h1>
            <p class="mt-3 text-sm text-[#7b7b7b]">Entra al panel para gestionar tu perfil y tu catálogo.</p>

            <form action="{{ route('talents.login.store') }}" method="POST" class="mt-8 space-y-5">
                @csrf
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="lucille-product-field w-full">
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Password</label>
                    <input type="password" name="password" class="lucille-product-field w-full">
                </div>
                <label class="flex items-center gap-2 text-sm text-[#7b7b7b]">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 border border-[#3a3a3a] bg-transparent">
                    Remember me
                </label>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="lucille-button-solid">Entrar</button>
                    <a href="{{ route('talents.register') }}" class="lucille-button">Crear cuenta</a>
                </div>
            </form>
        </div>
    </section>
</x-layouts.site>
