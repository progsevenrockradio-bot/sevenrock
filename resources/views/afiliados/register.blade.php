<x-layouts.site :title="'Club de Fans - Registro Gratuito'">
    <section class="mx-auto max-w-[1180px] px-5 py-16">
        <div class="grid gap-8 lg:grid-cols-[1fr_380px]">
            
            {{-- Formulario de Registro --}}
            <div class="border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-8 shadow-xl">
                <span class="text-[10px] uppercase tracking-[.25em] text-[var(--lucille-accent)] font-semibold font-display">Únete a la Señal</span>
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc] mt-1">Registro de Afiliados (Fan Club)</h1>
                <p class="mt-3 text-sm text-[#7b7b7b]">Crea tu cuenta gratuita en segundos. Participa activamente en el Muro de la Comunidad, interactúa con tus bandas favoritas y accede a contenidos inéditos de forma 100% gratuita.</p>

                @if ($errors->any())
                    <div class="mt-6 border border-red-500/20 bg-red-500/5 rounded-[12px] p-4 text-xs text-red-400">
                        <strong class="font-display uppercase tracking-[.08em] block mb-2">Se encontraron errores:</strong>
                        <ul class="list-disc pl-4 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('afiliados.register.store') }}" method="POST" class="mt-8 space-y-6">
                    @csrf
                    
                    {{-- Honeypot Spam Protection --}}
                    <div class="hidden" style="display:none !important" aria-hidden="true">
                        <input type="text" name="user_website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Nombre Completo / Apodo</label>
                            <input name="name" value="{{ old('name') }}" class="lucille-product-field w-full rounded-[8px]" required>
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Email</label>
                            <input type="email" name="email" value="{{ old('email') }}" class="lucille-product-field w-full rounded-[8px]" required>
                        </div>
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Contraseña</label>
                            <input type="password" name="password" class="lucille-product-field w-full rounded-[8px]" required>
                        </div>
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Confirmar contraseña</label>
                            <input type="password" name="password_confirmation" class="lucille-product-field w-full rounded-[8px]" required>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-4 pt-2">
                        <button type="submit" class="lucille-button-solid rounded-[8px] px-8 py-3">Unirse Gratis</button>
                        <a href="{{ route('afiliados.login') }}" class="lucille-button rounded-[8px] px-6 py-3">Ya tengo cuenta</a>
                    </div>
                </form>
            </div>

            {{-- Columna de Beneficios del Club --}}
            <aside class="border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-6 shadow-xl h-fit">
                <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc] border-b border-white/5 pb-3">Beneficios del Club</h2>
                <ul class="mt-6 space-y-5 text-sm text-[#7b7b7b]">
                    <li class="flex gap-3">
                        <span class="text-[var(--lucille-accent)] text-lg">✔</span>
                        <div>
                            <strong class="text-[#dcdcdc] block">Muro de la Comunidad</strong>
                            Escribe en nuestro foro rockero, comparte programas y enlaces, y opina libremente.
                        </div>
                    </li>
                    <li class="flex gap-3">
                        <span class="text-[var(--lucille-accent)] text-lg">✔</span>
                        <div>
                            <strong class="text-[#dcdcdc] block">Música & Descargas Inéditas</strong>
                            Escucha pistas exclusivas, bootlegs de directos y demos en primicia que suben las bandas asociadas.
                        </div>
                    </li>
                    <li class="flex gap-3">
                        <span class="text-[var(--lucille-accent)] text-lg">✔</span>
                        <div>
                            <strong class="text-[#dcdcdc] block">Sin Costes de por Vida</strong>
                            La radio financia el alojamiento. El apoyo y la interacción entre fans y bandas es totalmente gratuita.
                        </div>
                    </li>
                </ul>
            </aside>
        </div>
    </section>
</x-layouts.site>
