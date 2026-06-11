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
                    <div x-data="{ dropdownOpen: false, selectedType: '{{ old('type', 'photo') }}', selectedLabel: '{{ old('type') === 'mp3' ? 'MP3' : (old('type') === 'document' ? 'Documento' : (old('type') === 'video' ? 'Video' : 'Foto')) }}' }">
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Tipo</label>
                        <div class="relative w-full">
                            <input type="hidden" name="type" :value="selectedType">
                            
                            <button type="button" @click="dropdownOpen = !dropdownOpen" @click.away="dropdownOpen = false" 
                                class="lucille-product-field w-full flex items-center justify-between text-left rounded-[8px]" 
                                style="color: #dcdcdc; background-color: rgba(0, 0, 0, .22); cursor: pointer; height: 50px; border: 1px solid rgba(255,255,255,0.06); padding: 0 16px; font-size: 14px;">
                                <span x-text="selectedLabel"></span>
                                <svg class="w-4 h-4 ml-2 transition-transform duration-200" :class="dropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="14" height="14">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            
                            <ul x-show="dropdownOpen" x-cloak x-transition.opacity 
                                class="absolute left-0 z-30 mt-1 w-full border border-white/10 bg-[#141416] rounded-[8px] py-1 shadow-2xl" 
                                style="max-height: 250px; overflow-y: auto; list-style: none; padding: 0; margin: 0;">
                                <li>
                                    <button type="button" @click="selectedType = 'photo'; selectedLabel = 'Foto'; dropdownOpen = false" 
                                        class="w-full text-left px-4 py-3 text-sm transition-colors duration-150"
                                        :class="selectedType === 'photo' ? 'bg-[var(--lucille-accent)] text-white' : 'text-[#dcdcdc] hover:bg-white/5'">
                                        Foto
                                    </button>
                                </li>
                                <li>
                                    <button type="button" @click="selectedType = 'mp3'; selectedLabel = 'MP3'; dropdownOpen = false" 
                                        class="w-full text-left px-4 py-3 text-sm transition-colors duration-150"
                                        :class="selectedType === 'mp3' ? 'bg-[var(--lucille-accent)] text-white' : 'text-[#dcdcdc] hover:bg-white/5'">
                                        MP3
                                    </button>
                                </li>
                                <li>
                                    <button type="button" @click="selectedType = 'document'; selectedLabel = 'Documento'; dropdownOpen = false" 
                                        class="w-full text-left px-4 py-3 text-sm transition-colors duration-150"
                                        :class="selectedType === 'document' ? 'bg-[var(--lucille-accent)] text-white' : 'text-[#dcdcdc] hover:bg-white/5'">
                                        Documento
                                    </button>
                                </li>
                                <li>
                                    <button type="button" @click="selectedType = 'video'; selectedLabel = 'Video'; dropdownOpen = false" 
                                        class="w-full text-left px-4 py-3 text-sm transition-colors duration-150"
                                        :class="selectedType === 'video' ? 'bg-[var(--lucille-accent)] text-white' : 'text-[#dcdcdc] hover:bg-white/5'">
                                        Video
                                    </button>
                                </li>
                            </ul>
                        </div>
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
