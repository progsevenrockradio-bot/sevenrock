<x-layouts.site :title="'Firma de Contrato - ' . $contract->title">
    <section class="mx-auto max-w-4xl px-5 py-16">
        <div class="border border-white/10 bg-gradient-to-b from-[#10151a]/95 to-[#070a0d]/98 backdrop-blur-md rounded-[20px] p-8 md:p-12 shadow-[0_20px_50px_rgba(0,0,0,0.5)]">
            <div class="text-center border-b border-white/5 pb-6 mb-8">
                <span class="text-[10px] uppercase tracking-[.25em] text-[var(--lucille-accent)] font-semibold font-display">Portal de Firma Digital</span>
                <h1 class="font-display text-2xl md:text-3xl uppercase tracking-[.12em] text-white mt-1">{{ $contract->title }}</h1>
                <p class="mt-2 text-xs text-gray-500 font-mono">ID de Documento: DOC-{{ $contract->token }}</p>
            </div>

            @if ($errors->any())
                <div class="mb-8 border border-red-500/20 bg-red-500/5 rounded-[12px] p-4 text-xs text-red-400">
                    <strong class="font-display uppercase tracking-[.08em] block mb-2">Se encontraron errores de validación:</strong>
                    <ul class="list-disc pl-4 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Contenedor del cuerpo del contrato --}}
            <div class="mb-8 border border-white/10 bg-black/45 rounded-[12px] p-6 max-h-[350px] overflow-y-auto text-sm leading-relaxed text-gray-300 scrollbar-thin scrollbar-thumb-white/10">
                <div class="prose prose-invert max-w-none">
                    {!! $contract->formatted_content !!}
                </div>
            </div>

            {{-- Formulario de Firma --}}
            <form action="{{ route('contratos.sign', ['token' => $contract->token]) }}" method="POST" class="space-y-6">
                @csrf

                <div class="grid gap-5 md:grid-cols-3 items-end">
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b] font-semibold">Escribe tu Nombre Completo (Firma Electrónica)</label>
                        <input name="nombre_completo" value="{{ old('nombre_completo', $contract->signer_name) }}" class="lucille-product-field w-full rounded-[8px]" required placeholder="Tu nombre y apellidos completos">
                    </div>
                    <div>
                        <div class="text-[10px] text-[#7b7b7b] uppercase tracking-wider mb-2 font-mono">IP de Registro:</div>
                        <div class="p-3 bg-white/5 border border-white/10 rounded-[8px] font-mono text-xs text-gray-300 text-center select-none">
                            {{ request()->ip() }}
                        </div>
                    </div>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b] font-semibold">País de Residencia / Origen</label>
                        <input name="country" value="{{ old('country') }}" class="lucille-product-field w-full rounded-[8px]" required placeholder="Ej. Colombia, España, México">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b] font-semibold">Ciudad</label>
                        <input name="city" value="{{ old('city') }}" class="lucille-product-field w-full rounded-[8px]" required placeholder="Ej. Bogotá, Madrid, CDMX">
                    </div>
                </div>

                {{-- Cláusula de consentimiento Clickwrap --}}
                <div class="border border-white/5 bg-white/[0.01] rounded-[12px] p-5">
                    <div class="flex items-start gap-4">
                        <input type="checkbox" name="aceptar_terminos" id="aceptar_terminos" value="1" required class="mt-1 h-5 w-5 rounded border-white/10 bg-black text-[var(--lucille-accent)] focus:ring-0 focus:ring-offset-0 cursor-pointer">
                        <label for="aceptar_terminos" class="text-xs text-gray-400 leading-relaxed cursor-pointer select-none">
                            <strong class="text-white">Declaración de consentimiento:</strong> Acepto y declaro mi total conformidad con los términos y cláusulas estipulados en este Contrato Digital. Entiendo y consiento que la marcación de esta casilla y el envío de este formulario constituyen una firma electrónica con pleno valor legal, probatorio y vinculante a todos los efectos legales aplicables.
                        </label>
                    </div>
                </div>

                <div class="pt-4 text-center">
                    <button type="submit" class="lucille-button-solid text-sm uppercase py-3.5 px-10 tracking-[.15em] rounded-full shadow-[0_4px_25px_rgba(195,39,32,0.3)] hover:scale-103 active:scale-98 transition-all duration-300">
                        Formalizar y Firmar Contrato 🖋
                    </button>
                </div>
            </form>
        </div>
    </section>
</x-layouts.site>
