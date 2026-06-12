@php $isEdit = $bandProfile->exists; @endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Nombre de la Banda</label>
        <input name="name" value="{{ old('name', $bandProfile->name) }}" class="lucille-product-field w-full" required>
        @error('name')
            <span class="mt-1 block text-xs text-red-400 font-mono uppercase">{{ $message }}</span>
        @enderror
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Ruta de la Imagen de Perfil</label>
        <input name="image_path" value="{{ old('image_path', $bandProfile->image_path) }}" class="lucille-product-field w-full" placeholder="Ej: catalog/releases/covers/foto.jpg">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Fecha de Fundación</label>
        <input type="date" name="founded_date" value="{{ old('founded_date', $bandProfile->founded_date?->format('Y-m-d')) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">URL del Logotipo</label>
        <input name="logo_path" value="{{ old('logo_path', $bandProfile->logo_path) }}" class="lucille-product-field w-full" placeholder="https://example.com/logo.png">
        @if($bandProfile->logo_path)
            <div class="mt-2"><img src="{{ $bandProfile->logo_path }}" loading="lazy" class="h-16 w-auto object-contain border border-white/10" alt="logo"></div>
        @endif
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">País de Origen</label>
        <input name="country" value="{{ old('country', $bandProfile->country) }}" class="lucille-product-field w-full" placeholder="Ej: Alemania">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Género Musical</label>
        <input name="genre" value="{{ old('genre', $bandProfile->genre) }}" class="lucille-product-field w-full" placeholder="Ej: Heavy Metal">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Cantidad de Miembros</label>
        <input type="number" name="members_count" value="{{ old('members_count', $bandProfile->members_count) }}" class="lucille-product-field w-full" min="0">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Estado</label>
        <select name="status" class="lucille-product-field w-full">
            <option value="">Seleccionar...</option>
            <option value="active" {{ old('status', $bandProfile->status) === 'active' ? 'selected' : '' }}>Activo</option>
            <option value="on_hold" {{ old('status', $bandProfile->status) === 'on_hold' ? 'selected' : '' }}>En pausa</option>
            <option value="disbanded" {{ old('status', $bandProfile->status) === 'disbanded' ? 'selected' : '' }}>Separados</option>
            <option value="unknown" {{ old('status', $bandProfile->status) === 'unknown' ? 'selected' : '' }}>Desconocido</option>
        </select>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Biografía</label>
        <textarea name="biography" rows="6" class="lucille-product-field w-full" placeholder="Escribe aquí la historia o biografía de la banda...">{{ old('biography', $bandProfile->biography) }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Resumen Editorial</label>
        <textarea name="editorial_summary" rows="4" class="lucille-product-field w-full" placeholder="Breve resumen o slogan de presentación...">{{ old('editorial_summary', $bandProfile->editorial_summary) }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Datos Curiosos (Uno por línea)</label>
        <textarea name="featured_facts_text" rows="5" class="lucille-product-field w-full" placeholder="Ej: Fundada por ex-miembros de Iron Maiden&#10;Ganadores de premio revelación 2025">{{ old('featured_facts_text', $featuredFactsText ?? '') }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Enlaces Oficiales (Formato: Etiqueta|Enlace - uno por línea)</label>
        <textarea name="official_links_text" rows="5" class="lucille-product-field w-full" placeholder="Ej: Facebook|https://facebook.com/banda&#10;Spotify|https://spotify.com/...&#10;Instagram|https://instagram.com/...">{{ old('official_links_text', $officialLinksText ?? '') }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Artistas Relacionados (Uno por línea)</label>
        <textarea name="related_artists_text" rows="4" class="lucille-product-field w-full" placeholder="Ej: Metallica&#10;Megadeth">{{ old('related_artists_text', $relatedArtistsText ?? '') }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Sellos Discográficos (Uno por línea)</label>
        <textarea name="labels" rows="3" class="lucille-product-field w-full" placeholder="Ej: Nuclear Blast&#10;Sony Music">{{ old('labels', $labelsText ?? $bandProfile->labels) }}</textarea>
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit" class="lucille-button-solid">{{ $isEdit ? 'Actualizar Banda' : 'Registrar Banda' }}</button>
    <a href="{{ route('agency.bands') }}" class="lucille-button">Volver a Mis Bandas</a>
</div>
