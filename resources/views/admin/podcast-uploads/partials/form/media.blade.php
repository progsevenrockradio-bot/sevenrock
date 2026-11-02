<div class="space-y-6">
    {{-- Encabezado de sección limpio --}}
    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between pb-3 border-b border-white/5">
        <div>
            <h2 class="font-display text-lg sm:text-xl font-bold uppercase tracking-wide text-white">
                Audio principal y carátula
            </h2>
            <p class="text-xs text-gray-400 font-sans">
                Adjunta el archivo de audio MP3 de la emisión y la imagen promocional o carátula.
            </p>
        </div>
        <span class="text-[11px] font-mono text-gray-500 uppercase tracking-wider">
            Paso 2 de 3
        </span>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Columna 1: Archivo MP3 y Monitor de Carga --}}
        <div class="space-y-4 flex flex-col justify-between">
            <div class="space-y-3">
                <label class="block text-xs font-medium text-gray-300 font-sans">
                    Archivo de audio (MP3) <span class="text-[#c32720]">*</span>
                </label>
                <input
                    type="file"
                    name="archivo_mp3"
                    accept="audio/mpeg,audio/mp3"
                    class="block w-full text-xs text-gray-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-white/10 file:text-white hover:file:bg-white/15 file:cursor-pointer cursor-pointer border border-white/10 rounded-lg bg-black/50 p-1.5 focus:border-[#c32720] focus:outline-none transition-colors"
                    x-on:change="engancharFichero($event)"
                >
                @error('archivo_mp3')
                    <p class="text-xs text-rose-400 mt-1">{{ $message }}</p>
                @enderror

                {{-- Aviso de subida directa a R2 --}}
                <div class="rounded-xl border border-white/5 bg-black/30 p-3 text-xs font-sans">
                    <template x-if="uppy">
                        <div class="flex items-center gap-2 text-emerald-400">
                            <span class="h-2 w-2 rounded-full bg-emerald-400 shrink-0"></span>
                            <span><strong>Subida directa activa:</strong> Carga a Cloudflare R2 sin límite de peso.</span>
                        </div>
                    </template>
                    <template x-if="!uppy">
                        <div class="flex items-center gap-2 text-amber-300">
                            <span class="h-2 w-2 rounded-full bg-amber-400 shrink-0"></span>
                            <span><strong>Subida estándar:</strong> Para archivos superiores a 100 MB, recarga para activar la subida directa.</span>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Monitor de Progreso de Subida --}}
            <div class="rounded-xl border border-white/5 bg-black/40 p-4 space-y-2.5">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-medium text-gray-300 font-sans">Progreso de transferencia</span>
                    <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-mono uppercase tracking-wider" :class="phaseClass()">
                        <span x-text="phaseLabel"></span>
                    </span>
                </div>

                <div class="h-2 w-full overflow-hidden rounded-full bg-white/10">
                    <div
                        class="h-full bg-[#c32720] transition-all duration-150"
                        :style="`width:${progress}%`"
                    ></div>
                </div>

                <div class="flex items-center justify-between text-xs text-gray-400 font-sans">
                    <span x-text="uploading ? 'Cargando archivo...' : (statusMessage || 'Esperando archivo...')"></span>
                    <span class="font-mono text-white text-xs font-semibold" x-text="progressLabel"></span>
                </div>

                <div class="flex items-center justify-between text-[11px] text-gray-500 pt-2 border-t border-white/5 font-sans">
                    <span x-text="fileSizeLabel ? `Tamaño: ${fileSizeLabel}` : ''"></span>
                    <span x-text="uploadEtaLabel"></span>
                </div>
            </div>
        </div>

        {{-- Columna 2: Carátula o Imagen del Episodio --}}
        <div class="space-y-4 flex flex-col justify-between">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-300 font-sans mb-1">
                        Carátula o imagen del episodio
                    </label>
                    <p class="text-xs text-gray-400 font-sans leading-relaxed">
                        Indica una URL pública o sube un archivo desde tu equipo. Si proporcionas ambos, el archivo cargado tendrá preferencia.
                    </p>
                </div>

                {{-- Opción A: URL remota --}}
                <div>
                    <label class="block text-[11px] font-medium text-gray-400 font-sans mb-1.5">
                        Opción A: URL de imagen remota
                    </label>
                    <input
                        type="url"
                        name="imagen_episodio_url"
                        value="{{ old('imagen_episodio_url') }}"
                        class="h-10 w-full rounded-lg border border-white/10 bg-black/50 px-3.5 text-xs sm:text-sm text-white placeholder-gray-500 focus:border-[#c32720] focus:ring-1 focus:ring-[#c32720] focus:outline-none transition-colors"
                        placeholder="https://ejemplo.com/caratula.jpg"
                    >
                    @error('imagen_episodio_url')
                        <p class="text-xs text-rose-400 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Opción B: Subir archivo --}}
                <div>
                    <label class="block text-[11px] font-medium text-gray-400 font-sans mb-1.5">
                        Opción B: Subir archivo de imagen
                    </label>
                    <input
                        type="file"
                        name="imagen_episodio_file"
                        accept="image/*"
                        class="block w-full text-xs text-gray-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-white/10 file:text-white hover:file:bg-white/15 file:cursor-pointer cursor-pointer border border-white/10 rounded-lg bg-black/50 p-1.5 focus:border-[#c32720] focus:outline-none transition-colors"
                    >
                    @error('imagen_episodio_file')
                        <p class="text-xs text-rose-400 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="rounded-xl border border-white/5 bg-black/30 p-3 text-xs text-gray-400 font-sans flex items-center gap-2">
                <span class="text-base">💡</span>
                <span>La imagen se utilizará como portada en el reproductor web y en la ficha pública de Archive.org.</span>
            </div>
        </div>
    </div>
</div>
