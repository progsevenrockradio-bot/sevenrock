<x-layouts.admin title="Manual de configuración - {{ $themeSettings->site_name ?? 'Seven Rock Radio' }}">
    @php
        $admin = $themeAppearance['admin_texts'];
    @endphp

    <style>
        @media print {
            .manual-sidebar,
            .manual-actions,
            .manual-no-print {
                display: none !important;
            }
            .manual-content {
                display: block !important;
            }
            .manual-section {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>

    <div class="space-y-5 font-sans">
        {{-- Cabecera compacta --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="flex items-center gap-2 text-[11px] font-medium uppercase tracking-wider text-gray-400">
                    <a href="{{ route('admin.settings.edit') }}" class="hover:text-white transition-colors">Configuración general</a>
                    <span class="text-white/20">/</span>
                    <span class="text-[#c32720]">Manual interno</span>
                </div>
                <h1 class="mt-1 font-display text-2xl font-bold uppercase tracking-wide text-[#dcdcdc]">Manual de configuración general</h1>
                <p class="mt-1 text-xs text-gray-400 max-w-3xl">
                    Referencia rápida de las cuatro áreas de <code class="text-gray-200">/admin/settings</code>: qué controla cada bloque, dónde impacta y qué precauciones tomar.
                </p>
            </div>
            <div class="manual-actions flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.settings.manual.pdf') }}" target="_blank"
                   class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3.5 text-xs font-medium text-gray-200 hover:bg-white/10 hover:text-white">
                    <span>Exportar PDF</span>
                </a>
                <a href="{{ route('admin.settings.edit') }}"
                   class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#a61f19]">
                    <span>← Volver a configuración</span>
                </a>
            </div>
        </header>

        {{-- Resumen de las 4 pestañas --}}
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-xs">
            <article class="rounded-xl border border-white/10 bg-[#101012] p-4">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-[#c32720]">Pestaña 1</span>
                <h2 class="mt-1 font-display text-sm font-bold uppercase text-[#dcdcdc]">Apariencia y multimedia</h2>
                <p class="mt-1 text-[11px] text-gray-400">Identidad de marca, fuentes, paleta de colores Lucille, vídeo Hero y carrusel de portada.</p>
            </article>
            <article class="rounded-xl border border-white/10 bg-[#101012] p-4">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-[#c32720]">Pestaña 2</span>
                <h2 class="mt-1 font-display text-sm font-bold uppercase text-[#dcdcdc]">Contenido y textos (JSON)</h2>
                <p class="mt-1 text-[11px] text-gray-400">Estructuras JSON que alimentan historias destacadas, titulares de portada y diccionarios de textos.</p>
            </article>
            <article class="rounded-xl border border-white/10 bg-[#101012] p-4">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-[#c32720]">Pestaña 3</span>
                <h2 class="mt-1 font-display text-sm font-bold uppercase text-[#dcdcdc]">Comunicaciones, redes e IA</h2>
                <p class="mt-1 text-[11px] text-gray-400">Datos de contacto, redes sociales, correo SMTP/IMAP, automatización editorial por IA y Archive.org.</p>
            </article>
            <article class="rounded-xl border border-white/10 bg-[#101012] p-4">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-[#c32720]">Pestaña 4</span>
                <h2 class="mt-1 font-display text-sm font-bold uppercase text-[#dcdcdc]">Moderación global</h2>
                <p class="mt-1 text-[11px] text-gray-400">Interruptores de aprobación previa para contenidos enviados por bandas, agencias y comunidad.</p>
            </article>
        </div>

        <div class="manual-content grid gap-5 xl:grid-cols-[240px_minmax(0,1fr)] xl:items-start">
            <aside class="manual-sidebar rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3">
                <h2 class="font-display text-xs uppercase tracking-wider font-semibold text-gray-300">Índice de secciones</h2>
                <nav class="space-y-1 text-xs">
                    <a href="#tab1" class="block rounded-lg px-3 py-2 text-gray-300 hover:bg-white/5 hover:text-white">1. Apariencia y multimedia</a>
                    <a href="#tab2" class="block rounded-lg px-3 py-2 text-gray-300 hover:bg-white/5 hover:text-white">2. Contenido y textos (JSON)</a>
                    <a href="#tab3" class="block rounded-lg px-3 py-2 text-gray-300 hover:bg-white/5 hover:text-white">3. Comunicaciones, redes e IA</a>
                    <a href="#tab4" class="block rounded-lg px-3 py-2 text-gray-300 hover:bg-white/5 hover:text-white">4. Moderación global</a>
                </nav>
            </aside>

            <div class="space-y-5">
                <section id="tab1" class="manual-section rounded-xl border border-white/10 bg-[#101012] p-5 space-y-3">
                    <h2 class="font-display text-lg font-bold uppercase tracking-wide text-[#dcdcdc]">1. Apariencia y multimedia</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-white/10 text-[11px] uppercase text-gray-400">
                                    <th class="py-2 px-3">Campo</th>
                                    <th class="py-2 px-3">Función</th>
                                    <th class="py-2 px-3">Impacto</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-gray-300">
                                <tr><td class="py-2 px-3 font-mono text-white">site_name / brand_mark</td><td class="py-2 px-3">Nombre oficial y texto del logotipo en cabecera</td><td class="py-2 px-3 text-emerald-300">Bajo</td></tr>
                                <tr><td class="py-2 px-3 font-mono text-white">logo / background</td><td class="py-2 px-3">Imagen de marca y fondo general de la web</td><td class="py-2 px-3 text-amber-300">Medio</td></tr>
                                <tr><td class="py-2 px-3 font-mono text-white">hero_video_url / hero_video</td><td class="py-2 px-3">Vídeo principal de portada (externo o archivo local)</td><td class="py-2 px-3 text-amber-300">Medio</td></tr>
                                <tr><td class="py-2 px-3 font-mono text-white">accent_color / surface_color</td><td class="py-2 px-3">Colores del tema visual Lucille</td><td class="py-2 px-3 text-amber-300">Medio</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="tab2" class="manual-section rounded-xl border border-white/10 bg-[#101012] p-5 space-y-3">
                    <h2 class="font-display text-lg font-bold uppercase tracking-wide text-[#dcdcdc]">2. Contenido y textos (JSON)</h2>
                    <p class="text-xs text-gray-400">
                        Los bloques <code class="text-gray-200">featured_stories_json</code>, <code class="text-gray-200">latest_podcasts_json</code>, <code class="text-gray-200">home_headings_json</code>, <code class="text-gray-200">ui_texts_json</code> y <code class="text-gray-200">admin_texts_json</code> exigen sintaxis JSON válida. Utiliza el botón «Formatear JSON» incluido en cada editor antes de guardar.
                    </p>
                </section>

                <section id="tab3" class="manual-section rounded-xl border border-white/10 bg-[#101012] p-5 space-y-3">
                    <h2 class="font-display text-lg font-bold uppercase tracking-wide text-[#dcdcdc]">3. Comunicaciones, redes e IA</h2>
                    <p class="text-xs text-gray-400">
                        Controla los destinatarios de alertas, el transporte de correo (<code class="text-gray-200">notification_mailer</code>), la lectura automática IMAP y las claves de Gemini, OpenRouter y Archive.org. Las contraseñas y claves guardadas se mantienen intactas si dejas sus campos en blanco.
                    </p>
                </section>

                <section id="tab4" class="manual-section rounded-xl border border-white/10 bg-[#101012] p-5 space-y-3">
                    <h2 class="font-display text-lg font-bold uppercase tracking-wide text-[#dcdcdc]">4. Moderación global</h2>
                    <p class="text-xs text-gray-400">
                        Permite activar o desactivar la revisión previa de maquetas, bandas del Muro, álbumes, comentarios, eventos y contratos antes de su publicación.
                    </p>
                </section>
            </div>
        </div>
    </div>
</x-layouts.admin>
