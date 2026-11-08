@php
    $basic = $basicPlan ?? \App\Support\TalentPlan::definition('basic');
    $limits = $basic['limits'] ?? ['songs' => 5, 'photos' => 10, 'storage_mb' => 200, 'videos' => 0, 'documents' => 0];
    $maxSizes = $basic['max_file_size_mb'] ?? ['songs' => 15, 'photos' => 10];
    $priceLabel = $basic['monthly_label'] ?? '3,50 €/mes';
    $paypalUrl = \App\Support\TalentPlan::paypalUrl('basic') ?? 'https://www.paypal.com/ncp/payment/FTNT3HTH6KXR2';
    $contactEmail = filled($themeAppearance['contact']['email'] ?? null)
        ? trim((string) $themeAppearance['contact']['email'])
        : 'sevenrockradio@gmail.com';
    $preselectedBand = trim((string) request()->query('band', ''));
    $defaultInquiryMessage = "Hola equipo de Seven Rock Radio,\n\nSolicito activar o renovar el Plan Básico (3,50 €/mes) en el Muro del Rock" . ($preselectedBand !== '' ? " para la banda \"{$preselectedBand}\"" : '') . ".\n\nReferencia de pago de PayPal (si ya has pagado) o dudas sobre el plan:";
@endphp

<x-layouts.site
    :title="'Seven Rock Radio - Tu banda en el Muro del Rock (Plan Básico · 3,50 €/mes)'"
    description="Reúne tu música, biografía, fotografías y redes en una ficha pública fácil de compartir en el Muro del Rock de Seven Rock Radio (Plan Básico · 3,50 €/mes)."
