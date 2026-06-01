<section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-[10px] uppercase tracking-[.28em] text-[#8b8b8b]">Bloque 1</p>
            <h2 class="mt-2 font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Archivos Multimedia</h2>
            <p class="mt-3 max-w-3xl text-sm leading-7 text-[#7b7b7b]">
                Aquí van los dos elementos más pesados y visibles del episodio: el MP3 principal y la carátula o imagen del episodio.
            </p>
        </div>
        <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] px-4 py-3 text-xs uppercase tracking-[.18em] text-[#b8e6c3]">
            Acción principal de esta pantalla
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
            <label class="mb-3 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Archivo MP3</label>
            <input type="file" name="archivo_mp3" accept="audio/mpeg,audio/mp3" class="lucille-product-field w-full">
            @error('archivo_mp3')
                <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
            @enderror

            <div class="mt-4 space-y-2 border border-[#242424] bg-[#131313] p-4">
                <div class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Estado de carga</div>
                <div class="inline-flex items-center rounded border px-2.5 py-1 text-[10px] uppercase tracking-[.18em]" :class="phaseClass()">
                    <span x-text="phaseLabel"></span>
                </div>
                <div class="text-[11px] leading-5 text-[#8b8b8b]" x-text="phaseDetailLabel"></div>
                <div class="h-1.5 overflow-hidden border border-[#242424] bg-[#101010]">
                    <div class="h-full bg-[color:var(--color-lucille-accent)] transition-[width] duration-150" :style="`width:${progress}%`"></div>
                </div>
                <div class="flex items-center justify-between text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">
                    <span x-text="uploading ? 'Subiendo' : (statusMessage || 'Listo para subir')"></span>
                    <span x-text="progressLabel"></span>
                </div>
                <div class="flex items-center justify-between text-[10px] uppercase tracking-[.18em] text-[#5f5f5f]">
                    <span x-text="fileSizeLabel ? `Peso: ${fileSizeLabel}` : ''"></span>
                    <span x-text="uploadEtaLabel"></span>
                </div>
            </div>
        </div>

        <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
            <div class="flex items-center justify-between gap-3">
                <label class="block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Carátula / imagen del episodio</label>
                <span class="text-[10px] uppercase tracking-[.18em] text-[#8b8b8b]">URL o archivo</span>
            </div>
            <p class="mt-2 text-sm text-[#7b7b7b]">
                Puedes usar una URL pública o subir el archivo directamente. Si completas ambos, el archivo local tiene prioridad y se reutiliza después.
            </p>

            <div class="mt-4 grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">URL de imagen</label>
                    <input name="imagen_episodio_url" value="{{ old('imagen_episodio_url') }}" class="lucille-product-field w-full" placeholder="https://...">
                    @error('imagen_episodio_url')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Archivo de imagen</label>
                    <input type="file" name="imagen_episodio_file" accept="image/*" class="lucille-product-field w-full">
                    @error('imagen_episodio_file')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>
    </div>
</section>
