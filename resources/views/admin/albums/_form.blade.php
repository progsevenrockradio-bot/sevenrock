@php
    $isEdit = $album->exists;
    $admin = $themeAppearance['admin_texts'];
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Título del álbum <span class="text-[#c32720]">*</span></label>
        <input name="title" value="{{ old('title', $album->title) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" required placeholder="Ej. Back in Black">
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Slug (Enlace permanente)</label>
        <input name="slug" value="{{ old('slug', $album->slug) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-xs font-mono text-gray-300 placeholder-gray-600 focus:border-[#c32720] focus:outline-none" placeholder="back-in-black">
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Artista / Banda <span class="text-[#c32720]">*</span></label>
        <input name="artist" value="{{ old('artist', $album->artist) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" required placeholder="Ej. AC/DC">
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Fecha de lanzamiento</label>
        <input type="date" name="released_at" value="{{ old('released_at', optional($album->released_at)->format('Y-m-d')) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Ruta o URL de la carátula</label>
        <input name="cover_image" value="{{ old('cover_image', $album->cover_image) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="https://... o ruta relativa">
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Subir archivo de carátula</label>
        <input type="file" name="cover_image_file" class="block w-full text-xs text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-white/10 file:text-gray-200 hover:file:bg-white/20">
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Resumen o crítica del disco</label>
        <textarea name="summary" rows="4" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="Breve reseña sobre el álbum...">{{ old('summary', $album->summary) }}</textarea>
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Lista de canciones (Tracklist)</label>
        <textarea name="tracks_text" rows="5" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm font-mono text-gray-200 focus:border-[#c32720] focus:outline-none" placeholder="Una canción por línea...">{{ old('tracks_text', $tracksText ?? '') }}</textarea>
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Enlaces de compra o escucha</label>
        <textarea name="buy_links_text" rows="3" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="Spotify|https://...&#10;Vinilo|https://...">{{ old('buy_links_text', $buyLinksText ?? '') }}</textarea>
    </div>
</div>

<div class="mt-6 pt-5 border-t border-white/10 flex flex-wrap gap-3">
    <button type="submit" class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold tracking-wide shadow-md shadow-red-950/40 transition-all">
        {{ $isEdit ? 'Guardar Álbum' : 'Crear Álbum' }}
    </button>
    <a href="{{ route('admin.albums.index') }}" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all">
        Volver a Álbumes
    </a>
</div>
