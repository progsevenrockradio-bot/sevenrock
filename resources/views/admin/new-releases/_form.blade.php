@php
    $isEdit = $newRelease->exists;
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Título del Lanzamiento *</label>
        <input name="title" value="{{ old('title', $newRelease->title) }}" class="lucille-product-field w-full" required placeholder="Ej. Rock N' Roll Train">
        @error('title') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
    </div>
    
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Slug del Lanzamiento (Autogenerado si está vacío)</label>
        <input name="slug" value="{{ old('slug', $newRelease->slug) }}" class="lucille-product-field w-full" placeholder="Ej. rock-n-roll-train">
        @error('slug') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Nombre del Artista / Banda *</label>
        <input name="artist_name" value="{{ old('artist_name', $newRelease->artist_name) }}" class="lucille-product-field w-full" required placeholder="Ej. AC/DC">
        @error('artist_name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Asociar con Perfil de Banda (Opcional)</label>
        <select name="radio_artist_id" class="lucille-product-field w-full">
            <option value="">-- No asociar --</option>
            @foreach($radioArtists as $artist)
                <option value="{{ $artist->id }}" {{ old('radio_artist_id', $newRelease->radio_artist_id) == $artist->id ? 'selected' : '' }}>
                    {{ $artist->name }}
                </option>
            @endforeach
        </select>
        @error('radio_artist_id') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Fecha de Lanzamiento</label>
        <input type="date" name="released_at" value="{{ old('released_at', $newRelease->released_at ? $newRelease->released_at->format('Y-m-d') : '') }}" class="lucille-product-field w-full">
        @error('released_at') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Estado Activo</label>
        <select name="is_active" class="lucille-product-field w-full">
            <option value="1" {{ old('is_active', $newRelease->is_active) ? 'selected' : '' }}>Activo (Visible)</option>
            <option value="0" {{ !old('is_active', $newRelease->is_active) ? 'selected' : '' }}>Inactivo (Oculto)</option>
        </select>
        @error('is_active') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Correo del Autor (Destinatario)</label>
        <input name="author_email" value="{{ old('author_email', $newRelease->author_email) }}" class="lucille-product-field w-full" placeholder="Ej. autor@ejemplo.com (para varios usar ';')">
        @error('author_email') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Remitente de la Notificación (Opcional)</label>
        <input name="notification_sender" value="{{ old('notification_sender', $newRelease->notification_sender) }}" class="lucille-product-field w-full" placeholder="Ej. no-reply@sevenrockradio.com">
        @error('notification_sender') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Ruta / URL de la Carátula (Portada)</label>
        <input name="cover_image" value="{{ old('cover_image', $newRelease->cover_image) }}" class="lucille-product-field w-full" placeholder="Ej. catalog/releases/covers/portada.jpg o URL externa">
        @error('cover_image') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Subir Archivo de Carátula (Sobrescribe la ruta anterior)</label>
        <input type="file" name="cover_image_file" class="block w-full text-sm text-[#7b7b7b]">
        @error('cover_image_file') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Ruta / URL de la Pista de Audio (MP3)</label>
        <input name="audio_path" value="{{ old('audio_path', $newRelease->audio_path) }}" class="lucille-product-field w-full" placeholder="Ej. catalog/releases/audio/cancion.mp3 o URL externa">
        @error('audio_path') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Subir Archivo de Audio MP3 (Sobrescribe la ruta anterior)</label>
        <input type="file" name="audio_file" class="block w-full text-sm text-[#7b7b7b]">
        @error('audio_file') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Enlace de YouTube</label>
        <input name="youtube_url" value="{{ old('youtube_url', $newRelease->youtube_url) }}" class="lucille-product-field w-full" placeholder="Ej. https://www.youtube.com/watch?v=...">
        @error('youtube_url') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Enlace de Spotify</label>
        <input name="spotify_url" value="{{ old('spotify_url', $newRelease->spotify_url) }}" class="lucille-product-field w-full" placeholder="Ej. https://open.spotify.com/track/...">
        @error('spotify_url') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Reseña / Descripción</label>
        <textarea name="description" rows="6" class="lucille-product-field w-full" placeholder="Escribe una pequeña reseña del lanzamiento aquí...">{{ old('description', str_replace(['\r\n', '\r', '\n'], "\n", $newRelease->description ?? '')) }}</textarea>
        @error('description') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit" class="lucille-button-solid">{{ $isEdit ? 'Guardar Cambios' : 'Crear Lanzamiento' }}</button>
    <a href="{{ route('admin.new-releases.index') }}" class="lucille-button">Volver a la Lista</a>
</div>
