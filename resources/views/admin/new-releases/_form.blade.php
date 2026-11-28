@php
    $isEdit = $newRelease->exists;
    $missingFields = $missingFields ?? [];
    $potentialDuplicates = $potentialDuplicates ?? collect();
@endphp

@if ($isEdit && count($missingFields) > 0)
    <div class="mb-5 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-xs text-amber-200">
        <strong class="uppercase tracking-wider">Datos pendientes en esta ficha:</strong>
        <span class="ml-1">{{ implode(' · ', $missingFields) }}</span>
    </div>
@endif

@if ($isEdit && $potentialDuplicates->isNotEmpty())
    <div class="mb-5 rounded-xl border border-sky-500/30 bg-sky-500/10 p-4 text-xs text-sky-200 space-y-1.5">
        <div class="font-semibold uppercase tracking-wider">Posibles registros relacionados (no se fusionan automáticamente)</div>
        @foreach ($potentialDuplicates as $dup)
            <div>
                <a href="{{ route('admin.new-releases.edit', $dup['release']) }}" class="underline font-medium text-white">
                    #{{ $dup['release']->id }} · {{ $dup['release']->title }} — {{ $dup['release']->artist_name }}
                </a>
                <span class="text-sky-300">({{ $dup['release']->releaseTypeLabel() }}, {{ $dup['release']->effectiveReviewStatus() }})</span>
                — {{ $dup['reason'] }}
                @if ($dup['different_type'])
                    <span class="ml-1 rounded bg-amber-500/20 px-1.5 py-0.5 text-[10px] text-amber-200">Tipo distinto (no fusionar single/álbum)</span>
                @endif
            </div>
        @endforeach
    </div>
@endif

@if ($isEdit && filled($newRelease->proposal_summary))
    <div class="mb-5 rounded-xl border border-white/10 bg-white/[0.03] p-4 text-xs text-gray-300 space-y-1.5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="font-semibold uppercase tracking-wider text-amber-300">Propuesta recibida por correo (sin verificar)</span>
            <span class="font-mono text-[11px] text-gray-400">
                {{ $newRelease->source_sender_email ?? $newRelease->author_email ?? 'Remitente no registrado' }}
                @if ($newRelease->received_at)
                    · Recibido {{ $newRelease->received_at->format('d/m/Y H:i') }}
                @endif
            </span>
        </div>
        <p class="leading-relaxed text-gray-300 whitespace-pre-line">{{ $newRelease->proposal_summary }}</p>
        @if (! empty($newRelease->source_sender_auth))
            <div class="text-[11px] text-gray-400">
                Autenticación remitente: <strong class="text-gray-200">{{ $newRelease->source_sender_auth['status'] ?? 'n/d' }}</strong>
                (SPF: {{ $newRelease->source_sender_auth['spf'] ?? '-' }}, DKIM: {{ $newRelease->source_sender_auth['dkim'] ?? '-' }}, DMARC: {{ $newRelease->source_sender_auth['dmarc'] ?? '-' }})
            </div>
        @endif
    </div>
