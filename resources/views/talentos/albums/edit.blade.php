<x-layouts.talent :title="'Talentos - Editar Álbum'" :talent="$talent">
    <section class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Editar: {{ $album->title }}</h1>
            <a href="{{ route('talents.albums.index') }}" class="lucille-button text-sm">← Volver</a>
        </div>

        <div class="border border-white/10 bg-[#10161b] p-8">
            <form method="POST" action="{{ route('talents.albums.update', $album->id) }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('POST')

                <div class="flex flex-wrap gap-8">
                    @if ($album->coverUrl())
                        <div class="w-40">
                            <img src="{{ $album->coverUrl() }}" alt="{{ $album->title }}" class="w-full" loading="lazy">
                            <label class="mt-2 flex items-center gap-2 text-xs text-[#7b7b7b]">
                                <input type="checkbox" name="remove_cover" value="1">
                                Eliminar portada
                            </label>
                        </div>
                    @endif
                    <div class="flex-1 space-y-6">
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Título del álbum *</label>
                            <input type="text" name="title" value="{{ old('title', $album->title) }}" required
                                class="lucille-product-field w-full">
                            @error('title') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Cambiar portada</label>
                            <input type="file" name="cover_image" accept="image/*"
                                class="lucille-product-field w-full text-sm text-[#c7d0d8] file:mr-4 file:border-0 file:bg-[#1d1d1d] file:px-4 file:py-2 file:text-sm file:text-white">
                        </div>

                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Fecha de lanzamiento</label>
                            <input type="date" name="release_date" value="{{ old('release_date', $album->release_date?->format('Y-m-d')) }}"
                                class="lucille-product-field w-full">
                        </div>

                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Descripción</label>
                            <textarea name="description" rows="4" class="lucille-product-field w-full">{{ old('description', $album->description) }}</textarea>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Canciones (JSON)</label>
                    <p class="mb-2 text-xs text-[#7b7b7b]">Cada canción puede incluir "preview_url" para habilitar preview de 30s.</p>
                    <p class="mb-2 text-xs text-[#5a5a5a]">Ej: [{"title":"Canción","duration":"3:45","preview_url":"https://..."}]</p>
                    <textarea name="tracks_json" rows="8" class="lucille-product-field w-full font-mono text-xs">{{ old('tracks_json', json_encode($album->tracks ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}</textarea>
                    @error('tracks_json') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror

                    @php $talentMp3s = $talent ? $talent->media()->where('type', 'mp3')->latest()->get() : collect(); @endphp
                    @if ($talentMp3s->isNotEmpty())
                        <div class="mt-4 rounded border border-white/10 bg-[#1d1d1d] p-4">
                            <h4 class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Tus MP3s subidos</h4>
                            <p class="mt-1 text-xs text-[#5a5a5a]">Copia la URL y pégala en "preview_url" del JSON de arriba.</p>
                            <div class="mt-3 space-y-2">
                                @foreach ($talentMp3s as $mp3)
                                    <div class="flex items-center gap-3 rounded bg-[#161616] px-3 py-2">
                                        <span class="flex-shrink-0 text-xs text-[#5a5a5a]">MP3</span>
                                        <span class="flex-1 truncate text-xs text-[#c7d0d8]">{{ $mp3->title ?? $mp3->filename }}</span>
                                        <button type="button" class="flex-shrink-0 text-xs text-lucille-accent hover:text-white"
                                            onclick="navigator.clipboard.writeText('{{ $mp3->url }}').then(() => { this.textContent='✓ Copiado'; setTimeout(() => this.textContent='Copiar URL', 2000) })"
                                        >Copiar URL</button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="mt-4 rounded border border-white/10 bg-[#1d1d1d] p-4 text-xs text-[#5a5a5a]">
                            No has subido MP3s. Ve a <a href="{{ route('talents.media.index') }}" class="text-lucille-accent underline">Mi Música</a> para subir archivos.
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    <input type="checkbox" name="is_published" id="is_published" value="1" @checked(old('is_published', $album->is_published))
                        class="h-4 w-4 accent-white">
                    <label for="is_published" class="text-sm text-[#c7d0d8]">Publicado</label>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="submit" class="lucille-button-solid">Guardar Cambios</button>
                    <a href="{{ route('talents.albums.index') }}" class="lucille-button">Cancelar</a>
                </div>
            </form>
        </div>
    </section>
</x-layouts.talent>
