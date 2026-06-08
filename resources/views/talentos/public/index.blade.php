<x-layouts.site :title="'Seven Rock Radio - Talentos'" description="Descubre nuevos talentos musicales en Seven Rock Radio. Bandas independientes, artistas emergentes y musica original.">
    <section class="mx-auto max-w-7xl px-5 py-16">
        <!-- Section Header -->
        <div class="mb-8 border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-8 shadow-xl">
            <div class="font-display text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Muro del Rock</div>
            <h1 class="mt-2 font-display text-4xl uppercase tracking-[.12em] text-white">Muro del Rock</h1>
            <p class="mt-3 max-w-3xl text-sm text-[#8b8b8b]">Todas las bandas ordenadas por actividad — las más activas primero. Interactúa con ellas, descubre su música y sígueles la pista.</p>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="{{ route('talents.explore') }}" class="grid gap-4 border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-6 md:grid-cols-[1.5fr_1fr_auto] shadow-lg">
            <input type="search" name="search" value="{{ $search }}" placeholder="Buscar talento" class="lucille-product-field w-full">
            <select name="plan" class="lucille-product-field w-full">
                <option value="">Todos los planes</option>
                @foreach ($plans as $key => $plan)
                    <option value="{{ $key }}" @selected($selectedPlan === $key)>{{ $plan['label'] ?? ucfirst($key) }}</option>
                @endforeach
            </select>
            <button type="submit" class="lucille-button-solid">Filtrar</button>
        </form>

        <!-- Talents Cards Grid -->
        <div class="mt-8 grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($talents as $talent)
                <a href="{{ route('talents.show', ['bandName' => $talent->band_name]) }}" class="group border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-6 transition-all duration-300 hover:border-white/20 hover:bg-white/[0.04] hover:-translate-y-1 shadow-lg flex flex-col justify-between">
                    <div class="flex items-start gap-4">
                        <div class="h-20 w-20 shrink-0 overflow-hidden rounded-[12px] border border-white/10 bg-black/30 relative">
                            @if ($talent->logoUrl())
                                <img src="{{ $talent->logoUrl() }}" alt="{{ $talent->band_name }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy" width="80" height="80">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-[10px] uppercase tracking-[.18em] text-[#7b7b7b] text-center">Sin logo</div>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-display text-xl uppercase tracking-[.12em] text-white truncate group-hover:text-[var(--lucille-accent)] transition-colors">{{ $talent->band_name }}</h2>
                                @if ($talent->is_featured)
                                    <span class="border border-[#d4af37]/30 bg-[#d4af37]/10 px-2 py-0.5 text-[9px] font-bold uppercase tracking-[.15em] text-[#d4af37] rounded-sm shadow-[0_0_10px_rgba(212,175,55,0.15)]">Destacado</span>
                                @endif
                            </div>
                            <div class="mt-1 text-[10px] uppercase tracking-[.18em] text-gray-500">{{ ucfirst($talent->plan) }}</div>
                            <p class="mt-3 line-clamp-3 text-sm text-gray-400 leading-relaxed font-sans">{{ $talent->bio ?: 'Este artista aún no ha escrito su biografía.' }}</p>
                        </div>
                    </div>
                    <div class="mt-5 pt-4 border-t border-white/5 flex items-center justify-between text-[10px] uppercase tracking-[.18em] text-gray-400 font-mono">
                        <span>{{ $talent->media_count }} archivos</span>
                        <span>{{ $talent->interacts }} interacciones</span>
                    </div>
                </a>
            @empty
                <div class="col-span-full border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-8 text-sm text-gray-500 text-center shadow-lg">No hay talentos publicados todavía.</div>
            @endforelse
        </div>

        <div class="mt-8">
            {{ $talents->links() }}
        </div>
    </section>
</x-layouts.site>