@endif

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Título del Lanzamiento <span class="text-[#c32720]">*</span></label>
        <input name="title" value="{{ old('title', $newRelease->title) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" required>
        @error('title') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Slug (Autogenerado si está vacío)</label>
        <input name="slug" value="{{ old('slug', $newRelease->slug) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-xs font-mono text-gray-300 focus:border-[#c32720] focus:outline-none">
        @error('slug') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Nombre del Artista / Banda <span class="text-[#c32720]">*</span></label>
        <input name="artist_name" value="{{ old('artist_name', $newRelease->artist_name) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" required>
        @error('artist_name') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Tipo de lanzamiento</label>
        <select name="release_type" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
            <option value="">-- Sin clasificar --</option>
            @foreach (\App\Models\NewRelease::RELEASE_TYPES as $typeKey => $typeLabel)
                <option value="{{ $typeKey }}" @selected(old('release_type', $newRelease->release_type) === $typeKey)>{{ $typeLabel }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Fecha de anuncio</label>
        <input type="date" name="announced_at" value="{{ old('announced_at', $newRelease->announced_at?->format('Y-m-d')) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Fecha de lanzamiento musical</label>
        <input type="date" name="released_at" value="{{ old('released_at', $newRelease->released_at?->format('Y-m-d')) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Asociar con Artista de Radio (Opcional)</label>
        <select name="radio_artist_id" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
            <option value="">-- No asociar --</option>
            @foreach($radioArtists as $artist)
                <option value="{{ $artist->id }}" @selected(old('radio_artist_id', $newRelease->radio_artist_id) == $artist->id)>{{ $artist->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Visibilidad pública</label>
        <select name="is_active" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
            <option value="1" @selected(old('is_active', $newRelease->is_active))>Activo (Visible públicamente)</option>
            <option value="0" @selected(! old('is_active', $newRelease->is_active))>Inactivo (Por revisar / oculto)</option>
        </select>
        @error('is_active') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Ruta o URL de la Portada</label>
        <input name="cover_image" value="{{ old('cover_image', $newRelease->cover_image) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="https://... o ruta relativa">
        @error('cover_image') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Subir archivo de carátula</label>
        <input type="file" name="cover_image_file" class="block w-full text-xs text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-white/10 file:text-gray-200 hover:file:bg-white/20">
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Procedencia de la portada</label>
        <input name="cover_source" value="{{ old('cover_source', $newRelease->cover_source) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="Ej. Press kit oficial del sello">
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Permiso o crédito de portada</label>
        <input name="cover_permission_credit" value="{{ old('cover_permission_credit', $newRelease->cover_permission_credit) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="Ej. Autorizado para difusión en prensa / Foto: ...">
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Enlace oficial</label>
        <input name="official_url" value="{{ old('official_url', $newRelease->official_url) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="https://...">
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Enlace de descarga (solo referencia)</label>
        <input name="download_url" value="{{ old('download_url', $newRelease->download_url) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="https://...">
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Enlace de YouTube</label>
        <input name="youtube_url" value="{{ old('youtube_url', $newRelease->youtube_url) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Enlace de Spotify</label>
        <input name="spotify_url" value="{{ old('spotify_url', $newRelease->spotify_url) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Ruta o URL del Audio (MP3)</label>
        <input name="audio_path" value="{{ old('audio_path', $newRelease->audio_path) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Subir archivo de audio MP3</label>
        <input type="file" name="audio_file" class="block w-full text-xs text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-white/10 file:text-gray-200 hover:file:bg-white/20">
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Embargo activo</label>
        <select name="is_embargo_active" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
            <option value="0" @selected(! old('is_embargo_active', $newRelease->is_embargo_active))>Sin embargo activo</option>
            <option value="1" @selected(old('is_embargo_active', $newRelease->is_embargo_active))>Bajo embargo (bloquea publicación)</option>
        </select>
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Embargo hasta</label>
        <input type="datetime-local" name="embargo_until" value="{{ old('embargo_until', $newRelease->embargo_until?->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Correo del Autor / Banda</label>
        <input name="author_email" value="{{ old('author_email', $newRelease->author_email) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Remitente Notificación (Opcional)</label>
        <input name="notification_sender" value="{{ old('notification_sender', $newRelease->notification_sender) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Condiciones de uso / notas de embargo</label>
        <textarea name="usage_conditions" rows="2" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">{{ old('usage_conditions', $newRelease->usage_conditions) }}</textarea>
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Reseña editorial verificada</label>
        <textarea name="description" rows="5" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="Escribe o revisa la reseña verificada antes de publicar...">{{ old('description', str_replace(['\r\n', '\r', '\n'], "\n", $newRelease->description ?? '')) }}</textarea>
        @error('description') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-6 pt-5 border-t border-white/10 flex flex-wrap gap-3">
    <button type="submit" class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold tracking-wide transition-all">
        {{ $isEdit ? 'Guardar Cambios' : 'Crear Lanzamiento' }}
    </button>
    <a href="{{ route('admin.new-releases.index', ['tab' => $isEdit ? $newRelease->effectiveReviewStatus() : 'pending_review']) }}" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all">
        Volver a la Bandeja
    </a>
</div>
