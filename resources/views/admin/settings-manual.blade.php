<x-layouts.admin :title="'Manual de ajustes - '.$themeSettings->site_name">
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

    <section class="border border-[#2b2b2b] bg-[linear-gradient(180deg,rgba(16,16,18,.96),rgba(12,12,13,.92))] p-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-[10px] uppercase tracking-[.28em] text-[#8b8b8b]">Manual interno</p>
                <h1 class="mt-3 font-display text-4xl uppercase tracking-[.12em] text-[#f0f0f0]">Admin Settings Handbook</h1>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-[#b4b4b4]">
                    Esta pagina documenta <span class="text-[#f0f0f0]">/admin/settings</span> siguiendo las tres pestañas del formulario:
                    apariencia, contenido y comunicaciones. Cada bloque explica que hace el campo, donde se ve y que riesgo tiene.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.settings.edit') }}" class="lucille-button">Volver a ajustes</a>
                <a href="{{ route('admin.dashboard') }}" class="lucille-button-solid">{{ $admin['back_to_dashboard'] }}</a>
            </div>
        </div>

        <div class="mt-7 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4">
                <p class="text-[10px] uppercase tracking-[.24em] text-[#8b8b8b]">Pestaña 1</p>
                <p class="mt-2 text-sm leading-6 text-[#e4e4e4]">Apariencia, branding, fuentes, colores y media principal.</p>
            </article>
            <article class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4">
                <p class="text-[10px] uppercase tracking-[.24em] text-[#8b8b8b]">Pestaña 2</p>
                <p class="mt-2 text-sm leading-6 text-[#e4e4e4]">Bloques JSON que gobiernan la portada y los textos reutilizables.</p>
            </article>
            <article class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4">
                <p class="text-[10px] uppercase tracking-[.24em] text-[#8b8b8b]">Pestaña 3</p>
                <p class="mt-2 text-sm leading-6 text-[#e4e4e4]">Contacto, redes sociales y notificaciones activas.</p>
            </article>
            <article class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4">
                <p class="text-[10px] uppercase tracking-[.24em] text-[#8b8b8b]">Riesgo</p>
                <p class="mt-2 text-sm leading-6 text-[#e4e4e4]">Los JSON y el mailer son los puntos más sensibles.</p>
            </article>
        </div>
    </section>

    <div
        class="manual-content mt-8 grid gap-8 xl:grid-cols-[280px_minmax(0,1fr)] xl:items-start"
        x-data="{
            activeSection: 'tab1',
            observer: null,
            init() {
                const targets = ['tab1', 'tab2', 'tab3', 'capturas']
                    .map((id) => document.getElementById(id))
                    .filter(Boolean);

                if (! targets.length) {
                    return;
                }

                this.observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            this.activeSection = entry.target.id;
                        }
                    });
                }, {
                    root: null,
                    rootMargin: '-18% 0px -70% 0px',
                    threshold: 0.08,
                });

                targets.forEach((el) => this.observer.observe(el));
            },
        }"
        x-init="init()"
    >
        <aside class="manual-sidebar sticky top-6 space-y-6 self-start">
            <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
                <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Índice</h2>
                <p class="mt-2 text-sm leading-7 text-[#7b7b7b]">Navegación fija para saltar entre secciones del manual.</p>
                <nav class="mt-5 space-y-2 text-sm">
                    <a href="#tab1" class="block border px-4 py-3 transition" :class="activeSection === 'tab1' ? 'border-[var(--color-lucille-accent)] bg-[rgba(255,255,255,.06)] text-white' : 'border-[#2b2b2b] bg-[rgba(0,0,0,.18)] text-[#e4e4e4] hover:border-[#4a4a4a] hover:text-white'">Apariencia y Multimedia</a>
                    <a href="#tab2" class="block border px-4 py-3 transition" :class="activeSection === 'tab2' ? 'border-[var(--color-lucille-accent)] bg-[rgba(255,255,255,.06)] text-white' : 'border-[#2b2b2b] bg-[rgba(0,0,0,.18)] text-[#e4e4e4] hover:border-[#4a4a4a] hover:text-white'">Contenido y Textos</a>
                    <a href="#tab3" class="block border px-4 py-3 transition" :class="activeSection === 'tab3' ? 'border-[var(--color-lucille-accent)] bg-[rgba(255,255,255,.06)] text-white' : 'border-[#2b2b2b] bg-[rgba(0,0,0,.18)] text-[#e4e4e4] hover:border-[#4a4a4a] hover:text-white'">Comunicaciones y Redes</a>
                    <a href="#capturas" class="block border px-4 py-3 transition" :class="activeSection === 'capturas' ? 'border-[var(--color-lucille-accent)] bg-[rgba(255,255,255,.06)] text-white' : 'border-[#2b2b2b] bg-[rgba(0,0,0,.18)] text-[#e4e4e4] hover:border-[#4a4a4a] hover:text-white'">Capturas sugeridas</a>
                </nav>
            </section>

            <section class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-6">
                <h3 class="font-display text-lg uppercase tracking-[.12em] text-[#dcdcdc]">Acciones</h3>
                <div class="manual-actions mt-4 flex flex-col gap-3">
                    <a href="{{ route('admin.settings.manual.pdf') }}" target="_blank" class="lucille-button-solid">Exportar PDF real</a>
                    <button type="button" class="lucille-button" onclick="window.print()">Imprimir / Guardar PDF</button>
                    <a href="{{ route('admin.settings.edit') }}" class="lucille-button">Volver a ajustes</a>
                    <a href="{{ route('admin.dashboard') }}" class="lucille-button">{{ $admin['back_to_dashboard'] }}</a>
                </div>
                <p class="mt-4 text-xs leading-6 text-[#7b7b7b]">
                    Usa la impresión del navegador para generar un PDF desde esta misma vista.
                </p>
            </section>

            <section class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-6">
                <h3 class="font-display text-lg uppercase tracking-[.12em] text-[#dcdcdc]">Resumen</h3>
                <ul class="mt-4 space-y-2 text-sm leading-6 text-[#c7c7c7]">
                    <li>• Pestaña 1: apariencia.</li>
                    <li>• Pestaña 2: JSON editoriales.</li>
                    <li>• Pestaña 3: contacto y correo.</li>
                    <li>• Los JSON son lo más sensible.</li>
                </ul>
            </section>
        </aside>

        <div class="space-y-8">
    <section id="tab1" class="manual-section border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[10px] uppercase tracking-[.28em] text-[#8b8b8b]">Pestaña 1</p>
                <h2 class="mt-2 font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Apariencia y Multimedia</h2>
                <p class="mt-3 max-w-3xl text-sm leading-7 text-[#7b7b7b]">
                    Aquí se controla la identidad visual del sitio: nombre, marca, logo, fondos, hero, tipografía, colores y media principal.
                </p>
            </div>
            <a href="#capturas" class="text-xs uppercase tracking-[.18em] text-[#8b8b8b] transition hover:text-white">Ver capturas sugeridas</a>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,.7fr)]">
            <div class="space-y-6">
                <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
                    <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Branding</h3>
                    <div class="mt-4 overflow-hidden border border-[#2b2b2b]">
                        <table class="w-full border-collapse text-left text-sm">
                            <thead class="bg-[rgba(255,255,255,.03)] text-[10px] uppercase tracking-[.22em] text-[#8b8b8b]">
                                <tr>
                                    <th class="px-4 py-3">Campo</th>
                                    <th class="px-4 py-3">Qué hace</th>
                                    <th class="px-4 py-3">Dónde se ve</th>
                                    <th class="px-4 py-3">Riesgo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#2b2b2b] text-[#d8d8d8]">
                                <tr><td class="px-4 py-4 font-medium text-white">site_name</td><td class="px-4 py-4">Nombre global del sitio</td><td class="px-4 py-4">Títulos y branding</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                                <tr><td class="px-4 py-4 font-medium text-white">brand_mark</td><td class="px-4 py-4">Texto del wordmark</td><td class="px-4 py-4">Header público</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                                <tr><td class="px-4 py-4 font-medium text-white">brand_mark_font</td><td class="px-4 py-4">Fuente del wordmark</td><td class="px-4 py-4">Header público</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                                <tr><td class="px-4 py-4 font-medium text-white">brand_display_mode</td><td class="px-4 py-4">Texto o logo</td><td class="px-4 py-4">Header público</td><td class="px-4 py-4 text-[#f3c26b]">Medio</td></tr>
                                <tr><td class="px-4 py-4 font-medium text-white">logo</td><td class="px-4 py-4">Logo principal</td><td class="px-4 py-4">Header y redes</td><td class="px-4 py-4 text-[#f3c26b]">Medio</td></tr>
                                <tr><td class="px-4 py-4 font-medium text-white">background</td><td class="px-4 py-4">Fondo general</td><td class="px-4 py-4">Layouts</td><td class="px-4 py-4 text-[#f3c26b]">Medio</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
                    <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Hero, fuentes y colores</h3>
                    <div class="mt-4 overflow-hidden border border-[#2b2b2b]">
                        <table class="w-full border-collapse text-left text-sm">
                            <thead class="bg-[rgba(255,255,255,.03)] text-[10px] uppercase tracking-[.22em] text-[#8b8b8b]">
                                <tr>
                                    <th class="px-4 py-3">Campo</th>
                                    <th class="px-4 py-3">Qué hace</th>
                                    <th class="px-4 py-3">Dónde se ve</th>
                                    <th class="px-4 py-3">Riesgo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#2b2b2b] text-[#d8d8d8]">
                                <tr><td class="px-4 py-4 font-medium text-white">hero_video_file</td><td class="px-4 py-4">Video local del hero</td><td class="px-4 py-4">Home</td><td class="px-4 py-4 text-[#f3c26b]">Medio</td></tr>
                                <tr><td class="px-4 py-4 font-medium text-white">hero_video_url</td><td class="px-4 py-4">Video externo del hero</td><td class="px-4 py-4">Home</td><td class="px-4 py-4 text-[#ff9e9e]">Alto</td></tr>
                                <tr><td class="px-4 py-4 font-medium text-white">hero_video_disabled</td><td class="px-4 py-4">Apaga el video del hero</td><td class="px-4 py-4">Home</td><td class="px-4 py-4 text-[#f3c26b]">Medio</td></tr>
                                <tr><td class="px-4 py-4 font-medium text-white">body_font</td><td class="px-4 py-4">Fuente base</td><td class="px-4 py-4">Texto general</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                                <tr><td class="px-4 py-4 font-medium text-white">heading_font</td><td class="px-4 py-4">Fuente de títulos</td><td class="px-4 py-4">Encabezados</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                                <tr><td class="px-4 py-4 font-medium text-white">accent_color</td><td class="px-4 py-4">Color de acento</td><td class="px-4 py-4">Botones y highlights</td><td class="px-4 py-4 text-[#f3c26b]">Medio</td></tr>
                                <tr><td class="px-4 py-4 font-medium text-white">nav_color</td><td class="px-4 py-4">Color de navegación</td><td class="px-4 py-4">Cabeceras y menús</td><td class="px-4 py-4 text-[#f3c26b]">Medio</td></tr>
                                <tr><td class="px-4 py-4 font-medium text-white">surface_color</td><td class="px-4 py-4">Color de superficies</td><td class="px-4 py-4">Cards y paneles</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                                <tr><td class="px-4 py-4 font-medium text-white">body_color</td><td class="px-4 py-4">Color del texto base</td><td class="px-4 py-4">Texto general</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                                <tr><td class="px-4 py-4 font-medium text-white">heading_color</td><td class="px-4 py-4">Color de títulos</td><td class="px-4 py-4">Encabezados</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                                <tr><td class="px-4 py-4 font-medium text-white">line_color</td><td class="px-4 py-4">Color de bordes</td><td class="px-4 py-4">Separadores</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
                    <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Media principal</h3>
                    <div class="mt-4 overflow-hidden border border-[#2b2b2b]">
                        <table class="w-full border-collapse text-left text-sm">
                            <thead class="bg-[rgba(255,255,255,.03)] text-[10px] uppercase tracking-[.22em] text-[#8b8b8b]">
                                <tr>
                                    <th class="px-4 py-3">Campo</th>
                                    <th class="px-4 py-3">Qué hace</th>
                                    <th class="px-4 py-3">Dónde se ve</th>
                                    <th class="px-4 py-3">Riesgo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#2b2b2b] text-[#d8d8d8]">
                                <tr><td class="px-4 py-4 font-medium text-white">hero_slide_primary</td><td class="px-4 py-4">Primera imagen del hero</td><td class="px-4 py-4">Home principal</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                                <tr><td class="px-4 py-4 font-medium text-white">hero_slide_secondary</td><td class="px-4 py-4">Segunda imagen del hero</td><td class="px-4 py-4">Home principal</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                                <tr><td class="px-4 py-4 font-medium text-white">home_album_cover</td><td class="px-4 py-4">Portada destacada de álbum</td><td class="px-4 py-4">Home</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                                <tr><td class="px-4 py-4 font-medium text-white">home_video_image</td><td class="px-4 py-4">Imagen destacada del video</td><td class="px-4 py-4">Home</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <aside class="space-y-6">
                <section class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
                    <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Resumen operativo</h3>
                    <ul class="mt-4 space-y-2 text-sm leading-6 text-[#c7c7c7]">
                        <li>• Define primero nombre, marca y logo.</li>
                        <li>• Después ajusta colores y tipografías.</li>
                        <li>• Luego revisa el hero y las imágenes.</li>
                        <li>• Evita subir media pesada sin comprobar el servidor.</li>
                    </ul>
                </section>
                <section class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
                    <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Riesgo</h3>
                    <p class="mt-4 text-sm leading-6 text-[#c7c7c7]">
                        Los campos de esta pestaña son visuales, pero <span class="text-white">hero_video_url</span> puede dejar la portada vacía
                        si la URL no es válida o no responde correctamente.
                    </p>
                </section>
            </aside>
        </div>
    </section>

    <section id="tab2" class="manual-section border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[10px] uppercase tracking-[.28em] text-[#8b8b8b]">Pestaña 2</p>
                <h2 class="mt-2 font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Contenido y Textos</h2>
                <p class="mt-3 max-w-3xl text-sm leading-7 text-[#7b7b7b]">
                    Esta pestaña agrupa los bloques JSON. Si el formato falla, el guardado se bloquea y el sitio puede perder parte del contenido dinámico.
                </p>
            </div>
            <div class="border border-[#5a4a1e] bg-[rgba(255,193,7,.08)] px-4 py-3 text-sm leading-6 text-[#f0d48f]">
                Asegúrate de usar JSON válido. Si dudas, pulsa <span class="text-white">Formatear JSON</span> en cada editor.
            </div>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <section class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
                <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Home editorial</h3>
                <div class="mt-4 space-y-6">
                    <x-admin.json-editor
                        name="featured_stories_json"
                        :label="$admin['featured_stories_json_label']"
                        :value="$featuredStoriesJson ?? ''"
                        :rows="14"
                    />

                    <x-admin.json-editor
                        name="latest_podcasts_json"
                        :label="$admin['latest_podcasts_json_label']"
                        :value="$latestPodcastsJson ?? ''"
                        :rows="14"
                    />

                    <x-admin.json-editor
                        name="home_headings_json"
                        :label="$admin['home_headings_json_label']"
                        :value="$homeHeadingsJson ?? ''"
                        :rows="14"
                    />
                </div>
            </section>

            <section class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
                <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Textos reutilizables</h3>
                <div class="mt-4 space-y-6">
                    <x-admin.json-editor
                        name="ui_texts_json"
                        :label="$admin['ui_texts_json_label']"
                        :value="$uiTextsJson ?? ''"
                        :rows="18"
                    />

                    <x-admin.json-editor
                        name="admin_texts_json"
                        :label="$admin['admin_texts_json_label']"
                        :value="$adminTextsJson ?? ''"
                        :rows="18"
                    />
                </div>
            </section>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <section class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
                <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Qué hace cada JSON</h3>
                <ul class="mt-4 space-y-3 text-sm leading-6 text-[#c7c7c7]">
                    <li><span class="text-white">featured_stories_json</span>: controla el bloque de historias destacadas.</li>
                    <li><span class="text-white">latest_podcasts_json</span>: controla el bloque de podcasts recientes.</li>
                    <li><span class="text-white">home_headings_json</span>: define títulos y subtítulos de la home.</li>
                    <li><span class="text-white">ui_texts_json</span>: textos reutilizables de la interfaz pública.</li>
                    <li><span class="text-white">admin_texts_json</span>: textos reutilizables del panel admin.</li>
                </ul>
            </section>
            <section class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
                <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Riesgo real</h3>
                <p class="mt-4 text-sm leading-6 text-[#c7c7c7]">
                    Estos campos tienen el mayor impacto sobre la web. Un JSON mal cerrado, una coma sobrante o una clave mal escrita
                    puede provocar que el guardado falle o que una vista no encuentre el texto esperado.
                </p>
            </section>
        </div>
    </section>

    <section id="tab3" class="manual-section border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[10px] uppercase tracking-[.28em] text-[#8b8b8b]">Pestaña 3</p>
                <h2 class="mt-2 font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Comunicaciones y Redes</h2>
                <p class="mt-3 max-w-3xl text-sm leading-7 text-[#7b7b7b]">
                    Esta pestaña reúne textos de contacto, direcciones, teléfonos, correos del sistema, redes sociales y el estado real de notificaciones activas.
                </p>
            </div>
            <a href="#capturas" class="text-xs uppercase tracking-[.18em] text-[#8b8b8b] transition hover:text-white">Ver capturas sugeridas</a>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <section class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
                <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Textos y datos de contacto</h3>
                <div class="mt-4 overflow-hidden border border-[#2b2b2b]">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead class="bg-[rgba(255,255,255,.03)] text-[10px] uppercase tracking-[.22em] text-[#8b8b8b]">
                            <tr>
                                <th class="px-4 py-3">Campo</th>
                                <th class="px-4 py-3">Qué hace</th>
                                <th class="px-4 py-3">Dónde se ve</th>
                                <th class="px-4 py-3">Riesgo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#2b2b2b] text-[#d8d8d8]">
                            <tr><td class="px-4 py-4 font-medium text-white">contact_form_title</td><td class="px-4 py-4">Título del formulario</td><td class="px-4 py-4">Página de contacto</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">contact_info_title</td><td class="px-4 py-4">Título del bloque de info</td><td class="px-4 py-4">Página de contacto</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">contact_description</td><td class="px-4 py-4">Texto descriptivo</td><td class="px-4 py-4">Contacto</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">contact_address</td><td class="px-4 py-4">Dirección física o postal</td><td class="px-4 py-4">Contacto</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">contact_phone_primary</td><td class="px-4 py-4">Teléfono principal</td><td class="px-4 py-4">Contacto y pie</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">contact_phone_secondary</td><td class="px-4 py-4">Teléfono secundario</td><td class="px-4 py-4">Contacto y pie</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
                <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Redes y notificaciones</h3>
                <div class="mt-4 overflow-hidden border border-[#2b2b2b]">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead class="bg-[rgba(255,255,255,.03)] text-[10px] uppercase tracking-[.22em] text-[#8b8b8b]">
                            <tr>
                                <th class="px-4 py-3">Campo</th>
                                <th class="px-4 py-3">Qué hace</th>
                                <th class="px-4 py-3">Dónde se ve</th>
                                <th class="px-4 py-3">Riesgo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#2b2b2b] text-[#d8d8d8]">
                            <tr><td class="px-4 py-4 font-medium text-white">social_facebook</td><td class="px-4 py-4">Enlace de Facebook</td><td class="px-4 py-4">Footer</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">social_instagram</td><td class="px-4 py-4">Enlace de Instagram</td><td class="px-4 py-4">Footer</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">social_youtube</td><td class="px-4 py-4">Enlace de YouTube</td><td class="px-4 py-4">Footer</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">social_tiktok</td><td class="px-4 py-4">Enlace de TikTok</td><td class="px-4 py-4">Footer</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">social_x</td><td class="px-4 py-4">Enlace de X</td><td class="px-4 py-4">Footer</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">contact_email</td><td class="px-4 py-4">Correo público</td><td class="px-4 py-4">Contacto y pie</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">notification_email</td><td class="px-4 py-4">Correo principal de notificación</td><td class="px-4 py-4">Sistema de emails</td><td class="px-4 py-4 text-[#f3c26b]">Medio</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">notification_copy_email</td><td class="px-4 py-4">Correo en copia</td><td class="px-4 py-4">Sistema de emails</td><td class="px-4 py-4 text-[#f3c26b]">Medio</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">notification_from_email</td><td class="px-4 py-4">Remitente de correos</td><td class="px-4 py-4">Sistema de emails</td><td class="px-4 py-4 text-[#f3c26b]">Medio</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">notification_reply_to_email</td><td class="px-4 py-4">Reply-To de correos</td><td class="px-4 py-4">Sistema de emails</td><td class="px-4 py-4 text-[#f3c26b]">Medio</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">notification_mailer</td><td class="px-4 py-4">Mailer activo</td><td class="px-4 py-4">Envío de correos</td><td class="px-4 py-4 text-[#ff9e9e]">Alto</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
                    <h4 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">Estado activo de notificaciones</h4>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.7)] p-4">
                            <dt class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Correo principal activo</dt>
                            <dd class="mt-2 break-all text-sm text-[#e0e0e0]">{{ $activeNotificationState['primary'] ?? 'No definido' }}</dd>
                        </div>
                        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.7)] p-4">
                            <dt class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Correo copia activo</dt>
                            <dd class="mt-2 break-all text-sm text-[#e0e0e0]">{{ $activeNotificationState['copy'] ?? 'No definido' }}</dd>
                        </div>
                        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.7)] p-4">
                            <dt class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Remitente activo</dt>
                            <dd class="mt-2 break-all text-sm text-[#e0e0e0]">{{ $activeNotificationState['from'] ?? 'No definido' }}</dd>
                        </div>
                        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.7)] p-4">
                            <dt class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Reply-to activo</dt>
                            <dd class="mt-2 break-all text-sm text-[#e0e0e0]">{{ $activeNotificationState['reply_to'] ?? 'No definido' }}</dd>
                        </div>
                        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.7)] p-4 md:col-span-2">
                            <dt class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Mailer activo</dt>
                            <dd class="mt-2 break-all text-sm text-[#e0e0e0]">{{ $activeNotificationState['mailer'] ?? 'No definido' }}</dd>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </section>

    <section id="capturas" class="manual-section border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Capturas sugeridas</h2>
        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4 text-sm leading-6 text-[#c7c7c7]">1. Pantalla completa de ajustes con las 3 pestañas.</div>
            <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4 text-sm leading-6 text-[#c7c7c7]">2. Pestaña de apariencia abierta.</div>
            <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4 text-sm leading-6 text-[#c7c7c7]">3. Pestaña de contenido con editores JSON visibles.</div>
            <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4 text-sm leading-6 text-[#c7c7c7]">4. Pestaña de comunicaciones con el estado de notificaciones.</div>
            <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4 text-sm leading-6 text-[#c7c7c7]">5. Ejemplo del frontend antes y después de cambiar branding.</div>
            <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4 text-sm leading-6 text-[#c7c7c7]">6. Ejemplo del footer antes y después de cambiar redes o contacto.</div>
        </div>
    </section>

    <section class="manual-no-print mt-8 flex flex-wrap gap-3">
        <a href="{{ route('admin.settings.edit') }}" class="lucille-button-solid">Volver a ajustes</a>
        <a href="{{ route('admin.dashboard') }}" class="lucille-button">{{ $admin['back_to_dashboard'] }}</a>
    </section>
</div>
    </div>
</x-layouts.admin>
