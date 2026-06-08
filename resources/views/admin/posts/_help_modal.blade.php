<!-- Help Slide-over Modal -->
<div
    x-show="showHelp"
    class="fixed inset-0 z-[150] overflow-hidden"
    aria-labelledby="slide-over-title"
    role="dialog"
    aria-modal="true"
    style="display: none;"
    x-cloak
>
    <div class="absolute inset-0 overflow-hidden">
        <!-- Backdrop overlay -->
        <div
            x-show="showHelp"
            x-transition:enter="ease-in-out duration-500"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in-out duration-500"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 bg-black/80 backdrop-blur-sm transition-opacity"
            @click="showHelp = false"
            aria-hidden="true"
        ></div>

        <!-- Slide-over panel -->
        <div class="fixed inset-y-0 right-0 flex max-w-full pl-10 pointer-events-none">
            <div
                x-show="showHelp"
                x-transition:enter="transform transition ease-in-out duration-500 sm:duration-700"
                x-transition:enter-start="translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transform transition ease-in-out duration-500 sm:duration-700"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full"
                class="w-screen max-w-2xl pointer-events-auto"
                @keydown.window.escape="showHelp = false"
            >
                <div class="flex flex-col h-full bg-[#0c0c0d] border-l border-[#2b2b2b] shadow-2xl">
                    <!-- Header -->
                    <div class="px-6 py-6 border-b border-[#2b2b2b] bg-[rgba(16,16,18,.98)] flex items-center justify-between">
                        <div>
                            <p class="text-[10px] uppercase tracking-[.28em] text-[var(--lucille-accent)]">Ayuda en línea</p>
                            <h2 class="mt-1 font-display text-2xl uppercase tracking-[.12em] text-[#f0f0f0]" id="slide-over-title">
                                Guía de Edición de Posts
                            </h2>
                        </div>
                        <button
                            type="button"
                            class="p-2 text-[#7b7b7b] hover:text-white rounded hover:bg-[rgba(255,255,255,.05)] transition-colors focus:outline-none"
                            @click="showHelp = false"
                        >
                            <span class="sr-only">Cerrar manual</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Jump-to Navigation -->
                    <div class="px-6 py-3 border-b border-[#2b2b2b] bg-[rgba(0,0,0,.18)]">
                        <span class="text-[9px] uppercase tracking-wider text-[#7b7b7b] mr-2 block sm:inline mb-1 sm:mb-0">Ir a sección:</span>
                        <div class="inline-flex flex-wrap gap-2 text-xs">
                            <a href="#help-meta" class="text-gray-400 hover:text-[var(--lucille-accent)] hover:underline">1. Metadatos</a>
                            <span class="text-gray-600">|</span>
                            <a href="#help-blocks" class="text-gray-400 hover:text-[var(--lucille-accent)] hover:underline">2. Bloques</a>
                            <span class="text-gray-600">|</span>
                            <a href="#help-tax" class="text-gray-400 hover:text-[var(--lucille-accent)] hover:underline">3. Categorías</a>
                            <span class="text-gray-600">|</span>
                            <a href="#help-featured" class="text-gray-400 hover:text-[var(--lucille-accent)] hover:underline">4. Portada</a>
                            <span class="text-gray-600">|</span>
                            <a href="#help-extra" class="text-gray-400 hover:text-[var(--lucille-accent)] hover:underline">5. Redes/Créditos</a>
                            <span class="text-gray-600">|</span>
                            <a href="#help-seo" class="text-gray-400 hover:text-[var(--lucille-accent)] hover:underline">6. SEO</a>
                        </div>
                    </div>

                    <!-- Scrollable Manual Content -->
                    <div class="flex-1 overflow-y-auto p-6 md:p-8 space-y-10 scroll-smooth">
                        
                        <!-- Introduction -->
                        <div class="prose prose-invert max-w-none text-sm text-[#b4b4b4] leading-relaxed">
                            <p>
                                Este manual interactivo detalla el funcionamiento del editor y los campos disponibles para la creación y edición de artículos en <strong>Seven Rock Radio</strong>.
                            </p>
                        </div>

                        <!-- 1. Metadata -->
                        <section id="help-meta" class="border border-[#2b2b2b] bg-[rgba(0,0,0,.15)] p-5">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-lg">📋</span>
                                <h3 class="font-display text-lg uppercase tracking-[.12em] text-[#dcdcdc]">1. Metadatos Generales</h3>
                            </div>
                            <ul class="space-y-3 text-xs leading-relaxed text-[#b4b4b4]">
                                <li>
                                    <strong class="text-white">Título:</strong> Nombre principal del post. Campo obligatorio.
                                </li>
                                <li>
                                    <strong class="text-white">Slug:</strong> URL amigable del artículo. Si se deja en blanco, el sistema la generará de forma automática al guardar basado en el título.
                                </li>
                                <li>
                                    <strong class="text-white">Autor:</strong> Nombre del escritor. Por defecto se asigna el valor del administrador.
                                </li>
                                <li>
                                    <strong class="text-white">Fecha de Publicación:</strong> Define cuándo se publica el post. Permite horas futuras para programar publicaciones en diferido.
                                </li>
                            </ul>
                        </section>

                        <!-- 2. Interactive Blocks -->
                        <section id="help-blocks" class="space-y-4">
                            <div class="flex items-center gap-2 border-b border-[#2b2b2b] pb-2">
                                <span class="text-lg">🧱</span>
                                <h3 class="font-display text-lg uppercase tracking-[.12em] text-[#dcdcdc]">2. Editor Interactivo por Bloques</h3>
                            </div>
                            <p class="text-xs text-[#b4b4b4] leading-relaxed">
                                El área de contenido principal se edita mediante bloques dinámicos que puedes agregar, reordenar y eliminar en tiempo real.
                            </p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.22)] p-4 text-xs">
                                    <h4 class="font-bold text-white mb-2">📝 Paragraph (Párrafo)</h4>
                                    <p class="text-[#8b8b8b] mb-2">Para el texto principal. Soporta enlaces en línea de forma inteligente.</p>
                                    <span class="inline-block bg-[rgba(255,255,255,.05)] px-2 py-1 rounded text-[10px] text-[#b8e6c3] border border-[#2b2b2b]">
                                        Tip: El primer término coincidente será enlazado.
                                    </span>
                                </div>
                                <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.22)] p-4 text-xs">
                                    <h4 class="font-bold text-white mb-2">🔤 Heading (Encabezado)</h4>
                                    <p class="text-[#8b8b8b]">Secciones internas del artículo para estructurar la lectura (Subtítulos).</p>
                                </div>
                                <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.22)] p-4 text-xs">
                                    <h4 class="font-bold text-white mb-2">💬 Quote (Cita)</h4>
                                    <p class="text-[#8b8b8b]">Resalta frases importantes. Incluye campos para el texto de la cita y el autor original.</p>
                                </div>
                                <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.22)] p-4 text-xs">
                                    <h4 class="font-bold text-white mb-2">🖼️ Image (Imagen)</h4>
                                    <p class="text-[#8b8b8b]">Ingresa una URL externa o sube una imagen directamente para que se almacene y autocomplete la ruta.</p>
                                </div>
                                <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.22)] p-4 text-xs sm:col-span-2">
                                    <h4 class="font-bold text-white mb-2">🎞️ Gallery (Galería de Imágenes)</h4>
                                    <p class="text-[#8b8b8b] mb-2">Permite subir o adjuntar múltiples imágenes. El sistema de subida múltiple procesará y enlazará las fotos en lotes.</p>
                                </div>
                                <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.22)] p-4 text-xs sm:col-span-2">
                                    <h4 class="font-bold text-white mb-2">💻 Raw HTML (HTML Puro)</h4>
                                    <p class="text-[#8b8b8b]">Usa este bloque para insertar reproductores multimedia embebidos (Spotify, Soundcloud, YouTube, Bandcamp) mediante código iframe.</p>
                                </div>
                            </div>
                            <div class="border-l-2 border-[var(--lucille-accent)] bg-[rgba(255,255,255,.02)] p-4 text-xs">
                                <strong class="text-white block mb-1">↕️ Reordenar Bloques:</strong>
                                Utiliza las flechas <span class="text-white">↑</span> y <span class="text-white">↓</span> localizadas en la barra superior de cada bloque para cambiar su posición. La previsualización de la columna derecha se actualiza al instante.
                            </div>
                        </section>

                        <!-- 3. Taxonomies -->
                        <section id="help-tax" class="border border-[#2b2b2b] bg-[rgba(0,0,0,.15)] p-5">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-lg">🏷️</span>
                                <h3 class="font-display text-lg uppercase tracking-[.12em] text-[#dcdcdc]">3. Taxonomías</h3>
                            </div>
                            <div class="space-y-3 text-xs leading-relaxed text-[#b4b4b4]">
                                <p>
                                    <strong class="text-white">Categorías y Etiquetas (Tags):</strong> Campos separados por comas (ej. <code class="text-white">Noticias, Bandas, Rock</code>).
                                </p>
                                <p class="text-[#8b8b8b]">
                                    El sistema cuenta con un autocompletado predictivo. A medida que escribas, aparecerán sugerencias basadas en las taxonomías ya creadas para mantener la coherencia.
                                </p>
                            </div>
                        </section>

                        <!-- 4. Featured Image -->
                        <section id="help-featured" class="space-y-3">
                            <div class="flex items-center gap-2 border-b border-[#2b2b2b] pb-2">
                                <span class="text-lg">🖼️</span>
                                <h3 class="font-display text-lg uppercase tracking-[.12em] text-[#dcdcdc]">4. Imagen Destacada (Portada)</h3>
                            </div>
                            <p class="text-xs text-[#b4b4b4] leading-relaxed">
                                Corresponde al banner del artículo y la miniatura para la sección de Blog. Es obligatorio tener al menos uno de los dos campos llenos:
                            </p>
                            <ol class="list-decimal pl-5 text-xs space-y-2 text-[#b4b4b4]">
                                <li>
                                    <strong class="text-white">URL de la Imagen:</strong> Una ruta absoluta a una imagen remota o del servidor.
                                </li>
                                <li>
                                    <strong class="text-white">Subida de archivo:</strong> Sube la foto directamente. Se optimizará y guardará en <code class="text-white">catalog/posts</code> de forma transparente.
                                </li>
                            </ol>
                        </section>

                        <!-- 5. Social & Credits -->
                        <section id="help-extra" class="border border-[#2b2b2b] bg-[rgba(0,0,0,.15)] p-5">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-lg">🌐</span>
                                <h3 class="font-display text-lg uppercase tracking-[.12em] text-[#dcdcdc]">5. Redes del Artista y Enlaces</h3>
                            </div>
                            <p class="text-xs text-[#b4b4b4] leading-relaxed mb-3">
                                Campos opcionales para complementar el post con contenido interactivo y de fuentes externas:
                            </p>
                            <ul class="space-y-2 text-xs text-[#b4b4b4]">
                                <li>
                                    <strong class="text-white">Redes del Artista:</strong> Enlaces a Facebook, Instagram, Twitter y YouTube oficiales de la banda.
                                </li>
                                <li>
                                    <strong class="text-white">Enlace Externo y Créditos:</strong> Permite ingresar una URL de compra, fuente de la noticia original y el nombre del medio proveedor para asignar créditos de autoría.
                                </li>
                            </ul>
                        </section>

                        <!-- 6. SEO -->
                        <section id="help-seo" class="space-y-3">
                            <div class="flex items-center gap-2 border-b border-[#2b2b2b] pb-2">
                                <span class="text-lg">🔍</span>
                                <h3 class="font-display text-lg uppercase tracking-[.12em] text-[#dcdcdc]">6. Optimización SEO</h3>
                            </div>
                            <p class="text-xs text-[#b4b4b4] leading-relaxed">
                                Optimiza la forma en que los motores de búsqueda (Google, Bing) visualizan tu artículo:
                            </p>
                            <ul class="list-disc pl-5 text-xs space-y-2 text-[#b4b4b4]">
                                <li>
                                    <strong class="text-white">Meta Title:</strong> Título adaptado para Google. Mantener bajo los 120 caracteres para evitar recortes.
                                </li>
                                <li>
                                    <strong class="text-white">Meta Description:</strong> Extracto descriptivo y llamativo que estimule el clic en resultados de búsqueda.
                                </li>
                            </ul>
                        </section>

                        <!-- 7. Save Actions -->
                        <section class="border-t border-[#2b2b2b] pt-6">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-lg">💾</span>
                                <h3 class="font-display text-lg uppercase tracking-[.12em] text-[#dcdcdc]">7. Acciones de Envío</h3>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-3 text-xs">
                                <div class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-3">
                                    <strong class="text-[#b8e6c3] block mb-1">📢 Publicar</strong>
                                    <p class="text-[#8b8b8b]">Guarda el post en estado activo para que sea visible al público de inmediato.</p>
                                </div>
                                <div class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-3">
                                    <strong class="text-[#f3c26b] block mb-1">📝 Guardar Borrador</strong>
                                    <p class="text-[#8b8b8b]">Guarda la información de forma privada. Ideal para artículos en desarrollo.</p>
                                </div>
                                <div class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-3">
                                    <strong class="text-white block mb-1">⏳ Programar</strong>
                                    <p class="text-[#8b8b8b]">Se activa si la Fecha de Publicación es en el futuro. Publicará el post de forma automática.</p>
                                </div>
                            </div>
                        </section>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-5 border-t border-[#2b2b2b] bg-[rgba(16,16,18,.98)] flex justify-end">
                        <button
                            type="button"
                            class="lucille-button-solid text-xs"
                            @click="showHelp = false"
                        >
                            Entendido, Volver al Post
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