>
    <div class="relative min-h-screen bg-[#121215] text-[#dcdcdc] font-sans">
        {{-- Textura sutil Lucille --}}
        <div class="fixed inset-0 z-0 bg-cover bg-center bg-no-repeat pointer-events-none opacity-20 mix-blend-luminosity" style="background-image: -webkit-image-set(url('{{ asset('assets/lucille/dark-background.webp') }}') 1x, url('{{ asset('assets/lucille/dark-background.jpg') }}') 1x); background-image: image-set(url('{{ asset('assets/lucille/dark-background.webp') }}') type('image/webp'), url('{{ asset('assets/lucille/dark-background.jpg') }}') type('image/jpeg'));"></div>
        <div class="fixed inset-0 z-0 bg-gradient-to-b from-[#121215]/80 via-[#121215]/92 to-[#121215] pointer-events-none"></div>

        <section class="relative z-10 mx-auto max-w-[960px] px-4 sm:px-6 pt-28 md:pt-36 pb-20 space-y-6">
            {{-- Migas de pan --}}
            <nav aria-label="Ruta de navegación" class="flex flex-wrap items-center justify-between gap-3 text-xs text-[#8b8b8b] border-b border-white/10 pb-3">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('home') }}" class="hover:text-white transition-colors">Inicio</a>
                    <span>/</span>
                    <a href="{{ route('talents.explore') }}" class="hover:text-white transition-colors">Muro del Rock</a>
                    <span>/</span>
                    <span class="text-[#dcdcdc] font-semibold">Plan Básico</span>
                </div>
                <a href="{{ route('talents.explore') }}" class="inline-flex items-center gap-1.5 text-xs font-display uppercase tracking-[.12em] text-[#dcdcdc] hover:text-[var(--lucille-accent)] transition-colors">
                    <span>← Explorar bandas</span>
                </a>
            </nav>

            @if (session('success'))
                <div class="rounded-sm border border-emerald-500/35 bg-emerald-500/10 p-4 text-sm text-emerald-300" role="status">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-sm border border-red-500/35 bg-red-500/10 p-4 text-sm text-red-300" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Bloque Principal Visible: Hero + Formulario Compacto --}}
            <div class="grid gap-6 lg:grid-cols-[1.05fr_.95fr] items-start">
                {{-- 1–6: Título, Descripción, Precio, Prestaciones, Botón, Explicación corta y Aclaración editorial --}}
                <header class="border border-white/10 bg-[#141417] rounded-sm p-5 sm:p-7 shadow-[0_28px_72px_rgba(0,0,0,.58)] space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="inline-flex items-center rounded-sm border border-[#c32720]/40 bg-[#c32720]/10 px-2.5 py-1 text-[11px] font-display uppercase tracking-[.16em] text-[#ff6b63]">
                            Plan Básico
                        </span>
                        <span class="font-display text-2xl sm:text-3xl font-bold text-white">
                            {{ $priceLabel }}
                        </span>
                    </div>

                    <div>
                        <h1 class="font-display text-2xl sm:text-4xl uppercase font-bold tracking-[.08em] text-white leading-tight">
                            Tu banda en el Muro del Rock
                        </h1>
                        <p class="mt-2.5 text-sm sm:text-base text-[#b5b5b5] leading-relaxed">
                            Reúne tu música, biografía, fotografías y redes en una ficha pública fácil de compartir.
                        </p>
                    </div>

                    <ul class="grid gap-2.5 sm:grid-cols-2 border-y border-white/10 py-4 text-xs sm:text-sm text-[#dcdcdc]">
                        <li class="flex items-center gap-2.5">
                            <span class="text-[#c32720] font-bold">✓</span>
                            <span>{{ $limits['songs'] }} canciones.</span>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-[#c32720] font-bold">✓</span>
                            <span>{{ $limits['photos'] }} fotografías.</span>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-[#c32720] font-bold">✓</span>
                            <span>{{ $limits['storage_mb'] }} MB de almacenamiento.</span>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-[#c32720] font-bold">✓</span>
                            <span>Biografía, redes y enlaces a YouTube.</span>
                        </li>
                    </ul>

                    <div class="space-y-4">
                        <div class="flex flex-col sm:flex-row gap-3">
                            <a href="{{ $paypalUrl }}" target="_blank" rel="noopener noreferrer" class="lucille-button-solid block w-full sm:w-auto rounded-sm px-6 py-3 text-xs sm:text-sm font-display uppercase tracking-[.14em] text-center">
                                Pagar un mes de Básico con PayPal
                            </a>
                            <a href="#solicitar-plan-basico" class="lucille-button block w-full sm:w-auto rounded-sm px-6 py-3 text-xs sm:text-sm font-display uppercase tracking-[.14em] text-center">
                                Solicitar información
                            </a>
                        </div>

                        {{-- Aclaración sobre verificación y ciclo manual --}}
                        <div class="rounded-sm border border-white/10 bg-[#141417] p-4 text-xs text-[#b5b5b5] space-y-2 leading-relaxed">
                            <p class="font-semibold text-white flex items-center gap-2">
                                <span class="text-[var(--lucille-accent)]">ℹ️</span>
                                <span>Verificación manual para activación o renovación:</span>
                            </p>
                            <p>
                                El abono mediante PayPal es un <strong>pago único de un mes (3,50 €)</strong> sin cobros automáticos ni suscripción recurrente.
                            </p>
                            <p>
                                <strong>El pago requiere verificación del administrador para activar o renovar.</strong> Puede abonarlo un integrante, representante o tercero con su propia cuenta PayPal o tarjeta (no es necesario que su nombre o correo coincida con el de la banda). Tras completar el pago, indícanos el correo de tu cuenta de banda y la referencia de la operación en el formulario a continuación para que el equipo compruebe el cobro recibido y aplique la vigencia.
                            </p>
                        </div>

                        <p class="border-l-2 border-[#c32720] pl-3 text-xs text-[#a8a8b0]">
                            El plan no garantiza emisión en radio.
                        </p>
                    </div>
                </header>

                {{-- 7. Formulario compacto (conserva campos y validaciones existentes) --}}
                <div id="solicitar-plan-basico" class="border border-white/10 bg-[#141417] rounded-sm p-5 sm:p-6 shadow-[0_28px_72px_rgba(0,0,0,.58)] scroll-mt-28">
                    <h2 class="font-display text-lg sm:text-xl uppercase tracking-[.10em] text-white">
                        Notificar pago o solicitar información
                    </h2>
                    <p class="mt-1 text-xs text-[#8b8b8b]">
                        Indica el correo de tu cuenta de banda y la referencia de la operación de PayPal para activar o renovar, o plantéanos cualquier consulta antes de contratar.
                    </p>

                    <form method="POST" action="{{ route('contact.send') }}" class="mt-4 space-y-3.5">
                        @csrf
                        <div class="hidden" style="display:none !important" aria-hidden="true">
                            <input type="text" name="user_website" tabindex="-1" autocomplete="off">
                        </div>
                        <input type="hidden" name="subject" value="general">
                        <input type="hidden" name="plan_interest" value="basic">

                        <div class="grid gap-3.5 sm:grid-cols-2">
                            <div>
                                <label for="basic-plan-name" class="mb-1 block font-display text-[11px] uppercase tracking-[.14em] text-[#dcdcdc]">
                                    Persona de contacto *
                                </label>
                                <input
                                    type="text"
                                    id="basic-plan-name"
                                    name="name"
                                    value="{{ old('name') }}"
                                    required
                                    placeholder="Tu nombre"
                                    class="lucille-form-field w-full"
                                >
                                @error('name')
                                    <span class="mt-1 block text-[11px] text-red-400">{{ $message }}</span>
                                @enderror
                            </div>

                            <div>
                                <label for="basic-plan-band" class="mb-1 block font-display text-[11px] uppercase tracking-[.14em] text-[#dcdcdc]">
                                    Banda o proyecto
                                </label>
                                <input
                                    type="text"
                                    id="basic-plan-band"
                                    name="band_name"
                                    value="{{ old('band_name', $preselectedBand) }}"
                                    placeholder="Nombre de tu banda"
                                    class="lucille-form-field w-full"
                                >
                            </div>
                        </div>

                        <div class="grid gap-3.5 sm:grid-cols-2">
                            <div>
                                <label for="basic-plan-email" class="mb-1 block font-display text-[11px] uppercase tracking-[.14em] text-[#dcdcdc]">
                                    Correo electrónico *
                                </label>
                                <input
                                    type="email"
                                    id="basic-plan-email"
                                    name="email"
                                    value="{{ old('email', request()->query('email', '')) }}"
                                    required
                                    placeholder="banda@ejemplo.com"
                                    class="lucille-form-field w-full"
                                >
                                @error('email')
                                    <span class="mt-1 block text-[11px] text-red-400">{{ $message }}</span>
                                @enderror
                            </div>

                            <div>
                                <label for="basic-plan-phone" class="mb-1 block font-display text-[11px] uppercase tracking-[.14em] text-[#dcdcdc]">
                                    Teléfono / WhatsApp (opcional)
                                </label>
                                <input
                                    type="tel"
                                    id="basic-plan-phone"
                                    name="phone"
                                    value="{{ old('phone') }}"
                                    placeholder="+34 / +58 ..."
                                    class="lucille-form-field w-full"
                                >
                            </div>
                        </div>

                        <div>
                            <label for="basic-plan-message" class="mb-1 block font-display text-[11px] uppercase tracking-[.14em] text-[#dcdcdc]">
                                Mensaje *
                            </label>
                            <textarea
                                id="basic-plan-message"
                                name="message"
                                rows="3"
                                required
                                class="lucille-form-field w-full"
                            >{{ old('message', $defaultInquiryMessage) }}</textarea>
                            @error('message')
                                <span class="mt-1 block text-[11px] text-red-400">{{ $message }}</span>
                            @enderror
                        </div>

                        <button type="submit" class="lucille-button-solid w-full rounded-sm py-3 text-xs font-display uppercase tracking-[.14em]">
                            Solicitar el Plan Básico
                        </button>

                        {{-- Enlaces de privacidad y condiciones al pie del formulario --}}
                        <div class="border-t border-white/10 pt-3 flex flex-wrap items-center justify-between gap-2 text-[11px] text-[#8b8b8b]">
                            <div class="flex flex-wrap items-center gap-3">
                                <a href="{{ route('privacy-policy') }}" class="text-[#dcdcdc] hover:text-[var(--lucille-accent)] underline transition-colors">
                                    Política de Privacidad
                                </a>
                                <a href="{{ route('copyright-policy') }}" class="text-[#dcdcdc] hover:text-[var(--lucille-accent)] underline transition-colors">
                                    Términos y Condiciones
                                </a>
                            </div>
                            <a href="{{ route('talents.login') }}" class="text-[#9d9d9d] hover:text-white transition-colors">
                                Acceso bandas →
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Detalles secundarios agrupados en desplegables accesibles --}}
            <div class="space-y-2.5" aria-label="Información adicional del Plan Básico">
                <details class="group border border-white/10 bg-[#141417] rounded-sm">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 sm:px-5 py-3.5 font-display text-xs sm:text-sm uppercase tracking-[.12em] text-[#dcdcdc] hover:text-white focus:outline-none focus-visible:ring-1 focus-visible:ring-[#c32720]">
                        <span>Formatos y tamaños de archivo</span>
                        <span class="text-[#c32720] transition-transform duration-200 group-open:rotate-180" aria-hidden="true">▾</span>
                    </summary>
                    <div class="border-t border-white/10 px-4 sm:px-5 py-3.5 text-xs sm:text-sm text-[#a8a8b0] space-y-1.5">
                        <p>• <strong class="text-[#dcdcdc]">Canciones:</strong> hasta {{ $limits['songs'] }} archivos MP3 (máximo {{ $maxSizes['songs'] }} MB por canción).</p>
                        <p>• <strong class="text-[#dcdcdc]">Fotografías:</strong> hasta {{ $limits['photos'] }} imágenes JPG, PNG o WEBP (máximo {{ $maxSizes['photos'] }} MB por foto).</p>
                        <p>• <strong class="text-[#dcdcdc]">Almacenamiento total:</strong> {{ $limits['storage_mb'] }} MB conjuntos.</p>
                        <p>• <strong class="text-[#dcdcdc]">Vídeo y documentos:</strong> incluye enlaces a YouTube en el perfil; no incluye subida de archivos de vídeo ni documentos PDF.</p>
                    </div>
                </details>

                <details class="group border border-white/10 bg-[#141417] rounded-sm">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 sm:px-5 py-3.5 font-display text-xs sm:text-sm uppercase tracking-[.12em] text-[#dcdcdc] hover:text-white focus:outline-none focus-visible:ring-1 focus-visible:ring-[#c32720]">
                        <span>Cómo funciona la contratación</span>
                        <span class="text-[#c32720] transition-transform duration-200 group-open:rotate-180" aria-hidden="true">▾</span>
                    </summary>
                    <div class="border-t border-white/10 px-4 sm:px-5 py-3.5 text-xs sm:text-sm text-[#a8a8b0] space-y-1.5">
                        <p>1. Envías tu solicitud con el formulario superior (o te registras en el <a href="{{ route('talents.register', ['plan' => 'basic']) }}" class="text-[#dcdcdc] underline hover:text-white">Portal de Bandas</a>).</p>
                        <p>2. Te respondemos desde <strong class="text-[#dcdcdc]">{{ $contactEmail }}</strong> para coordinar el pago manual de {{ $priceLabel }} (sin cargos automáticos).</p>
                        <p>3. Confirmado el abono, activamos el mes hasta la fecha acordada (inclusive). Renovar por adelantado suma un mes completo sin perder días; si vence, la ficha se pausa y los archivos se conservan.</p>
                    </div>
                </details>

                <details class="group border border-white/10 bg-[#141417] rounded-sm">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 sm:px-5 py-3.5 font-display text-xs sm:text-sm uppercase tracking-[.12em] text-[#dcdcdc] hover:text-white focus:outline-none focus-visible:ring-1 focus-visible:ring-[#c32720]">
                        <span>Publicación web y programación de radio</span>
                        <span class="text-[#c32720] transition-transform duration-200 group-open:rotate-180" aria-hidden="true">▾</span>
                    </summary>
                    <div class="border-t border-white/10 px-4 sm:px-5 py-3.5 text-xs sm:text-sm text-[#a8a8b0] space-y-1.5">
                        <p>Contratar el Plan Básico es opcional y no condiciona la publicación de tus maquetas en el catálogo web.</p>
                        <p>El directorio del Muro del Rock es independiente de la programación musical de la emisora y no incluye promesas de emisión en radio ni posiciones destacadas.</p>
                        <p>Puedes consultar las métricas generales de streaming y web en el <a href="{{ route('audience.report.latest') }}" class="text-[#c32720] hover:underline">informe semanal de audiencia</a> de la emisora.</p>
                    </div>
                </details>
            </div>
        </section>
    </div>
</x-layouts.site>
