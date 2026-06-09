<x-layouts.site title="Seven Rock Radio - Términos y Copyright" description="Términos de uso, política de copyright y descargo de responsabilidad por contenido de terceros de Seven Rock Radio.">
    @php
        $contact = $themeAppearance['contact'];
    @endphp

    <x-sections.page-heading title="Términos y Copyright" :image="$themeAppearance['background_url']" />

    <section class="py-16">
        <div class="lucille-content-box max-w-4xl mx-auto">
            <div class="rounded-2xl border border-white/[0.06] bg-[#10161b]/40 p-8 md:p-12 backdrop-blur-md shadow-2xl">
                <div class="prose prose-invert max-w-none text-[#9aa7b1] leading-relaxed space-y-8">
                    <h2 class="font-display text-2xl uppercase tracking-wider text-white border-b border-white/10 pb-4 mb-6">
                        Política de Infracción de Copyright y Descargo de Responsabilidad
                    </h2>

                    <div class="space-y-4">
                        <h3 class="font-display text-lg uppercase tracking-wide text-lucille-accent">1. Naturaleza de la plataforma</h3>
                        <p>
                            Seven Rock Radio es un medio de comunicación y difusión cultural enfocado en promover y apreciar los géneros del Rock y el Metal. Nuestro sitio web aloja transmisiones de audio por streaming en vivo, podcasts, letras de canciones, reseñas de bandas, noticias y programas de entretenimiento independientes.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <h3 class="font-display text-lg uppercase tracking-wide text-lucille-accent">2. Responsabilidad de terceros</h3>
                        <p>
                            Los contenidos, opiniones, comentarios y música emitidos durante los programas de radio en vivo o grabados en formato de podcast son responsabilidad exclusiva de sus respectivos locutores, productores, curadores y DJs invitados.
                        </p>
                        <p>
                            Seven Rock Radio actúa estrictamente como una plataforma de retransmisión técnica y cultural. Exigimos a todos nuestros colaboradores y locutores que cuenten con las licencias y permisos de emisión necesarios para los materiales que reproducen; sin embargo, Seven Rock Radio no se hace responsable legal ni civilmente por las infracciones de derechos de autor o declaraciones difamatorias cometidas de forma individual por dichos curadores, locutores independientes o usuarios en sus secciones específicas.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <h3 class="font-display text-lg uppercase tracking-wide text-lucille-accent">3. Propiedad de las bandas y artistas</h3>
                        <p>
                            Toda la música reproducida en nuestra emisora, así como las marcas comerciales, logotipos, imágenes promocionales y letras de canciones (exhibidas únicamente con fines informativos, educativos y de apreciación cultural) son propiedad exclusiva de sus respectivos autores, compositores, bandas, sellos discográficos o representantes legales. Seven Rock Radio no reclama ningún derecho de propiedad intelectual sobre los temas musicales que forman parte de la transmisión de la emisora.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <h3 class="font-display text-lg uppercase tracking-wide text-lucille-accent">4. Notificación de Infracción (DMCA / Reclamaciones de Autor)</h3>
                        <p>
                            Si eres un artista, sello discográfico, titular de derechos de autor o representante legal debidamente acreditado y consideras que algún contenido transmitido, alojado o enlazado en Seven Rock Radio infringe tus derechos de propiedad intelectual, te solicitamos ponerte en contacto con nosotros de inmediato.
                        </p>
                        <p>
                            Procederemos con la máxima celeridad a investigar y, de ser procedente, retirar o deshabilitar el material en disputa. Para enviar un reclamo formal de infracción, envíanos un correo electrónico detallando la obra protegida, el enlace o programa donde se cometió la presunta infracción, tu información de contacto y una declaración de propiedad al siguiente buzón:
                        </p>
                        <div class="mt-4 rounded-lg bg-white/[0.02] border border-white/5 p-4 text-center">
                            <a href="mailto:{{ $contact['email'] ?? 'prog.sevenrockradio@gmail.com' }}" class="font-display text-base text-white hover:text-lucille-accent transition-colors">
                                {{ $contact['email'] ?? 'prog.sevenrockradio@gmail.com' }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.site>
