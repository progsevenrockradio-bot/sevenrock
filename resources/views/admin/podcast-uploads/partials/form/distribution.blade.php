<section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
    <div class="flex flex-col gap-3">
        <div>
            <p class="text-[10px] uppercase tracking-[.28em] text-[#8b8b8b]">Bloque 3</p>
            <h2 class="mt-2 font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Distribución y Estados</h2>
            <p class="mt-3 text-sm leading-7 text-[#7b7b7b]">
                Opciones técnicas del pipeline: qué se conserva, qué se sincroniza y qué se envía al resto de sistemas.
            </p>
        </div>
        <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] px-4 py-3 text-xs uppercase tracking-[.18em] text-[#f3c26b]">
            Sin estado manual: el pipeline lo actualiza automáticamente
        </div>
    </div>

    <div class="mt-5 space-y-4">
        <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4">
            <h3 class="text-xs uppercase tracking-[.18em] text-[#b8e6c3]">Estado operativo</h3>
            <p class="mt-3 text-sm leading-6 text-[#c7c7c7]">
                Este formulario no expone un selector manual de <span class="text-white">Borrador / Publicado / Procesando</span>.
                El estado real se deriva del pipeline, de la carga del MP3 y de los jobs de RadioBOSS, Archive.org y notificación.
            </p>
            <div class="mt-4 grid gap-3">
                <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.7)] p-3">
                    <div class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Procesando</div>
                    <div class="mt-1 text-sm text-[#e0e0e0]">La carga está en curso o el pipeline trabaja en segundo plano.</div>
                </div>
                <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.7)] p-3">
                    <div class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Publicado</div>
                    <div class="mt-1 text-sm text-[#e0e0e0]">Se considera listo cuando RadioBOSS, Archive.org y notificación terminan correctamente.</div>
                </div>
                <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.7)] p-3">
                    <div class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Borrador</div>
                    <div class="mt-1 text-sm text-[#e0e0e0]">Es el estado implícito antes de completar una subida exitosa.</div>
                </div>
            </div>
        </div>

        <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4">
            <h3 class="text-xs uppercase tracking-[.18em] text-[#b8e6c3]">Distribución</h3>
            <label class="mt-4 flex items-start gap-3 text-sm text-[#dcdcdc]">
                <input type="checkbox" name="download_processed_mp3" value="1" @checked(old('download_processed_mp3')) class="mt-1 h-4 w-4">
                <span>
                    <span class="block font-medium text-white">Conservar una copia local del MP3 procesado</span>
                    <span class="mt-1 block text-xs leading-5 text-[#7b7b7b]">La descarga quedará disponible cuando termine el procesamiento.</span>
                </span>
            </label>

            <label class="mt-4 flex items-start gap-3 text-sm text-[#dcdcdc]">
                <input type="checkbox" name="sync_archive_org" value="1" @checked(old('sync_archive_org', true)) class="mt-1 h-4 w-4">
                <span>
                    <span class="block font-medium text-white">Sincronizar también con Archive.org</span>
                    <span class="mt-1 block text-xs leading-5 text-[#7b7b7b]">Activa la subida al archivo público además de RadioBOSS.</span>
                </span>
            </label>

            <div class="mt-4 border border-[#2b2b2b] bg-[rgba(16,16,18,.7)] p-4">
                <h4 class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Notas técnicas</h4>
                <p class="mt-2 text-sm leading-6 text-[#c7c7c7]">
                    Si más adelante necesitas tags, categorías o un selector manual de estado, conviene crear columnas y validación explícita en `RadioProgram`
                    antes de mostrarlos aquí.
                </p>
            </div>
        </div>
    </div>
</section>
