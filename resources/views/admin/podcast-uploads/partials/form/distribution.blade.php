<div class="space-y-6">
    {{-- Encabezado de sección limpio --}}
    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between pb-3 border-b border-white/5">
        <div>
            <h2 class="font-display text-lg sm:text-xl font-bold uppercase tracking-wide text-white">
                Opciones de entrega y distribución
            </h2>
            <p class="text-xs text-gray-400 font-sans">
                Configuración para la preservación de archivos y el despacho a plataformas de emisión.
            </p>
        </div>
        <span class="text-[11px] font-mono text-gray-500 uppercase tracking-wider">
            Paso 3 de 3
        </span>
    </div>

    {{-- Opción de Copia Local --}}
    <div class="rounded-xl border border-white/5 bg-black/40 p-4 sm:p-5">
        <label class="flex items-start gap-3.5 cursor-pointer select-none">
            <input
                type="checkbox"
                name="download_processed_mp3"
                value="1"
                @checked(old('download_processed_mp3'))
                class="mt-1 h-4 w-4 rounded border-white/20 bg-black/50 text-[#c32720] focus:ring-0 focus:ring-offset-0 cursor-pointer"
            >
            <div>
                <span class="block text-xs sm:text-sm font-semibold text-white font-sans">
                    Conservar una copia local del MP3 procesado
                </span>
                <span class="mt-0.5 block text-xs text-gray-400 font-sans leading-relaxed">
                    Almacena el archivo en el almacenamiento del servidor para habilitar su descarga directa inmediata desde el panel tras finalizar el procesamiento.
                </span>
            </div>
        </label>
    </div>

    {{-- Resumen de las acciones de envío disponibles --}}
    <div>
        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 font-sans">
            Acciones disponibles en este paso
        </h3>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-white/5 bg-black/30 p-4">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-2 w-2 rounded-full bg-gray-400"></span>
                    <span class="text-xs font-semibold text-white font-sans">Crear episodio</span>
                </div>
                <p class="mt-1.5 text-xs text-gray-400 font-sans leading-relaxed">
                    Almacena la emisión y el audio cargado sin despachar tareas a RadioBOSS ni Archive.org. Ideal para guardar la ficha con antelación o revisión previa.
                </p>
            </div>

            <div class="rounded-xl border border-[#c32720]/20 bg-[#c32720]/5 p-4">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-2 w-2 rounded-full bg-[#c32720]"></span>
                    <span class="text-xs font-semibold text-[#c32720] font-sans">Crear y procesar</span>
                </div>
                <p class="mt-1.5 text-xs text-gray-300 font-sans leading-relaxed">
                    Registra la emisión y lanza de inmediato la cola automática: optimización R2, subida FTP a RadioBOSS, archivo en Archive.org y notificación por email.
                </p>
            </div>
        </div>
    </div>

    {{-- Notas técnicas --}}
    <div class="rounded-xl border border-white/5 bg-black/20 p-4 text-xs text-gray-400 font-sans flex items-start gap-2.5">
        <span class="text-base text-gray-400 shrink-0">ℹ️</span>
        <p class="leading-relaxed">
            Los estados del pipeline se actualizan automáticamente en tiempo real mediante workers en segundo plano. Puedes supervisar el avance de cada tarea en la sección de emisiones recientes.
        </p>
    </div>
</div>
