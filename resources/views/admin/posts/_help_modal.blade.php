<!-- Help Slide-over Modal -->
<div
    x-show="showHelp"
    class="fixed inset-0 z-[150] flex justify-end"
    x-data="{
        search: '',
        activeSection: 'help-meta',
        matches(keywords) {
            if (!this.search) return true;
            const term = this.search.toLowerCase().trim();
            return keywords.toLowerCase().includes(term);
        }
    }"
    @keydown.window.escape="showHelp = false"
    style="display: none;"
    x-cloak
>
    <!-- Backdrop overlay -->
    <div
        x-show="showHelp"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity"
        @click="showHelp = false"
    ></div>

    <!-- Slide-over panel -->
    <div
        x-show="showHelp"
        x-transition:enter="transform transition ease-in-out duration-500"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition ease-in-out duration-500"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="relative w-screen max-w-2xl h-full bg-[#0c0c0d] border-l border-[#2b2b2b] shadow-2xl flex flex-col z-[160]"
    >
        <!-- Header -->
        <div class="px-6 py-5 border-b border-[#2b2b2b] bg-[rgba(16,16,18,.98)] flex items-center justify-between shrink-0">
            <div>
                <p class="text-[10px] uppercase tracking-[.28em] text-[var(--lucille-accent)] font-semibold">Ayuda en línea</p>
                <h2 class="mt-1 font-display text-xl uppercase tracking-[.12em] text-[#f0f0f0]" id="slide-over-title">
                    Manual de Creación de Posts
                </h2>
            </div>
            <button
                type="button"
                class="p-2 text-[#7b7b7b] hover:text-white rounded hover:bg-[rgba(255,255,255,0.05)] transition-colors focus:outline-none"
                @click="showHelp = false"
            >
                <span class="sr-only">Cerrar manual</span>
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Search Box -->
        <div class="px-6 py-3 border-b border-[#2b2b2b] bg-[rgba(0,0,0,0.22)] shrink-0">
            <div class="relative">
                <input
                    type="text"
                    x-model="search"
                    placeholder="Escribe para buscar (ej. slug, enlaces, html, seo...)"
                    class="w-full bg-[#121214] border border-[#2b2b2b] rounded-md px-3 py-2 pl-8 text-xs text-white placeholder-gray-500 focus:outline-none focus:border-[var(--lucille-accent)] transition-colors"
                >
                <div class="absolute left-2.5 top-2.5 text-gray-500">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <button
                    x-show="search"
                    @click="search = ''"
                    class="absolute right-2.5 top-2.5 text-gray-500 hover:text-white text-xs"
                    style="display: none;"
                >
                    Clear
                </button>
            </div>
        </div>

        <!-- Scrollable Manual Content -->
        <div class="flex-1 overflow-y-auto p-6 md:p-8 space-y-8 scroll-smooth">
            
            <!-- Quick Alert / Tip -->
            <div class="border border-[rgba(255,255,255,0.04)] bg-[rgba(255,255,255,0.01)] p-4 rounded-md text-xs text-[#9c9c9c] leading-relaxed">
                <strong class="text-white">💡 Consejo de Productividad:</strong> 
                Puedes mantener este manual abierto en el lateral derecho de tu pantalla mientras rellenas el formulario de creación o edición de artículos a la izquierda.
            </div>

            <!-- 1. Metadata -->
            <section
                id="help-meta"
                x-show="matches('metadatos titulo slug autor fecha publicacion programar')"
                class="space-y-4"
            >
                <div class="flex items-center gap-2 border-b border-[#2b2b2b] pb-2">
                    <span class="text-md">📋</span>
                    <h3 class="font-display text-sm uppercase tracking-[.15em] text-white">1. Metadatos Generales</h3>
                </div>
                <div class="overflow-hidden border border-[#2b2b2b] rounded bg-[rgba(0,0,0,0.12)]">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead class="bg-[rgba(255,255,255,0.02)] text-[#8b8b8b] uppercase tracking-wider border-b border-[#2b2b2b]">
                            <tr>
                                <th class="px-4 py-2">Campo</th>
                                <th class="px-4 py-2">Función / Requisito</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#2b2b2b] text-[#c7c7c7]">
                            <tr>
                                <td class="px-4 py-3 font-semibold text-white">Título</td>
                                <td class="px-4 py-3">Nombre principal del artículo. Obligatorio.</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-semibold text-white">Slug</td>
                                <td class="px-4 py-3">Ruta legible (URL). Si se deja vacío, se genera automáticamente al guardar.</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-semibold text-white">Autor</td>
                                <td class="px-4 py-3">Nombre de firma. Por defecto toma el rol del administrador actual.</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-semibold text-white">Fecha de Publicación</td>
                                <td class="px-4 py-3">Control de fecha/hora. Permite fechas futuras para la programación automática.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- 2. Interactive Blocks -->
            <section
                id="help-blocks"
                x-show="matches('bloques editor interactivo parrafo heading quote cita image gallery raw html inline links enlaces')"
                class="space-y-4"
            >
                <div class="flex items-center gap-2 border-b border-[#2b2b2b] pb-2">
                    <span class="text-md">🧱</span>
                    <h3 class="font-display text-sm uppercase tracking-[.15em] text-white">2. Editor Interactivo por Bloques</h3>
                </div>
                <p class="text-xs text-[#8b8b8b] leading-relaxed">
                    Usa la barra de herramientas para añadir diferentes elementos estructurados al cuerpo del post. Puedes arrastrarlos o subirlos de posición usando las flechas de ordenamiento (<span class="text-white">↑↓</span>).
                </p>
                <div class="space-y-3">
                    <!-- Paragraph -->
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,0.18)] p-4 rounded">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs font-bold text-white">📝 Paragraph (Párrafo de Texto)</span>
                            <span class="text-[9px] uppercase tracking-wider bg-[rgba(0,128,0,0.15)] text-green-400 px-2 py-0.5 rounded">Básico</span>
                        </div>
                        <p class="text-xs text-[#8b8b8b] leading-relaxed mb-3">Redacta el contenido principal. Permite definir enlaces rápidos vinculados a palabras específicas:</p>
                        <div class="bg-[rgba(255,255,255,0.02)] border-l-2 border-[var(--lucille-accent)] p-2.5 text-[11px] text-gray-300">
                            <strong>Enlaces en línea (Inline Links):</strong> Escribe la palabra exacta y la URL de destino. El sistema convertirá automáticamente la primera coincidencia del texto en un enlace activo en la web.
                        </div>
                    </div>
                    <!-- Heading -->
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,0.18)] p-4 rounded">
                        <span class="text-xs font-bold text-white block mb-2">🔤 Heading (Encabezado)</span>
                        <p class="text-xs text-[#8b8b8b] leading-relaxed">Introduce subtítulos internos para estructurar y jerarquizar la lectura de artículos largos.</p>
                    </div>
                    <!-- Quote -->
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,0.18)] p-4 rounded">
                        <span class="text-xs font-bold text-white block mb-2">💬 Quote (Cita Destacada)</span>
                        <p class="text-xs text-[#8b8b8b] leading-relaxed">Para destacar opiniones o frases importantes. Incluye un espacio para la cita y otro para el autor/crédito.</p>
                    </div>
                    <!-- Image -->
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,0.18)] p-4 rounded">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs font-bold text-white">🖼️ Image (Imagen de Bloque)</span>
                            <span class="text-[9px] uppercase tracking-wider bg-[rgba(195,39,32,0.12)] text-[#ffd0d0] px-2 py-0.5 rounded border border-[#5c2a2a]">Media</span>
                        </div>
                        <p class="text-xs text-[#8b8b8b] leading-relaxed">Puedes ingresar una URL de imagen externa o hacer clic en <strong class="text-white">"Upload image"</strong> para subirla directamente desde tu dispositivo en segundo plano.</p>
                    </div>
                    <!-- Gallery -->
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,0.18)] p-4 rounded">
                        <span class="text-xs font-bold text-white block mb-2">🎞️ Gallery (Galería Fotográfica)</span>
                        <p class="text-xs text-[#8b8b8b] leading-relaxed">Permite adjuntar colecciones de fotos en un solo bloque. Soporta la subida múltiple simultánea para agilizar la carga.</p>
                    </div>
                    <!-- Raw HTML -->
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,0.18)] p-4 rounded">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs font-bold text-white">💻 Raw HTML (Código Embebido)</span>
                            <span class="text-[9px] uppercase tracking-wider bg-[rgba(243,194,107,0.12)] text-[#f3c26b] px-2 py-0.5 rounded border border-[#5c4a2a]">Avanzado</span>
                        </div>
                        <p class="text-xs text-[#8b8b8b] leading-relaxed">Para incrustar widgets externos. Copia y pega códigos iframe de reproductores musicales o videos (ej. Spotify, Bandcamp, Soundcloud o YouTube).</p>
                    </div>
                </div>
            </section>

            <!-- 3. Taxonomies -->
            <section
                id="help-tax"
                x-show="matches('taxonomias categorias etiquetas tags')"
                class="space-y-4"
            >
                <div class="flex items-center gap-2 border-b border-[#2b2b2b] pb-2">
                    <span class="text-md">🏷️</span>
                    <h3 class="font-display text-sm uppercase tracking-[.15em] text-white">3. Taxonomías</h3>
                </div>
                <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,0.15)] p-4 rounded text-xs space-y-3 leading-relaxed">
                    <p>
                        <strong class="text-white">Categorías y Etiquetas:</strong> Escríbelas separadas por comas (ej. <code class="bg-[#1a1a1c] border border-[#2b2b2b] px-1 rounded font-mono text-[10px] text-white">Metal, Lanzamientos, Green Day</code>).
                    </p>
                    <p class="text-[#8b8b8b]">
                        El sistema dispone de un diccionario de sugerencias. Cuando comiences a redactar, se sugerirán las taxonomías existentes en la base de datos para mantener la consistencia del catálogo del sitio web.
                    </p>
                </div>
            </section>

            <!-- 4. Featured Image -->
            <section
                id="help-featured"
                x-show="matches('portada destacada featured image imagen archivo subida')"
                class="space-y-4"
            >
                <div class="flex items-center gap-2 border-b border-[#2b2b2b] pb-2">
                    <span class="text-md">🖼️</span>
                    <h3 class="font-display text-sm uppercase tracking-[.15em] text-white">4. Imagen Destacada (Portada)</h3>
                </div>
                <p class="text-xs text-[#8b8b8b] leading-relaxed">
                    Es la foto de cabecera en el blog y portada en el listado web. Se requiere rellenar al menos una de las siguientes opciones:
                </p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,0.18)] p-3 text-xs">
                        <strong class="text-white block mb-1">🔗 Opción 1: URL Externa</strong>
                        <p class="text-[#8b8b8b]">Pega una ruta de imagen completa en el campo de texto.</p>
                    </div>
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,0.18)] p-3 text-xs">
                        <strong class="text-white block mb-1">📤 Opción 2: Subir Archivo</strong>
                        <p class="text-[#8b8b8b]">Adjunta un archivo local. El servidor lo optimizará en la carpeta local de posts.</p>
                    </div>
                </div>
            </section>

            <!-- 5. Social & Credits -->
            <section
                id="help-extra"
                x-show="matches('redes facebook instagram twitter youtube fuente credito enlace externo')"
                class="space-y-4"
            >
                <div class="flex items-center gap-2 border-b border-[#2b2b2b] pb-2">
                    <span class="text-md">🌐</span>
                    <h3 class="font-display text-sm uppercase tracking-[.15em] text-white">5. Redes de Artistas y Enlaces</h3>
                </div>
                <p class="text-xs text-[#8b8b8b] leading-relaxed">
                    Campos adicionales e informativos para robustecer el contenido del artículo:
                </p>
                <ul class="space-y-2 text-xs text-[#c7c7c7] list-disc pl-5">
                    <li>
                        <strong class="text-white">Redes del Artista:</strong> Enlaces directos a las cuentas oficiales de la banda (FB, IG, TW, YT) para enlazarlas al post.
                    </li>
                    <li>
                        <strong class="text-white">Créditos de Fuente:</strong> Nombre y URL del portal donde se originó la noticia.
                    </li>
                    <li>
                        <strong class="text-white">Enlace Externo Destacado:</strong> Permite añadir un botón de compra o reproductor principal (ej. "Comprar Tickets" con enlace a Ticketmaster).
                    </li>
                </ul>
            </section>

            <!-- 6. SEO -->
            <section
                id="help-seo"
                x-show="matches('seo optimizacion meta title description motores busqueda google')"
                class="space-y-4"
            >
                <div class="flex items-center gap-2 border-b border-[#2b2b2b] pb-2">
                    <span class="text-md">🔍</span>
                    <h3 class="font-display text-sm uppercase tracking-[.15em] text-white">6. Optimización SEO</h3>
                </div>
                <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,0.15)] p-4 rounded text-xs space-y-3 leading-relaxed">
                    <p>
                        <strong class="text-white">Meta Title:</strong> Título adaptado para buscadores (Google, Bing). Límite recomendado: 120 caracteres.
                    </p>
                    <p>
                        <strong class="text-white">Meta Description:</strong> Extracto promocional de dos o tres líneas que aparecerá debajo del título en Google. Estimula el CTR del post.
                    </p>
                </div>
            </section>

            <!-- 7. Save Actions -->
            <section
                id="help-save"
                x-show="matches('guardar publicar borrador programar fecha futuro')"
                class="space-y-4"
            >
                <div class="flex items-center gap-2 border-b border-[#2b2b2b] pb-2">
                    <span class="text-md">💾</span>
                    <h3 class="font-display text-sm uppercase tracking-[.15em] text-white">7. Acciones de Envío</h3>
                </div>
                <div class="grid gap-3 text-xs">
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,0.18)] p-3 flex gap-3">
                        <span class="text-base shrink-0">📢</span>
                        <div>
                            <strong class="text-green-400 block mb-1">Publicar (Publish)</strong>
                            <p class="text-[#8b8b8b]">Guarda el post en estado activo para que sea visible al público inmediatamente.</p>
                        </div>
                    </div>
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,0.18)] p-3 flex gap-3">
                        <span class="text-base shrink-0">📝</span>
                        <div>
                            <strong class="text-yellow-400 block mb-1">Guardar Borrador (Save Draft)</strong>
                            <p class="text-[#8b8b8b]">Guarda la información de forma privada. Ideal para notas en desarrollo o revisión.</p>
                        </div>
                    </div>
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,0.18)] p-3 flex gap-3">
                        <span class="text-base shrink-0">⏳</span>
                        <div>
                            <strong class="text-white block mb-1">Programar (Schedule)</strong>
                            <p class="text-[#8b8b8b]">Solo se habilita si la "Fecha de Publicación" es en el futuro. Publicará el post de forma automatizada al llegar ese momento.</p>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Safe Footer / Empty State -->
            <div x-show="search && !matches('metadatos titulo slug autor fecha publicacion programar bloques editor interactivo parrafo heading quote cita image gallery raw html inline links enlaces taxonomias categorias etiquetas tags portada destacada featured image imagen archivo subida redes facebook instagram twitter youtube fuente credito enlace externo seo optimizacion meta title description motores busqueda google guardar publicar borrador programar fecha futuro')" class="text-center py-8" style="display: none;">
                <p class="text-xs text-[#7b7b7b]">No se encontraron secciones para tu búsqueda.</p>
                <button type="button" @click="search = ''" class="mt-2 text-xs text-[var(--lucille-accent)] hover:underline">Mostrar todo</button>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-[#2b2b2b] bg-[rgba(16,16,18,.98)] flex justify-end shrink-0">
            <button
                type="button"
                class="lucille-button-solid text-xs py-2 px-4"
                @click="showHelp = false"
            >
                Entendido, Volver al Post
            </button>
        </div>
    </div>
</div>
