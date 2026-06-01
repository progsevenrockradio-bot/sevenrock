<x-layouts.admin :title="'Manual de podcast uploads - '.$themeSettings->site_name">
    @php
        $admin = $themeAppearance['admin_texts'] ?? [];
    @endphp

    <style>
        [x-cloak] { display: none !important; }

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
                <h1 class="mt-3 font-display text-4xl uppercase tracking-[.12em] text-[#f0f0f0]">Podcast uploads handbook</h1>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-[#b4b4b4]">
                    Esta pagina explica cada opcion visible en <span class="text-[#f0f0f0]">/admin/podcast-uploads</span>,
                    cómo se relaciona con el pipeline y qué campos son más sensibles.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.podcast-uploads.index') }}" class="lucille-button">Volver a uploads</a>
                <a href="{{ route('admin.dashboard') }}" class="lucille-button-solid">{{ $admin['back_to_dashboard'] ?? 'Volver al dashboard' }}</a>
            </div>
        </div>

        <div class="mt-7 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4">
                <p class="text-[10px] uppercase tracking-[.24em] text-[#8b8b8b]">Bloque 1</p>
                <p class="mt-2 text-xs uppercase tracking-[.18em] text-[#8b8b8b]">Programa, fecha y título</p>
                <p class="mt-2 text-sm leading-6 text-[#e4e4e4]">Datos editoriales: programa, fecha, número, invitado y resumen.</p>
            </article>
            <article class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4">
                <p class="text-[10px] uppercase tracking-[.24em] text-[#8b8b8b]">Bloque 2</p>
                <p class="mt-2 text-xs uppercase tracking-[.18em] text-[#8b8b8b]">MP3 y carátula</p>
                <p class="mt-2 text-sm leading-6 text-[#e4e4e4]">Multimedia pesada: MP3 e imagen del episodio.</p>
            </article>
            <article class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4">
                <p class="text-[10px] uppercase tracking-[.24em] text-[#8b8b8b]">Bloque 3</p>
                <p class="mt-2 text-xs uppercase tracking-[.18em] text-[#8b8b8b]">Copia y sincronización</p>
                <p class="mt-2 text-sm leading-6 text-[#e4e4e4]">Distribución técnica y estados del pipeline.</p>
            </article>
            <article class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4">
                <p class="text-[10px] uppercase tracking-[.24em] text-[#8b8b8b]">Riesgo</p>
                <p class="mt-2 text-sm leading-6 text-[#e4e4e4]">Los errores de archivo y validación afectan la subida.</p>
            </article>
        </div>
    </section>

    <div class="manual-content mt-8 grid gap-8 xl:grid-cols-[280px_minmax(0,1fr)] xl:items-start">
        <aside
            class="manual-sidebar sticky top-6 space-y-6 self-start"
            x-data="{
                activeSection: 'tab1',
                observer: null,
                init() {
                    const targets = ['tab1', 'tab2', 'tab3', 'recent', 'captures']
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
            <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
                <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Índice</h2>
                <nav class="mt-5 space-y-2 text-sm">
                    <a href="#tab1" class="block border px-4 py-3 transition" :class="activeSection === 'tab1' ? 'border-[var(--color-lucille-accent)] bg-[rgba(255,255,255,.06)] text-white' : 'border-[#2b2b2b] bg-[rgba(0,0,0,.18)] text-[#e4e4e4] hover:border-[#4a4a4a] hover:text-white'">Bloque 1: Datos editoriales</a>
                    <a href="#tab2" class="block border px-4 py-3 transition" :class="activeSection === 'tab2' ? 'border-[var(--color-lucille-accent)] bg-[rgba(255,255,255,.06)] text-white' : 'border-[#2b2b2b] bg-[rgba(0,0,0,.18)] text-[#e4e4e4] hover:border-[#4a4a4a] hover:text-white'">Bloque 2: Multimedia pesada</a>
                    <a href="#tab3" class="block border px-4 py-3 transition" :class="activeSection === 'tab3' ? 'border-[var(--color-lucille-accent)] bg-[rgba(255,255,255,.06)] text-white' : 'border-[#2b2b2b] bg-[rgba(0,0,0,.18)] text-[#e4e4e4] hover:border-[#4a4a4a] hover:text-white'">Bloque 3: Distribución técnica</a>
                    <a href="#recent" class="block border px-4 py-3 transition" :class="activeSection === 'recent' ? 'border-[var(--color-lucille-accent)] bg-[rgba(255,255,255,.06)] text-white' : 'border-[#2b2b2b] bg-[rgba(0,0,0,.18)] text-[#e4e4e4] hover:border-[#4a4a4a] hover:text-white'">Últimos episodios</a>
                    <a href="#captures" class="block border px-4 py-3 transition" :class="activeSection === 'captures' ? 'border-[var(--color-lucille-accent)] bg-[rgba(255,255,255,.06)] text-white' : 'border-[#2b2b2b] bg-[rgba(0,0,0,.18)] text-[#e4e4e4] hover:border-[#4a4a4a] hover:text-white'">Capturas sugeridas</a>
                </nav>
            </section>

            <section class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-6">
                <h3 class="font-display text-lg uppercase tracking-[.12em] text-[#dcdcdc]">Acciones</h3>
                <div class="manual-actions mt-4 flex flex-col gap-3">
                    <a href="{{ route('admin.podcast-uploads.manual.pdf') }}" target="_blank" class="lucille-button-solid">Exportar PDF real</a>
                    <button type="button" class="lucille-button-solid" onclick="window.print()">Imprimir / Guardar PDF</button>
                    <a href="{{ route('admin.podcast-uploads.index') }}" class="lucille-button">Volver a uploads</a>
                    <a href="{{ route('admin.dashboard') }}" class="lucille-button">{{ $admin['back_to_dashboard'] ?? 'Volver al dashboard' }}</a>
                </div>
            </section>
        </aside>

        <div class="space-y-8">
            <section id="tab1" class="manual-section border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] uppercase tracking-[.28em] text-[#8b8b8b]">Bloque 1</p>
                        <h2 class="mt-2 font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">1. Datos editoriales</h2>
                        <p class="mt-2 text-xs uppercase tracking-[.18em] text-[#8b8b8b]">Programa, fecha y título</p>
                        <p class="mt-3 max-w-3xl text-sm leading-7 text-[#7b7b7b]">
                            Define el contenido visible del episodio antes de procesarlo.
                        </p>
                    </div>
                </div>

                <div class="mt-6 overflow-hidden border border-[#2b2b2b]">
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
                            <tr><td class="px-4 py-4 font-medium text-white">master_program_id</td><td class="px-4 py-4">Selecciona el programa maestro</td><td class="px-4 py-4">Base del episodio y pipeline</td><td class="px-4 py-4 text-[#f3c26b]">Medio</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">numero_episodio</td><td class="px-4 py-4">Número manual opcional</td><td class="px-4 py-4">Ficha interna</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">live_title</td><td class="px-4 py-4">Título visible del episodio</td><td class="px-4 py-4">Ficha, archivo y notificación</td><td class="px-4 py-4 text-[#f3c26b]">Medio</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">fecha_emision</td><td class="px-4 py-4">Fecha del episodio</td><td class="px-4 py-4">Ficha y listados</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">biografia_invitado</td><td class="px-4 py-4">Nombre o nota del invitado</td><td class="px-4 py-4">Ficha interna</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">resena</td><td class="px-4 py-4">Resumen o descripción amplia</td><td class="px-4 py-4">Ficha y notificación</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="tab2" class="manual-section border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] uppercase tracking-[.28em] text-[#8b8b8b]">Bloque 2</p>
                        <h2 class="mt-2 font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">2. Multimedia pesada</h2>
                        <p class="mt-2 text-xs uppercase tracking-[.18em] text-[#8b8b8b]">MP3 y carátula</p>
                        <p class="mt-3 max-w-3xl text-sm leading-7 text-[#7b7b7b]">
                            Estos campos son la entrada principal de la pantalla y los más pesados de procesar.
                        </p>
                    </div>
                </div>

                <div class="mt-6 overflow-hidden border border-[#2b2b2b]">
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
                            <tr><td class="px-4 py-4 font-medium text-white">archivo_mp3</td><td class="px-4 py-4">Subida del audio principal</td><td class="px-4 py-4">Pipeline y descarga posterior</td><td class="px-4 py-4 text-[#ff9e9e]">Alto</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">imagen_episodio_url</td><td class="px-4 py-4">URL de la carátula</td><td class="px-4 py-4">Ficha del episodio y Archive.org</td><td class="px-4 py-4 text-[#f3c26b]">Medio</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">imagen_episodio_file</td><td class="px-4 py-4">Archivo local de la carátula</td><td class="px-4 py-4">Ficha del episodio y Archive.org</td><td class="px-4 py-4 text-[#f3c26b]">Medio</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="tab3" class="manual-section border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] uppercase tracking-[.28em] text-[#8b8b8b]">Bloque 3</p>
                        <h2 class="mt-2 font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">3. Distribución técnica</h2>
                        <p class="mt-2 text-xs uppercase tracking-[.18em] text-[#8b8b8b]">Copia y sincronización</p>
                        <p class="mt-3 max-w-3xl text-sm leading-7 text-[#7b7b7b]">
                            Opciones de entrega y conservación del archivo.
                        </p>
                    </div>
                </div>

                <div class="mt-6 overflow-hidden border border-[#2b2b2b]">
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
                            <tr><td class="px-4 py-4 font-medium text-white">download_processed_mp3</td><td class="px-4 py-4">Conserva copia local del MP3 procesado</td><td class="px-4 py-4">Descarga posterior</td><td class="px-4 py-4 text-[#b8e6c3]">Bajo</td></tr>
                            <tr><td class="px-4 py-4 font-medium text-white">sync_archive_org</td><td class="px-4 py-4">Activa la sincronización con Archive.org</td><td class="px-4 py-4">Pipeline de distribución</td><td class="px-4 py-4 text-[#f3c26b]">Medio</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-3">
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4">
                        <div class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Borrador</div>
                        <div class="mt-2 text-sm text-[#e0e0e0]">Estado implícito antes de completar la subida.</div>
                    </div>
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4">
                        <div class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Procesando</div>
                        <div class="mt-2 text-sm text-[#e0e0e0]">MP3, RadioBOSS o Archive.org en ejecución.</div>
                    </div>
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4">
                        <div class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Publicado</div>
                        <div class="mt-2 text-sm text-[#e0e0e0]">Todo el pipeline terminó correctamente.</div>
                    </div>
                </div>
            </section>

            <section id="recent" class="manual-section border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
                <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">4. Últimos episodios</h2>
                <p class="mt-3 text-sm leading-7 text-[#7b7b7b]">
                    Esta zona no guarda datos nuevos. Solo lista los últimos episodios procesados y permite reintentar, descargar o eliminar.
                </p>
                <ul class="mt-4 space-y-2 text-sm leading-6 text-[#c7c7c7]">
                    <li>• Reprocesar: vuelve a disparar RadioBOSS, Archive.org y notificación según el estado actual.</li>
                    <li>• Descargar MP3: baja la copia local si existe.</li>
                    <li>• Eliminar: borra la entrada y sus archivos asociados.</li>
                </ul>
            </section>

            <section id="captures" class="manual-section border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
                <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">5. Capturas sugeridas</h2>
                <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4 text-sm leading-6 text-[#c7c7c7]">1. Pantalla completa de podcast uploads.</div>
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4 text-sm leading-6 text-[#c7c7c7]">2. Bloque Editorial con programa maestro y descripción.</div>
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4 text-sm leading-6 text-[#c7c7c7]">3. Bloque Multimedia con carga activa.</div>
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4 text-sm leading-6 text-[#c7c7c7]">4. Bloque Distribución con opciones de pipeline.</div>
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4 text-sm leading-6 text-[#c7c7c7]">5. Lista de últimos episodios.</div>
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4 text-sm leading-6 text-[#c7c7c7]">6. Estado final tras completar una subida.</div>
                </div>
            </section>
        </div>
    </div>

    <section class="manual-no-print mt-8 flex flex-wrap gap-3">
        <a href="{{ route('admin.podcast-uploads.index') }}" class="lucille-button-solid">Volver a uploads</a>
        <a href="{{ route('admin.dashboard') }}" class="lucille-button">{{ $admin['back_to_dashboard'] ?? 'Volver al dashboard' }}</a>
    </section>
</x-layouts.admin>
