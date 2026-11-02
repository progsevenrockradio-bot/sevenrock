@php $isEdit = $song->exists; @endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Título de la Canción <span class="text-[#c32720]">*</span></label>
        <input name="title" value="{{ old('title', $song->title) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" required placeholder="Ej. Highway to Hell">
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Slug (Enlace permanente)</label>
        <input name="slug" value="{{ old('slug', $song->slug) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-xs font-mono text-gray-300 placeholder-gray-600 focus:border-[#c32720] focus:outline-none" placeholder="highway-to-hell">
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Artista / Banda <span class="text-[#c32720]">*</span></label>
        <input name="artist" value="{{ old('artist', $song->artist) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" required placeholder="Ej. AC/DC">
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Álbum Discográfico</label>
        <input name="album" value="{{ old('album', $song->album) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="Ej. Highway to Hell (1979)">
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Perfil de Artista de Radio</label>
        <select name="band_profile_id" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
            <option value="">-- Ninguno --</option>
            @foreach ($radioArtists as $artist)
                <option value="{{ $artist->id }}" @selected((string) old('band_profile_id', $song->band_profile_id) === (string) $artist->id)>{{ $artist->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Programa de Radio Asociado</label>
        <select name="program_id" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
            <option value="">-- Ninguno --</option>
            @foreach ($programs as $program)
                <option value="{{ $program->id }}" @selected((string) old('program_id', $song->program_id) === (string) $program->id)>{{ $program->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Fecha de Publicación</label>
        <input type="datetime-local" name="published_at" value="{{ old('published_at', optional($song->published_at)->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Duración (Segundos)</label>
            <input type="number" name="duration_seconds" min="0" value="{{ old('duration_seconds', $song->duration_seconds) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="208">
        </div>
        <div>
            <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Orden de Visualización</label>
            <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $song->sort_order) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="0">
        </div>
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Ruta o URL del Audio (MP3)</label>
        <input name="audio_url" value="{{ old('audio_url', $song->audio_url) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="https://... o ruta de archivo">
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Ruta o URL de la Portada</label>
        <input name="cover_image" value="{{ old('cover_image', $song->cover_image) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="https://... o ruta de imagen">
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Letra de la Canción (Lyrics)</label>
        <textarea name="lyrics" rows="5" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="Letra completa de la canción...">{{ old('lyrics', $song->lyrics) }}</textarea>
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Biografía / Información de la Banda</label>
        <textarea name="band_info" rows="4" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="Información histórica sobre el tema o la banda...">{{ old('band_info', $song->band_info) }}</textarea>
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Miembros de la Banda (Un integrante por línea)</label>
        <textarea name="band_members_text" rows="3" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="Brian Johnson - Voz&#10;Angus Young - Guitarra líder">{{ old('band_members_text', $bandMembersText ?? '') }}</textarea>
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Enlaces Sociales (Formato: Etiqueta|URL)</label>
        <textarea name="social_links_text" rows="3" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="Spotify|https://spotify.com/...&#10;Instagram|https://instagram.com/...">{{ old('social_links_text', $socialLinksText ?? '') }}</textarea>
    </div>

    <div class="md:col-span-2 flex items-center gap-3 pt-1">
        <input type="checkbox" name="is_live" value="1" @checked(old('is_live', $song->is_live)) class="h-4 w-4 rounded border-white/20 bg-black/40 text-[#c32720] focus:ring-[#c32720]">
        <label class="text-sm font-medium text-gray-300 cursor-pointer">Pista en directo (Live recording)</label>
    </div>
</div>

<div class="mt-6 pt-5 border-t border-white/10 flex flex-wrap gap-3">
    <button type="submit" class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold tracking-wide shadow-md shadow-red-950/40 transition-all">
        {{ $isEdit ? 'Guardar Cambios' : 'Crear Canción' }}
    </button>
    <a href="{{ route('admin.songs.index') }}" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all">
        Volver a Canciones
    </a>
</div>
