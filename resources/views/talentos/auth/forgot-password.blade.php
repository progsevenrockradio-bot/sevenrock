<x-layouts.site :title="'Talentos - Recuperar Contraseña'">
    <section class="mx-auto max-w-xl px-4 sm:px-5 py-6 sm:py-10 mt-[100px] sm:mt-[180px]">
        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-4 sm:p-8 rounded-sm shadow-xl">
            <h1 class="font-display text-2xl sm:text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Recuperar Contraseña</h1>
            <p class="mt-3 text-sm text-[#7b7b7b]">Introduce el correo de tu banda o artista registrado. Te enviaremos un enlace para restablecer tu contraseña.</p>

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

            <form action="{{ route('talents.password.email') }}" method="POST" class="mt-6 space-y-5">
                @csrf
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Correo electrónico</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus class="lucille-product-field w-full" placeholder="tucorreo@ejemplo.com">
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <button type="submit" class="lucille-button-solid w-full sm:w-auto text-center">Enviar enlace</button>
                    <a href="{{ route('talents.login') }}" class="lucille-button w-full sm:w-auto text-center">Volver al acceso</a>
                </div>
            </form>
        </div>
    </section>
</x-layouts.site>
