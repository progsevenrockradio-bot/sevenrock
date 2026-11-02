<div class="space-y-5 font-sans">
    <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-xs text-amber-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <strong class="font-semibold text-amber-100">Bloques estructurales en formato JSON:</strong>
            <span>Si un bloque contiene errores de sintaxis JSON, el sistema impedirá el guardado para proteger la portada y las etiquetas del sitio.</span>
        </div>
        <span class="shrink-0 rounded border border-amber-400/30 bg-black/30 px-2 py-0.5 text-[11px] font-mono text-amber-200">
            Usa «Formatear JSON» para validar antes de guardar
        </span>
    </div>

    <div class="grid gap-5 xl:grid-cols-2">
        <section class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-5">
            <div class="border-b border-white/10 pb-3">
                <h2 class="font-display text-base font-bold uppercase tracking-wide text-[#dcdcdc]">Portada editorial (Home)</h2>
                <p class="mt-0.5 text-xs text-gray-400">Configuración de historias destacadas, podcasts recientes y titulares de secciones en portada.</p>
            </div>

            <div class="space-y-5">
                <x-admin.json-editor
                    name="featured_stories_json"
                    label="Historias destacadas de portada (JSON)"
                    :value="$featuredStoriesJson ?? ''"
                    :rows="12"
                />

                <x-admin.json-editor
                    name="latest_podcasts_json"
                    label="Últimos podcasts en portada (JSON)"
                    :value="$latestPodcastsJson ?? ''"
                    :rows="12"
                />

                <x-admin.json-editor
                    name="home_headings_json"
                    label="Encabezados de secciones de portada (JSON)"
                    :value="$homeHeadingsJson ?? ''"
                    :rows="12"
                />
            </div>
        </section>

        <section class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-5">
            <div class="border-b border-white/10 pb-3">
                <h2 class="font-display text-base font-bold uppercase tracking-wide text-[#dcdcdc]">Textos reutilizables de interfaz</h2>
                <p class="mt-0.5 text-xs text-gray-400">Diccionario de etiquetas de la web pública y textos base del panel administrativo.</p>
            </div>

            <div class="space-y-5">
                <x-admin.json-editor
                    name="ui_texts_json"
                    label="Textos de interfaz pública (JSON)"
                    :value="$uiTextsJson ?? ''"
                    :rows="16"
                />

                <x-admin.json-editor
                    name="admin_texts_json"
                    label="Textos base de administración (JSON)"
                    :value="$adminTextsJson ?? ''"
                    :rows="16"
                />
            </div>
        </section>
    </div>
</div>
