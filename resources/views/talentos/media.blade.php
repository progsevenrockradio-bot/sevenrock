<x-layouts.talent :title="'Talentos - Media'" :talent="$talent">
    <section class="space-y-6">
        @if (session('status'))
            <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[.9fr_1.1fr]">
            <div class="border border-white/10 bg-[#10161b] p-8">
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Media</h1>
                <p class="mt-2 text-sm text-[#7b7b7b]">Sube fotos, MP3, documentos o videos al almacenamiento de Backblaze B2.</p>
                <div class="mt-6 border border-white/5 bg-white/[0.02] p-5 rounded-[8px] text-xs space-y-3 text-[#7b7b7b] font-sans">
                    <div class="font-display uppercase tracking-wider text-[#dcdcdc] font-semibold text-xs border-b border-white/5 pb-2 mb-1">
                        Resumen de Límites (Plan: {{ strtoupper($talent->plan) }})
                    </div>
                    <div class="flex justify-between items-center">
                        <span>Canciones (MP3):</span>
                        <span class="text-[#dcdcdc] font-mono">{{ $usage['songs'] }} / {{ $limits['songs'] ?? 0 }} <span class="text-[10px] text-gray-500 font-sans ml-1">(Máx. {{ $talent->plan === 'free' ? '12MB' : ($talent->plan === 'basic' ? '15MB' : ($talent->plan === 'pro' ? '20MB' : '25MB')) }} c/u)</span></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span>Fotografías:</span>
                        <span class="text-[#dcdcdc] font-mono">{{ $usage['photos'] }} / {{ $limits['photos'] ?? 0 }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span>Documentos:</span>
                        <span class="text-[#dcdcdc] font-mono">{{ $usage['documents'] }} / {{ $limits['documents'] ?? 0 }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span>Videos:</span>
                        <span class="text-[#dcdcdc] font-mono">{{ $usage['videos'] }} / {{ $limits['videos'] ?? 0 }}</span>
                    </div>
                    <div class="flex justify-between items-center border-t border-white/5 pt-2 mt-1 font-semibold">
                        <span>Almacenamiento Total Usado:</span>
                        <span class="text-[#dcdcdc] font-mono">{{ number_format((float) ($usage['storage_used_mb'] ?? 0), 2) }} MB / {{ (int) ($limits['storage_mb'] ?? 0) }} MB</span>
                    </div>
                </div>

                <form action="{{ route('talents.media.upload') }}" method="POST" enctype="multipart/form-data" class="mt-8 space-y-5">
                    @csrf
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Tipo</label>
                        <select name="type" class="lucille-product-field w-full">
                            <option value="photo" @selected(old('type') === 'photo')>Foto</option>
                            <option value="mp3" @selected(old('type') === 'mp3')>MP3</option>
                            <option value="document" @selected(old('type') === 'document')>Documento</option>
                            <option value="video" @selected(old('type') === 'video')>Video</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Título</label>
                        <input name="title" value="{{ old('title') }}" class="lucille-product-field w-full">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Descripción</label>
                        <textarea name="description" rows="4" class="lucille-product-field w-full">{{ old('description') }}</textarea>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Archivo</label>
                        <input type="file" name="file" class="lucille-product-field w-full">
                    </div>
                    <div class="flex items-center gap-2 py-1 select-none">
                        <input type="hidden" name="is_exclusive" value="0">
                        <input type="checkbox" name="is_exclusive" id="is_exclusive" value="1" @checked(old('is_exclusive')) class="h-4 w-4 rounded border-[#2b2b2b] bg-[#151515] text-[#c32720] focus:ring-[#c32720] cursor-pointer">
                        <label for="is_exclusive" class="text-xs uppercase tracking-[.18em] text-[#dcdcdc] cursor-pointer">¿Exclusivo para Afiliados?</label>
                    </div>
                    <button type="submit" class="lucille-button-solid">Subir archivo</button>
                </form>
            </div>

            <div class="border border-white/10 bg-[#10161b] p-8">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Biblioteca</h2>
                    <a href="{{ route('talents.dashboard') }}" class="lucille-button">Panel</a>
                </div>
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    @forelse ($media as $item)
                        <div class="border border-[#2b2b2b] bg-[#151515] p-4">
                            <div class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">{{ $item->title ?: $item->filename }}</div>
                            <div class="mt-1 text-xs uppercase tracking-[.12em] text-[#7b7b7b]">
                                {{ $item->type }} · {{ number_format($item->size / 1024, 1) }} KB
                                @if ($item->is_exclusive)
                                    · <span class="text-[var(--lucille-accent)] font-semibold">Exclusivo</span>
                                @endif
                            </div>
                            @if ($item->description)
                                <p class="mt-3 text-sm text-[#7b7b7b]">{{ $item->description }}</p>
                            @endif
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a href="{{ $item->url }}" target="_blank" rel="noreferrer" class="lucille-button">Abrir</a>
                                <form action="{{ route('talents.media.destroy', ['id' => $item->id]) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="lucille-button-solid">Eliminar</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-[#7b7b7b]">Todavía no has subido contenido.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
</x-layouts.talent>
