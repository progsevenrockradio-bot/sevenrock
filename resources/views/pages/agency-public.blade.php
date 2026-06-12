<x-layouts.site 
    title="Seven Rock Radio - {{ $agency->name }}" 
    description="Perfil público de {{ $agency->name }} en Seven Rock Radio. Bandas representadas, lanzamientos destacados y novedades."
>
    <!-- Cabecera de la Agencia -->
    <section class="relative py-20 bg-[#0c0c0e] border-b border-white/5 overflow-hidden">
        <!-- Fondo de textura sutil -->
        <div class="absolute inset-0 bg-cover bg-center opacity-5 pointer-events-none" style="background-image: url('{{ asset('assets/lucille/guitar-1758005_1920.jpg') }}');"></div>
        
        <div class="mx-auto max-w-[1200px] px-6 relative z-10">
            <div class="flex flex-col md:flex-row gap-8 items-center text-center md:text-left">
                <!-- Logotipo de la Agencia -->
                <div class="h-32 w-32 shrink-0 border border-white/10 bg-black/60 p-3 rounded-[12px] flex items-center justify-center shadow-xl">
                    @if($agency->logo_path)
                        <img src="{{ $agency->logo_url }}" alt="{{ $agency->name }}" class="max-h-full max-w-full object-contain">
                    @else
                        <span class="font-display text-2xl text-[var(--lucille-accent)] font-bold">
                            {{ strtoupper(substr($agency->name, 0, 1)) }}
                        </span>
                    @endif
                </div>

                <!-- Detalles de la Agencia -->
                <div class="flex-1 min-w-0">
                    <span class="text-[10px] uppercase tracking-[.25em] text-[var(--lucille-accent)] font-semibold font-display">Colaborador Oficial</span>
                    <h1 class="font-display text-4xl uppercase tracking-[.12em] text-[#dcdcdc] mt-1">{{ $agency->name }}</h1>
                    
                    <div class="mt-4 flex flex-wrap justify-center md:justify-start gap-4">
                        @if($agency->website_url)
                            <a href="{{ $agency->website_url }}" target="_blank" rel="noreferrer" class="lucille-button-solid text-xs uppercase tracking-wider flex items-center gap-2">
                                🌐 Visitar Sitio Web
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Catálogo de Bandas Representadas -->
    <section class="py-16 bg-[#08080a]">
        <div class="mx-auto max-w-[1200px] px-6">
            <h2 class="font-display text-xl uppercase tracking-[.15em] text-[#dcdcdc] border-b border-white/5 pb-4 mb-10">Bandas en Catálogo</h2>

            @if($bands->isEmpty())
                <div class="border border-dashed border-white/5 rounded-[12px] py-16 text-center text-[#7b7b7b]">
                    <p class="text-sm">Esta agencia no tiene bandas registradas públicamente todavía.</p>
                </div>
            @else
                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach($bands as $band)
                        @php
                            $bandUrl = route('talents.show', $band->name);
                        @endphp
                        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.8)] p-5 flex flex-col justify-between transition-all duration-300 hover:-translate-y-2 hover:border-[var(--lucille-accent)]/40 group rounded-[12px] shadow-lg">
                            <div>
                                <!-- Logo/Imagen de la Banda -->
                                <div class="relative aspect-square overflow-hidden border border-[#2b2b2b] bg-[#111] rounded-[8px] mb-4">
                                    @if($band->logo_path)
                                        <img src="{{ \App\Support\PublicMediaUrl::normalizePublicUrl($band->logo_path) }}" alt="{{ $band->name }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy">
                                    @else
                                        <div class="absolute inset-0 flex items-center justify-center bg-white/5 border border-white/10 text-white font-bold font-display text-4xl">
                                            {{ strtoupper(substr($band->name, 0, 1)) }}
                                        </div>
                                    @endif
                                </div>

                                <!-- Información -->
                                <h3 class="font-display text-lg uppercase tracking-[.08em] text-[#dcdcdc] group-hover:text-[var(--lucille-accent)] transition-colors line-clamp-1">{{ $band->name }}</h3>
                                
                                <div class="mt-2 space-y-1 text-xs text-[#7b7b7b]">
                                    @if($band->genre)
                                        <p><span class="text-[#555]">Género:</span> <span class="text-[#8f9aa3] font-semibold">{{ $band->genre }}</span></p>
                                    @endif
                                    @if($band->country)
                                        <p><span class="text-[#555]">País:</span> <span class="text-[#dcdcdc]">{{ $band->country }}</span></p>
                                    @endif
                                </div>

                                @if($band->editorial_summary)
                                    <p class="mt-3 text-xs leading-relaxed text-[#7b7b7b] line-clamp-3 select-text">{{ $band->editorial_summary }}</p>
                                @endif
                            </div>

                            <div class="mt-6 border-t border-white/5 pt-4">
                                <a href="{{ $bandUrl }}" class="w-full text-center block lucille-button py-2 text-xs uppercase tracking-wider rounded-[6px]">
                                    Ver Ficha Banda &rarr;
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-layouts.site>
