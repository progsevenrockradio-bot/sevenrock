@php
    $isEdit = $newRelease->exists;
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Título del Lanzamiento <span class="text-[#c32720]">*</span></label>
        <input name="title" value="{{ old('title', $newRelease->title) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" required placeholder="Ej. Rock N' Roll Train">
        @error('title') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    </div>
    
    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Slug (Autogenerado si está vacío)</label>
        <input name="slug" value="{{ old('slug', $newRelease->slug) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-xs font-mono text-gray-300 placeholder-gray-600 focus:border-[#c32720] focus:outline-none" placeholder="rock-n-roll-train">
        @error('slug') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Nombre del Artista / Banda <span class="text-[#c32720]">*</span></label>
        <input name="artist_name" value="{{ old('artist_name', $newRelease->artist_name) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" required placeholder="Ej. AC/DC">
        @error('artist_name') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Asociar con Artista de Radio (Opcional)</label>
        <select name="radio_artist_id" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
            <option value="">-- No asociar --</option>
            @foreach($radioArtists as $artist)
                <option value="{{ $artist->id }}" {{ old('radio_artist_id', $newRelease->radio_artist_id) == $artist->id ? 'selected' : '' }}>
                    {{ $artist->name }}
                </option>
            @endforeach
        </select>
        @error('radio_artist_id') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Fecha de Lanzamiento</label>
        <input type="date" name="released_at" value="{{ old('released_at', $newRelease->released_at ? $newRelease->released_at->format('Y-m-d') : '') }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
        @error('released_at') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Estado</label>
        <select name="is_active" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
            <option value="1" {{ old('is_active', $newRelease->is_active) ? 'selected' : '' }}>Activo (Visible públicamente)</option>
            <option value="0" {{ !old('is_active', $newRelease->is_active) ? 'selected' : '' }}>Inactivo (Oculto)</option>
        </select>
        @error('is_active') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Correo del Autor / Banda</label>
        <input name="author_email" value="{{ old('author_email', $newRelease->author_email) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="autor@ejemplo.com">
        @error('author_email') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Remitente Notificación (Opcional)</label>
        <input name="notification_sender" value="{{ old('notification_sender', $newRelease->notification_sender) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="noreply@sevenrockradio.com">
        @error('notification_sender') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Ruta o URL de la Portada</label>
        <input name="cover_image" value="{{ old('cover_image', $newRelease->cover_image) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="https://... o ruta relativa">
        @error('cover_image') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Subir archivo de carátula</label>
        <input type="file" name="cover_image_file" class="block w-full text-xs text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-white/10 file:text-gray-200 hover:file:bg-white/20">
        @error('cover_image_file') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Ruta o URL del Audio (MP3)</label>
        <input name="audio_path" value="{{ old('audio_path', $newRelease->audio_path) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="https://... o ruta relativa">
        @error('audio_path') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Subir archivo de audio MP3</label>
        <input type="file" name="audio_file" class="block w-full text-xs text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-white/10 file:text-gray-200 hover:file:bg-white/20">
        @error('audio_file') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Enlace de YouTube</label>
        <input name="youtube_url" value="{{ old('youtube_url', $newRelease->youtube_url) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="https://www.youtube.com/watch?v=...">
        @error('youtube_url') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Enlace de Spotify</label>
        <input name="spotify_url" value="{{ old('spotify_url', $newRelease->spotify_url) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="https://open.spotify.com/track/...">
        @error('spotify_url') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Reseña / Descripción</label>
        <textarea name="description" rows="5" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="Escribe una pequeña reseña del lanzamiento aquí...">{{ old('description', str_replace(['\r\n', '\r', '\n'], "\n", $newRelease->description ?? '')) }}</textarea>
        @error('description') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-6 pt-5 border-t border-white/10 flex flex-wrap gap-3">
    <button type="submit" class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold tracking-wide shadow-md shadow-red-950/40 transition-all">
        {{ $isEdit ? 'Guardar Cambios' : 'Crear Lanzamiento' }}
    </button>
    <a href="{{ route('admin.new-releases.index') }}" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all">
        Volver a la Lista
    </a>
</div>
