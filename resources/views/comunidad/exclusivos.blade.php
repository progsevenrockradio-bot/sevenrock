<x-layouts.site :title="'Club de Fans - Descargas Exclusivas'">
    <section class="mx-auto max-w-[1200px] px-5 py-12" style="padding-top: 150px;">
        
        {{-- Cabecera de la Sección --}}
        <div class="mb-10 flex flex-wrap items-center justify-between gap-4 border-b border-white/5 pb-6">
            <div>
                <span class="text-[10px] uppercase tracking-[.25em] text-[var(--lucille-accent)] font-semibold font-display">Zona de Descargas</span>
                <h1 class="font-display text-4xl uppercase tracking-[.12em] text-[#dcdcdc] mt-1">Material Exclusivo</h1>
                <p class="mt-2 text-sm text-[#7b7b7b]">Escucha canciones inéditas, directos pirateados, maquetas y fotos que las bandas comparten solo para afiliados de Seven Rock Radio.</p>
            </div>
            <div>
                <a href="{{ route('comunidad.muro') }}" class="lucille-button rounded-[8px] px-5 py-2.5 text-xs font-display uppercase tracking-wider flex items-center gap-2">
                    💬 Volver al Muro
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="mb-6 border border-green-500/20 bg-green-500/5 rounded-[12px] p-4 text-xs text-green-400">
                {{ session('status') }}
            </div>
        @endif

        {{-- Grid de Medios Exclusivos --}}
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($media as $item)
                @php
                    $normalizedUrl = \App\Support\PublicMediaUrl::normalizePublicUrl($item->url);
                @endphp
                <div class="border border-white/5 bg-white/[0.01] rounded-[16px] p-5 flex flex-col justify-between hover:border-white/10 hover:bg-white/[0.02] transition-all duration-300">
                    <div>
                        {{-- Cabecera del Item --}}
                        <div class="flex items-center justify-between gap-3 border-b border-white/5 pb-3">
                            <div class="min-w-0">
                                <span class="text-[9px] uppercase tracking-wider text-[var(--lucille-accent)] font-semibold font-display">
                                    {{ $item->talent->band_name }}
                                </span>
                                <h3 class="font-display text-sm font-bold text-white tracking-wide truncate mt-0.5" title="{{ $item->title ?: $item->filename }}">
                                    {{ $item->title ?: $item->filename }}
                                </h3>
                            </div>
                            <span class="rounded bg-[#1b1b1e] border border-white/10 px-2 py-0.5 text-[8px] uppercase tracking-wider text-gray-400 shrink-0 font-mono">
                                {{ strtoupper($item->type) }}
                            </span>
                        </div>

                        {{-- Descripción si existe --}}
                        @if ($item->description)
                            <p class="mt-3 text-xs leading-relaxed text-[#7b7b7b] line-clamp-3">{{ $item->description }}</p>
                        @endif

                        {{-- Reproductor / Visor según tipo --}}
                        <div class="mt-4">
                            @if ($item->type === 'mp3')
                                <div class="bg-black/40 rounded-[12px] p-3 border border-white/5">
                                    <audio src="{{ $normalizedUrl }}" controls class="w-full h-8 accent-[#c32720]" controlsList="nodownload"></audio>
                                </div>
                            @elseif ($item->type === 'photo')
                                <div class="overflow-hidden rounded-[12px] border border-white/5 bg-black/20 aspect-video">
                                    <a href="{{ $normalizedUrl }}" target="_blank" rel="noreferrer" class="block h-full w-full">
                                        <img src="{{ $normalizedUrl }}" alt="{{ $item->title }}" class="h-full w-full object-cover hover:scale-105 transition-transform duration-500" loading="lazy">
                                    </a>
                                </div>
                            @elseif ($item->type === 'video')
                                <div class="overflow-hidden rounded-[12px] border border-white/5 bg-black aspect-video">
                                    <video src="{{ $normalizedUrl }}" controls class="w-full h-full" controlsList="nodownload"></video>
                                </div>
                            @elseif ($item->type === 'document')
                                <div class="flex items-center gap-3 p-3 bg-white/[0.02] border border-white/5 rounded-[12px]">
                                    <span class="text-2xl shrink-0">📄</span>
                                    <div class="min-w-0 flex-1">
                                        <span class="text-[10px] text-gray-500 font-mono block truncate">{{ $item->filename }}</span>
                                        <span class="text-[9px] text-[#7b7b7b] font-mono">{{ round($item->size / 1024 / 1024, 2) }} MB</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Enlace de Descarga / Meta al final --}}
                    <div class="mt-5 border-t border-white/5 pt-3 flex items-center justify-between gap-3">
                        <span class="text-[9px] text-gray-500 font-mono">Subido {{ $item->created_at->diffForHumans() }}</span>
                        @if (in_array($item->type, ['mp3', 'document', 'video'], true))
                            <a 
                                href="{{ $normalizedUrl }}" 
                                download="{{ $item->filename }}"
                                target="_blank"
                                class="text-[10px] uppercase font-display tracking-wider text-white hover:text-[var(--lucille-accent)] transition-colors flex items-center gap-1.5"
                            >
                                💾 Descargar &rarr;
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-span-full border border-dashed border-white/10 rounded-[16px] p-16 text-center text-[#7b7b7b]">
                    <p class="text-sm">Aún no se ha subido material exclusivo. ¡Vuelve pronto!</p>
                </div>
            @endforelse
        </div>

        {{-- Paginación --}}
        <div class="mt-8 font-mono text-xs">
            {{ $media->links() }}
        </div>
    </section>
</x-layouts.site>
