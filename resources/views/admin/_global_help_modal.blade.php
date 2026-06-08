@php
    $route = request()->route() ? request()->route()->getName() : 'admin.dashboard';
    $currentSection = 'dashboard';
    if (str_starts_with($route, 'admin.posts')) { $currentSection = 'posts'; }
    elseif (str_starts_with($route, 'admin.comments')) { $currentSection = 'comments'; }
    elseif (str_starts_with($route, 'admin.events')) { $currentSection = 'events'; }
    elseif (str_starts_with($route, 'admin.master-programs')) { $currentSection = 'master-programs'; }
    elseif (str_starts_with($route, 'admin.podcast-uploads')) { $currentSection = 'podcast-uploads'; }
    elseif (str_starts_with($route, 'admin.songs')) { $currentSection = 'songs'; }
    elseif (str_starts_with($route, 'admin.albums')) { $currentSection = 'albums'; }
    elseif (str_starts_with($route, 'admin.videos')) { $currentSection = 'videos'; }
    elseif (str_starts_with($route, 'admin.gallery')) { $currentSection = 'gallery'; }
    elseif (str_starts_with($route, 'admin.radio-artists')) { $currentSection = 'radio-artists'; }
    elseif (str_starts_with($route, 'admin.talents')) { $currentSection = 'talents'; }
    elseif (str_starts_with($route, 'admin.programs')) { $currentSection = 'programs'; }
    elseif (str_starts_with($route, 'admin.outreach')) { $currentSection = 'outreach'; }
    elseif (str_starts_with($route, 'admin.settings')) { $currentSection = 'settings'; }
    elseif (str_starts_with($route, 'admin.audit-logs')) { $currentSection = 'settings'; }
@endphp

