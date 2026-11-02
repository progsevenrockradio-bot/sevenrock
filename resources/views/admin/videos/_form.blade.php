@php
    $isEdit = $video->exists;
    $admin = $themeAppearance['admin_texts'];
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Título del vídeo <span class="text-[#c32720]">*</span></label>
        <input name="title" id="title-input" value="{{ old('title', $video->title) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" required placeholder="Ej. Iron Maiden - The Trooper (Live)">
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Slug (Enlace permanente)</label>
        <input name="slug" id="slug-input" value="{{ old('slug', $video->slug) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-xs font-mono text-gray-300 placeholder-gray-600 focus:border-[#c32720] focus:outline-none" placeholder="the-trooper-live">
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">URL del vídeo en YouTube <span class="text-[#c32720]">*</span></label>
        <input name="youtube_url" value="{{ old('youtube_url', $video->youtube_url) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" required placeholder="https://www.youtube.com/watch?v=...">
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Ruta o URL de miniatura personalizada (Opcional)</label>
        <input name="image" value="{{ old('image', $video->image) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="https://... o ruta relativa">
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Subir archivo de miniatura</label>
        <input type="file" name="image_file" class="block w-full text-xs text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-white/10 file:text-gray-200 hover:file:bg-white/20">
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Descripción o sinopsis del vídeo</label>
        <textarea name="summary" rows="4" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="Detalles de la actuación o créditos del videoclip...">{{ old('summary', $video->summary) }}</textarea>
    </div>

    <div class="md:col-span-2 flex items-center gap-3 pt-1">
        <input type="hidden" name="is_featured" value="0">
        <input type="checkbox" name="is_featured" id="is_featured" value="1" @checked(old('is_featured', $video->is_featured)) class="h-4 w-4 rounded border-white/20 bg-black/40 text-[#c32720] focus:ring-[#c32720]">
        <label for="is_featured" class="text-sm font-medium text-gray-200 cursor-pointer">Vídeo Destacado (Mostrar en portada principal)</label>
    </div>
</div>

<div class="mt-6 pt-5 border-t border-white/10 flex flex-wrap gap-3">
    <button type="submit" class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold tracking-wide shadow-md shadow-red-950/40 transition-all">
        {{ $isEdit ? 'Guardar Vídeo' : 'Crear Vídeo' }}
    </button>
    <a href="{{ route('admin.videos.index') }}" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all">
        Volver a Vídeos
    </a>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const titleInput = document.getElementById('title-input');
        const slugInput = document.getElementById('slug-input');

        if (titleInput && slugInput) {
            titleInput.addEventListener('input', function () {
                let slug = titleInput.value.toString().toLowerCase()
                    .trim()
                    .replace(/\s+/g, '-')
                    .replace(/[^\w\-]+/g, '')
                    .replace(/\-\-+/g, '-')
                    .replace(/^-+/, '')
                    .replace(/-+$/, '');
                slugInput.value = slug;
            });
        }
    });
</script>
