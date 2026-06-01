<section class="space-y-6">
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Contenido y Textos</h2>
        <div class="mt-4 border border-[#5a4a1e] bg-[rgba(255,193,7,.08)] px-4 py-3 text-sm leading-6 text-[#f0d48f]">
            Atención: los campos de esta pestaña son JSON. Si el formato es inválido, el guardado puede fallar y romper partes de la web.
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Home editorial</h3>
            <div class="mt-6 space-y-6">
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

        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Textos reutilizables</h3>
            <div class="mt-6 space-y-6">
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
</section>
