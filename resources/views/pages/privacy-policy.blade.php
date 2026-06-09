<x-layouts.site title="Seven Rock Radio - Política de Privacidad" description="Política de privacidad y protección de datos personales de Seven Rock Radio.">
    @php
        $contact = $themeAppearance['contact'];
    @endphp

    <x-sections.page-heading title="Política de Privacidad" :image="$themeAppearance['background_url']" />

    <section class="py-16">
        <div class="lucille-content-box max-w-4xl mx-auto">
            <div class="rounded-2xl border border-white/[0.06] bg-[#10161b]/40 p-8 md:p-12 backdrop-blur-md shadow-2xl">
                <div class="prose prose-invert max-w-none text-[#9aa7b1] leading-relaxed space-y-8">
                    <h2 class="font-display text-2xl uppercase tracking-wider text-white border-b border-white/10 pb-4 mb-6">
                        Política de Privacidad de Seven Rock Radio
                    </h2>

                    <div class="space-y-4">
                        <h3 class="font-display text-lg uppercase tracking-wide text-lucille-accent">1. Información que recopilamos</h3>
                        <p>
                            En Seven Rock Radio respetamos profundamente tu privacidad. Recopilamos información de identificación personal únicamente cuando tú nos la proporcionas de forma directa y voluntaria (por ejemplo, al enviar un mensaje a través de nuestro formulario de <strong>Contacto</strong> o al suscribirte a las actualizaciones de nuestro blog).
                        </p>
                        <p>
                            Los datos personales recopilados pueden incluir tu nombre, dirección de correo electrónico y el contenido de tus mensajes. Asimismo, recopilamos de forma automatizada ciertos datos anónimos y estadísticos de uso (como tu dirección IP simplificada, tipo de navegador y ubicación geográfica general) con el único fin de monitorear la calidad de nuestra señal y comprender desde qué lugares sintonizan nuestra radio.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <h3 class="font-display text-lg uppercase tracking-wide text-lucille-accent">2. Uso de la información</h3>
                        <p>Utilizamos la información recolectada exclusivamente para los siguientes propósitos:</p>
                        <ul class="list-disc pl-6 space-y-2">
                            <li>Procesar y responder a tus mensajes, sugerencias, comentarios o peticiones musicales.</li>
                            <li>Garantizar el correcto funcionamiento de nuestra transmisión de audio en vivo y optimizar la velocidad de carga del blog.</li>
                            <li>Analizar estadísticas de audiencia consolidadas y anónimas para evaluar el impacto de nuestros programas.</li>
                        </ul>
                    </div>

                    <div class="space-y-4">
                        <h3 class="font-display text-lg uppercase tracking-wide text-lucille-accent">3. Compartir tus datos</h3>
                        <p>
                            Mantenemos un compromiso absoluto de confidencialidad. <strong>No vendemos, alquilamos, comercializamos ni compartimos</strong> tu información de identificación personal con terceros bajo ningún concepto para fines publicitarios o lucrativos. Tus datos solo se procesan internamente para cumplir con las interacciones que inicias con nosotros.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <h3 class="font-display text-lg uppercase tracking-wide text-lucille-accent">4. Tus derechos</h3>
                        <p>
                            Tienes el derecho de solicitar el acceso, rectificación, portabilidad o eliminación total de tus datos personales de nuestros registros en cualquier momento. Si deseas ejercer este derecho o tienes alguna duda sobre el tratamiento de tus datos, puedes enviarnos una solicitud formal por escrito a nuestro correo de contacto:
                        </p>
                        <div class="mt-4 rounded-lg bg-white/[0.02] border border-white/5 p-4 text-center">
                            <a href="mailto:{{ $contact['email'] ?? 'prog.sevenrockradio@gmail.com' }}" class="font-display text-base text-white hover:text-lucille-accent transition-colors">
                                {{ $contact['email'] ?? 'prog.sevenrockradio@gmail.com' }}
                            </a>
                            @if(!empty($contact['phone_primary']))
                                <p class="mt-2 text-xs text-[#7b7b7b]">Teléfono: {{ $contact['phone_primary'] }}</p>
                            @endif
                            @if(!empty($contact['address']))
                                <p class="mt-1 text-xs text-[#7b7b7b]">Dirección: {{ $contact['address'] }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.site>
