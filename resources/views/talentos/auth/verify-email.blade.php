<x-layouts.site :title="'Talentos - Verificar Correo Electrónico'">
    <section class="mx-auto max-w-xl px-4 sm:px-5 py-6 sm:py-10 mt-[100px] sm:mt-[180px]">
        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-4 sm:p-8 rounded-sm shadow-xl">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-[#c32720]/15 text-[#c32720]">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </span>
                <h1 class="font-display text-2xl sm:text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Verifica tu Correo</h1>
            </div>

            <p class="mt-4 text-sm text-[#7b7b7b]">
                Para proteger la propiedad de tus canciones, maquetas y perfil, necesitamos verificar la dirección de correo electrónico asociada a tu cuenta:
            </p>

            @if ($talent && filled($talent->email))
                <div class="mt-3 rounded border border-white/10 bg-white/[0.03] px-3.5 py-2 font-mono text-xs text-white">
                    {{ $talent->email }}
                </div>
            @endif

            <p class="mt-3 text-xs text-[#7b7b7b]">
                Revisa tu bandeja de entrada y haz clic en el botón de confirmación. El enlace es seguro y caduca en 60 minutos.
            </p>

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

            <div class="mt-6 flex flex-col sm:flex-row items-center gap-3 pt-2">
                <form action="{{ route('talents.verification.send') }}" method="POST" class="w-full sm:w-auto">
                    @csrf
                    <button type="submit" class="lucille-button-solid w-full sm:w-auto text-center">
                        Reenviar correo de verificación
                    </button>
                </form>

                <a href="{{ route('talents.dashboard') }}" class="lucille-button w-full sm:w-auto text-center">
                    Ir al panel
                </a>

                <form action="{{ route('talents.logout') }}" method="POST" class="w-full sm:w-auto sm:ml-auto">
                    @csrf
                    <button type="submit" class="text-xs text-gray-400 hover:text-white underline transition-colors">
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </div>
    </section>
</x-layouts.site>

