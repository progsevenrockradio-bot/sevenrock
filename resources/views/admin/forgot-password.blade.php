<x-layouts.admin title="Recuperar Contraseña — Panel Admin">
    <div class="mx-auto mt-10 max-w-xl">
        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8 md:p-10">
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Recuperar Contraseña</h1>
            <p class="mt-3 text-sm text-[#7b7b7b]">
                Ingresa tu email de administrador y te enviaremos un enlace para restablecer tu contraseña.
            </p>

            @if (session('status'))
                <div class="mt-6 border border-[#1a4d1a] bg-[rgba(39,195,64,.08)] px-4 py-3 text-sm text-[#b1f3b6]">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-6 border border-[#5a1d1a] bg-[rgba(195,39,32,.08)] px-4 py-3 text-sm text-[#f3b6b1]">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('admin.password.email') }}" method="POST" class="mt-8 space-y-6">
                @csrf
                <div>
                    <label for="email" class="mb-2 block font-display text-xs uppercase tracking-[.18em] text-[#dcdcdc]">
                        Email de administrador
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        class="lucille-product-field w-full"
                        required
                        autofocus
                    >
                </div>

                <div class="flex items-center justify-between gap-4">
                    <button type="submit" class="lucille-button-solid">Enviar enlace</button>
                    <a href="{{ route('admin.login') }}" class="text-xs text-[#7b7b7b] transition hover:text-lucille-accent">
                        ← Volver al login
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