<!-- Global Help Slide-over Modal -->
<div
    x-show="showHelp"
    class="fixed inset-0 z-[150] flex justify-end"
    x-data="{
        search: '',
        activeManual: '{{ $currentSection }}',
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
        class="relative w-screen max-w-5xl h-full bg-[#0c0c0d] border-l border-[#2b2b2b] shadow-2xl flex z-[160]"
    >
        <!-- Inner Sidebar Navigation (Desktop) -->
        <aside class="hidden md:flex flex-col w-64 border-r border-[#2b2b2b] bg-[rgba(0,0,0,0.22)] shrink-0">
            <div class="p-6 border-b border-[#2b2b2b] bg-[rgba(16,16,18,.98)]">
                <p class="text-[9px] uppercase tracking-[.25em] text-[#8b8b8b] font-semibold">Índice del manual</p>
                <h3 class="mt-1 font-display text-sm uppercase tracking-[.1em] text-white">Herramientas</h3>
            </div>
            <nav class="flex-1 overflow-y-auto p-4 space-y-1 text-xs">
                @foreach([
                    'dashboard' => ['label' => 'Dashboard principal', 'icon' => '📊'],
                    'posts' => ['label' => 'Posts / Artículos', 'icon' => '📝'],
                    'comments' => ['label' => 'Comentarios', 'icon' => '💬'],
                    'events' => ['label' => 'Eventos / Conciertos', 'icon' => '📅'],
                    'master-programs' => ['label' => 'Programas Master', 'icon' => '🎙️'],
                    'podcast-uploads' => ['label' => 'Subir Podcasts', 'icon' => '📤'],
                    'songs' => ['label' => 'Canciones / Temas', 'icon' => '🎵'],
                    'albums' => ['label' => 'Álbumes / Discos', 'icon' => '💿'],
                    'videos' => ['label' => 'Videos', 'icon' => '🎥'],
                    'gallery' => ['label' => 'Galerías de Fotos', 'icon' => '📷'],
                    'radio-artists' => ['label' => 'Band profiles', 'icon' => '🎸'],
                    'talents' => ['label' => 'Staff / Talentos', 'icon' => '👥'],
                    'programs' => ['label' => 'Programas Conv.', 'icon' => '🔑'],
                    'outreach' => ['label' => 'Outreach / Campañas', 'icon' => '✉️'],
                    'settings' => ['label' => 'Configuración', 'icon' => '⚙️']
                ] as $key => $data)
                    <button
                        type="button"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded text-left transition-colors border"
                        :class="activeManual === '{{ $key }}' 
                            ? 'border-[var(--color-lucille-accent)] bg-[rgba(255,255,255,0.05)] text-white font-medium' 
                            : 'border-transparent text-gray-400 hover:bg-[rgba(255,255,255,0.02)] hover:text-white'"
                        @click="activeManual = '{{ $key }}'"
                    >
                        <span>{{ $data['icon'] }}</span>
                        <span>{{ $data['label'] }}</span>
                    </button>
                @endforeach
            </nav>
        </aside>

        <!-- Right Content Area -->
        <div class="flex-1 flex flex-col h-full bg-[#0c0c0d]">
            <!-- Header bar inside panel -->
            <div class="px-6 py-5 border-b border-[#2b2b2b] bg-[rgba(16,16,18,.98)] flex items-center justify-between shrink-0">
                <div>
                    <p class="text-[9px] uppercase tracking-[.25em] text-[var(--lucille-accent)] font-semibold">Ayuda y Documentación</p>
                    <h2 class="mt-1 font-display text-lg uppercase tracking-[.1em] text-[#f0f0f0]">
                        Guía de Uso del Panel
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

            <!-- Mobile Dropdown Selector & Search -->
            <div class="px-6 py-4 border-b border-[#2b2b2b] bg-[rgba(0,0,0,0.22)] shrink-0 flex flex-col gap-3">
                <!-- Select for Mobile -->
                <div class="block md:hidden">
                    <label class="block text-[10px] uppercase text-[#7b7b7b] mb-1">Sección:</label>
                    <select
                        x-model="activeManual"
                        class="w-full bg-[#121214] border border-[#2b2b2b] text-xs text-white rounded p-2 focus:outline-none focus:border-[var(--lucille-accent)]"
                    >
                        <option value="dashboard">📊 Dashboard principal</option>
                        <option value="posts">📝 Posts / Artículos</option>
                        <option value="comments">💬 Comentarios</option>
                        <option value="events">📅 Eventos / Conciertos</option>
                        <option value="master-programs">🎙️ Programas Master</option>
                        <option value="podcast-uploads">📤 Subir Podcasts</option>
                        <option value="songs">🎵 Canciones / Temas</option>
                        <option value="albums">💿 Álbumes / Discos</option>
                        <option value="videos">🎥 Videos</option>
                        <option value="gallery">📷 Galerías de Fotos</option>
                        <option value="radio-artists">🎸 Band profiles</option>
                        <option value="talents">👥 Staff / Talentos</option>
                        <option value="programs">🔑 Programas Conv.</option>
                        <option value="outreach">✉️ Outreach / Campañas</option>
                        <option value="settings">⚙️ Configuración</option>
                    </select>
                </div>

                <!-- Search Input -->
                <div class="relative">
                    <input
                        type="text"
                        x-model="search"
                        placeholder="Buscar en el manual (ej. slug, metadatos, pipeline, seeder, seo...)"
                        class="w-full bg-[#121214] border border-[#2b2b2b] rounded-md px-3 py-2 pl-8 text-xs text-white placeholder-gray-500 focus:outline-none focus:border-[var(--lucille-accent)] transition-colors"
                    >
                    <div class="absolute left-2.5 top-2.5 text-gray-500">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Scrollable Content -->
            <div class="flex-1 overflow-y-auto p-6 md:p-8 space-y-8 scroll-smooth">

                <!-- 1. DASHBOARD MANUAL -->
                <div x-show="activeManual === 'dashboard'" class="space-y-6">
                    <div class="flex items-center gap-3 border-b border-[#2b2b2b] pb-2">
                        <span class="text-xl">📊</span>
                        <h3 class="font-display text-base uppercase tracking-wider text-white">Manual del Dashboard Principal</h3>
                    </div>
                    
                    <div class="text-xs text-[#8b8b8b] leading-relaxed space-y-4">
                        <p>El dashboard principal es el resumen general operativo de la radio. Está diseñado en cuatro grandes secciones para facilitar el monitoreo.</p>
                        
                        <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,0.15)] p-4 rounded">
                            <strong class="text-white block mb-1">Métricas Rápidas:</strong>
                            <p class="mb-2">Indican el estado numérico del catálogo musical, base de usuarios y convocatorias. Cada tarjeta incluye un enlace rápido o atajo para redirigirte a su gestión.</p>
                        </div>

                        <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,0.15)] p-4 rounded">
                            <strong class="text-white block mb-1">Podcast Pipeline:</strong>
                            <p class="mb-2">Muestra la salud del sistema automatizado de publicación de episodios de podcast. Los audios atraviesan por los siguientes estados:</p>
                            <ul class="list-disc pl-5 space-y-1.5 mt-2">
                                <li><strong class="text-white">Procesando:</strong> El audio MP3 se está subiendo o convirtiendo localmente.</li>
                                <li><strong class="text-white">RB Pendiente:</strong> Esperando sincronización de comandos para que la pista sea inyectada en el software automatizador RadioBOSS.</li>
                                <li><strong class="text-white">Archive Pendiente:</strong> Esperando que se complete la subida automatizada y copia de seguridad en el portal Archive.org.</li>
                                <li><strong class="text-white">Entrega Verificada:</strong> El pipeline terminó exitosamente y el podcast está 100% disponible.</li>
                            </ul>
                        </div>

                        <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,0.15)] p-4 rounded">
                            <strong class="text-white block mb-1">Taxonomías Rápidas:</strong>
                            <p>Permite añadir categorías o etiquetas generales para las noticias del blog directamente desde la cabecera, sin necesidad de entrar a la sección de edición del artículo.</p>
                        </div>
                    </div>
                </div>

                <!-- 2. POSTS MANUAL -->
                <div x-show="activeManual === 'posts'" class="space-y-6">
                    <div class="flex items-center gap-3 border-b border-[#2b2b2b] pb-2">
                        <span class="text-xl">📝</span>
                        <h3 class="font-display text-base uppercase tracking-wider text-white">Manual de Artículos (Posts)</h3>
                    </div>
                    <div class="text-xs text-[#8b8b8b] leading-relaxed space-y-4">
                        <p>Sección para redactar, editar y organizar notas de blog y noticias.</p>
                        <div class="bg-[rgba(255,255,255,0.02)] border-l-2 border-[var(--lucille-accent)] p-3">
                            <strong class="text-white">Editor por bloques interactivo:</strong> Permite insertar Párrafos con enlaces inline rápidos ( Inline Links ), Encabezados, Citas (Quotes), imágenes, galerías y código HTML puro (para reproductores embebidos de Spotify o Bandcamp).
                        </div>
                        <ul class="list-disc pl-5 space-y-2">
                            <li><strong class="text-white">Fecha de Publicación:</strong> Modifica la fecha a una hora futura para programar la publicación del post automáticamente.</li>
                            <li><strong class="text-white">Portada (Featured Image):</strong> Subida local directa o copiado de URLs remotas obligatoria.</li>
                            <li><strong class="text-white">SEO:</strong> Introduce Meta Title y Meta Description recomendados de no más de 120 caracteres para mejorar tu posición en Google.</li>
                        </ul>
                    </div>
                </div>

                <!-- 3. COMMENTS MANUAL -->
                <div x-show="activeManual === 'comments'" class="space-y-6">
                    <div class="flex items-center gap-3 border-b border-[#2b2b2b] pb-2">
                        <span class="text-xl">💬</span>
                        <h3 class="font-display text-base uppercase tracking-wider text-white">Manual de Comentarios</h3>
                    </div>
                    <div class="text-xs text-[#8b8b8b] leading-relaxed space-y-3">
                        <p>Control de la comunidad y retroalimentación de los artículos de la radio.</p>
                        <p class="border border-[#2b2b2b] p-3 bg-[rgba(0,0,0,0.12)]">
                            <strong class="text-white">Moderación:</strong> Por seguridad de spam, los comentarios nuevos pueden guardarse desaprobados. Debes presionar "Aprobar" para que se visualicen públicamente debajo de la nota. Permite también la edición y el borrado permanente.
                        </p>
                    </div>
                </div>

                <!-- 4. EVENTS MANUAL -->
                <div x-show="activeManual === 'events'" class="space-y-6">
                    <div class="flex items-center gap-3 border-b border-[#2b2b2b] pb-2">
                        <span class="text-xl">📅</span>
                        <h3 class="font-display text-base uppercase tracking-wider text-white">Manual de Eventos y Conciertos</h3>
                    </div>
                    <div class="text-xs text-[#8b8b8b] leading-relaxed space-y-4">
                        <p>Gestión de la cartelera y coberturas especiales de la emisora.</p>
                        <ul class="list-disc pl-5 space-y-2">
                            <li><strong class="text-white">Eventos Próximos (Upcoming):</strong> Conciertos programados en cartelera. Requiere fecha, flyer de imagen y descripción de compra.</li>
                            <li><strong class="text-white">Eventos Pasados (Past):</strong> Coberturas de prensa y galerías de conciertos ya realizados por el staff.</li>
                            <li><strong class="text-white">Evento Único (Single Event):</strong> Pantalla dedicada a configurar y destacar el concierto promocional del mes.</li>
                        </ul>
                    </div>
                </div>

                <!-- 5. MASTER PROGRAMS MANUAL -->
                <div x-show="activeManual === 'master-programs'" class="space-y-6">
                    <div class="flex items-center gap-3 border-b border-[#2b2b2b] pb-2">
                        <span class="text-xl">🎙️</span>
                        <h3 class="font-display text-base uppercase tracking-wider text-white">Manual de Programas Master</h3>
                    </div>
                    <div class="text-xs text-[#8b8b8b] leading-relaxed space-y-3">
                        <p>Definición de los shows radiales oficiales.</p>
                        <p class="border border-[#2b2b2b] p-3 bg-[rgba(0,0,0,0.12)]">
                            <strong class="text-white">Estructura Base:</strong> Configura el nombre del show, descripción general y el locutor principal. Funciona como la plantilla a la cual se asocian todos los episodios semanales que se suben en "Podcast Uploads".
                        </p>
                    </div>
                </div>

                <!-- 6. PODCAST UPLOADS MANUAL -->
                <div x-show="activeManual === 'podcast-uploads'" class="space-y-6">
                    <div class="flex items-center gap-3 border-b border-[#2b2b2b] pb-2">
                        <span class="text-xl">📤</span>
                        <h3 class="font-display text-base uppercase tracking-wider text-white">Manual de Subida de Podcasts</h3>
                    </div>
                    <div class="text-xs text-[#8b8b8b] leading-relaxed space-y-4">
                        <p>Subida y distribución de episodios de audio.</p>
                        <ol class="list-decimal pl-5 space-y-2">
                            <li><strong class="text-white">Paso 1:</strong> Selecciona el Programa Master al que corresponde el episodio.</li>
                            <li><strong class="text-white">Paso 2:</strong> Introduce el título y número del episodio.</li>
                            <li><strong class="text-white">Paso 3:</strong> Sube el archivo de audio MP3 (máx. 150MB) y la imagen de carátula.</li>
                            <li><strong class="text-white">Paso 4:</strong> Marca si deseas sincronizarlo automáticamente en Archive.org para copia de seguridad ilimitada y en RadioBOSS.</li>
                        </ol>
                    </div>
                </div>

                <!-- 7. SONGS MANUAL -->
                <div x-show="activeManual === 'songs'" class="space-y-6">
                    <div class="flex items-center gap-3 border-b border-[#2b2b2b] pb-2">
                        <span class="text-xl">🎵</span>
                        <h3 class="font-display text-base uppercase tracking-wider text-white">Manual de Canciones</h3>
                    </div>
                    <div class="text-xs text-[#8b8b8b] leading-relaxed space-y-3">
                        <p>Control del catálogo musical reproducido.</p>
                        <p>Permite subir archivos de canciones individuales, asociarlos a sus bandas correspondientes y definir el orden dentro del catálogo general o listado del reproductor en línea.</p>
                    </div>
                </div>

                <!-- 8. ALBUMS MANUAL -->
                <div x-show="activeManual === 'albums'" class="space-y-6">
                    <div class="flex items-center gap-3 border-b border-[#2b2b2b] pb-2">
                        <span class="text-xl">💿</span>
                        <h3 class="font-display text-base uppercase tracking-wider text-white">Manual de Álbumes y Discografías</h3>
                    </div>
                    <div class="text-xs text-[#8b8b8b] leading-relaxed space-y-3">
                        <p>Gestión de lanzamientos discográficos.</p>
                        <p>Crea fichas de álbumes detallando el título, la banda, el año de salida y la imagen de portada. Puedes enlazar múltiples canciones cargadas para consolidar la discografía completa de un artista.</p>
                    </div>
                </div>

                <!-- 9. VIDEOS MANUAL -->
                <div x-show="activeManual === 'videos'" class="space-y-6">
                    <div class="flex items-center gap-3 border-b border-[#2b2b2b] pb-2">
                        <span class="text-xl">🎥</span>
                        <h3 class="font-display text-base uppercase tracking-wider text-white">Manual de Videos</h3>
                    </div>
                    <div class="text-xs text-[#8b8b8b] leading-relaxed space-y-3">
                        <p>Incrustación de videos musicales oficiales o entrevistas.</p>
                        <p>Para añadir un video, basta con ingresar el nombre del tema, la banda y la URL de YouTube o Vimeo. La plataforma se encargará de extraer el reproductor embebido automáticamente.</p>
                    </div>
                </div>

                <!-- 10. GALLERY MANUAL -->
                <div x-show="activeManual === 'gallery'" class="space-y-6">
                    <div class="flex items-center gap-3 border-b border-[#2b2b2b] pb-2">
                        <span class="text-xl">📷</span>
                        <h3 class="font-display text-base uppercase tracking-wider text-white">Manual de Galerías de Fotos</h3>
                    </div>
                    <div class="text-xs text-[#8b8b8b] leading-relaxed space-y-3">
                        <p>Organización de imágenes de coberturas y sesiones.</p>
                        <p>Permite subir múltiples fotos a la vez y asignarlas a carpetas temáticas o coberturas específicas para mantener ordenados los archivos del servidor.</p>
                    </div>
                </div>

                <!-- 11. RADIO ARTISTS MANUAL -->
                <div x-show="activeManual === 'radio-artists'" class="space-y-6">
                    <div class="flex items-center gap-3 border-b border-[#2b2b2b] pb-2">
                        <span class="text-xl">🎸</span>
                        <h3 class="font-display text-base uppercase tracking-wider text-white">Manual de Band Profiles (Artistas)</h3>
                    </div>
                    <div class="text-xs text-[#8b8b8b] leading-relaxed space-y-4">
                        <p>Gestión de las fichas de perfiles de agrupaciones musicales.</p>
                        <ul class="list-disc pl-5 space-y-2">
                            <li><strong class="text-white">Biografía Automática:</strong> Usa el botón "Autogenerar" para escribir descripciones biográficas coherentes asistidas por plantilla.</li>
                            <li><strong class="text-white">Redes del Artista:</strong> Vincula los perfiles de redes de la banda para mostrarlos en su ficha.</li>
                        </ul>
                    </div>
                </div>

                <!-- 12. TALENTS MANUAL -->
                <div x-show="activeManual === 'talents'" class="space-y-6">
                    <div class="flex items-center gap-3 border-b border-[#2b2b2b] pb-2">
                        <span class="text-xl">👥</span>
                        <h3 class="font-display text-base uppercase tracking-wider text-white">Manual de Staff y Talentos</h3>
                    </div>
                    <div class="text-xs text-[#8b8b8b] leading-relaxed space-y-4">
                        <p>Administración del staff de locución e invitados.</p>
                        <ul class="list-disc pl-5 space-y-2">
                            <li><strong class="text-white">Activar/Suspender:</strong> Permite deshabilitar temporalmente a un locutor de la vista pública de la radio.</li>
                            <li><strong class="text-white">Demos y Portafolios:</strong> Sube y organiza demos de audio del staff para consulta externa o comercial.</li>
                        </ul>
                    </div>
                </div>

                <!-- 13. PROGRAMS MANUAL -->
                <div x-show="activeManual === 'programs'" class="space-y-6">
                    <div class="flex items-center gap-3 border-b border-[#2b2b2b] pb-2">
                        <span class="text-xl">🔑</span>
                        <h3 class="font-display text-base uppercase tracking-wider text-white">Manual de Convocatorias y Códigos</h3>
                    </div>
                    <div class="text-xs text-[#8b8b8b] leading-relaxed space-y-3">
                        <p>Administración de registros externos para locutores colaboradores.</p>
                        <p>Permite generar códigos de invitación válidos por un único uso. El locutor nuevo ingresará este código en su registro para vincularse de forma segura al panel del programa.</p>
                    </div>
                </div>

                <!-- 14. OUTREACH MANUAL -->
                <div x-show="activeManual === 'outreach'" class="space-y-6">
                    <div class="flex items-center gap-3 border-b border-[#2b2b2b] pb-2">
                        <span class="text-xl">✉️</span>
                        <h3 class="font-display text-base uppercase tracking-wider text-white">Manual de Outreach y Campañas</h3>
                    </div>
                    <div class="text-xs text-[#8b8b8b] leading-relaxed space-y-4">
                        <p>Gestión de campañas de captación de bandas por e-mail.</p>
                        <ul class="list-disc pl-5 space-y-2">
                            <li><strong class="text-white">Plantillas:</strong> Redacta correos base pre-diseñados.</li>
                            <li><strong class="text-white">Contactos:</strong> Agrupa agendas de correos de managers e integrantes del medio para envíos programados.</li>
                        </ul>
                    </div>
                </div>

                <!-- 15. SETTINGS MANUAL -->
                <div x-show="activeManual === 'settings'" class="space-y-6">
                    <div class="flex items-center gap-3 border-b border-[#2b2b2b] pb-2">
                        <span class="text-xl">⚙️</span>
                        <h3 class="font-display text-base uppercase tracking-wider text-white">Manual de Configuración y Seguridad</h3>
                    </div>
                    <div class="text-xs text-[#8b8b8b] leading-relaxed space-y-4">
                        <p>Configuración global de la emisora (Restringido a Super Admin).</p>
                        <ul class="list-disc pl-5 space-y-2">
                            <li><strong class="text-white">Tema y Apariencia:</strong> Configura el logotipo, imagen de fondo general, fuentes tipográficas y colores del reproductor.</li>
                            <li><strong class="text-white">Confirmación de Contraseña:</strong> Por seguridad de accesos indeseados, las secciones críticas requerirán reintroducir tu contraseña de administrador para realizar modificaciones.</li>
                            <li><strong class="text-white">Bitácora de Auditoría:</strong> Registro inmutable que almacena la fecha, la IP y la acción exacta tomada por cualquier miembro del staff de administración.</li>
                        </ul>
                    </div>
                </div>

                <!-- Safe Footer / Empty State -->
                <div x-show="search && !matches('metadatos titulo slug autor fecha publicacion programar bloques editor interactivo parrafo heading quote cita image gallery raw html inline links enlaces taxonomias categorias etiquetas tags portada destacada featured image imagen archivo subida redes facebook instagram twitter youtube fuente credito enlace externo seo optimizacion meta title description motores busqueda google guardar publicar borrador programar fecha futuro comentarios moderar aprobar desaprobar eventos proximos cartelera master programs parrilla podcast uploads mp3 archive.org radioboss pipeline canciones catalogo discos discografia videos youtube gallery talento staff convocatoria codices invitaciones outreach email campañas config log de auditoria')" class="text-center py-8" style="display: none;">
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
                    Entendido, Volver
                </button>
            </div>
        </div>
    </div>
</div>
