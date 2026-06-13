<x-layouts.admin :title="'Editar Plantilla - '.(optional($themeSettings)->site_name ?? config('app.name'))">
    <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-[#2b2b2b] pb-6 mb-6">
            <div>
                <a href="{{ route('admin.email-templates.index') }}" class="text-sm text-[#7b7b7b] hover:text-[#dcdcdc] transition-colors mb-2 inline-block">&larr; Volver a las plantillas</a>
                <h1 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Editar Plantilla: <span class="text-lucille-accent font-mono lowercase tracking-normal">{{ $relativePath }}</span></h1>
            </div>
            <div>
                <button form="template-edit-form" type="submit" class="lucille-button-solid">Guardar Cambios</button>
            </div>
        </div>

        <div class="border border-[#2b2b2b] bg-[rgba(195,39,32,.06)] p-4 text-sm text-[#ffb8b8] mb-6 rounded">
            <strong>⚠️ Precaución:</strong> Estás editando el código fuente Blade de un correo del sistema. Asegúrate de no romper la sintaxis HTML ni las variables PHP <code>&#123;&#123; $variable &#125;&#125;</code>, ya que esto podría causar errores 500 al enviar el correo.
        </div>

        <form id="template-edit-form" action="{{ route('admin.email-templates.update', $encodedPath) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="flex flex-col">
                <label for="content" class="mb-2 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Código Blade (HTML/CSS/PHP)</label>
                <textarea 
                    id="content" 
                    name="content" 
                    rows="30" 
                    class="lucille-product-field font-mono text-[13px] leading-relaxed p-4 w-full bg-[#0d0d0f] border-[#2b2b2b] focus:border-[var(--color-lucille-accent)]" 
                    spellcheck="false"
                    required
                >{{ old('content', $content) }}</textarea>
                @error('content')
                    <p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="lucille-button-solid text-lg px-8 py-3">💾 Guardar Plantilla</button>
            </div>
        </form>
    </section>
</x-layouts.admin>
