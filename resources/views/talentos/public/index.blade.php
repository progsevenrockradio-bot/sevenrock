<x-layouts.site :title="'Seven Rock Radio - Talentos'" description="Descubre nuevos talentos musicales en Seven Rock Radio. Bandas independientes, artistas emergentes y musica original.">
    <section class="mx-auto max-w-7xl px-5 py-16">
        <div class="mb-8 border border-white/10 bg-[#10161b] p-8">
            <div class="font-display text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Muro del Rock</div>
            <h1 class="mt-2 font-display text-4xl uppercase tracking-[.12em] text-white">Muro del Rock</h1>
            <p class="mt-3 max-w-3xl text-sm text-[#8b8b8b]">Todas las bandas ordenadas por actividad — las más activas primero. Interactúa con ellas, descubre su música y sígueles la pista.</p>
        </div>

        <form method="GET" action="{{ route('talents.explore') }}" class="grid gap-4 border border-white/10 bg-[#10161b] p-6 md:grid-cols-[1.5fr_1fr_auto]">
            <input type="search" name="search" value="{{ $search }}" placeholder="Buscar talento" class="lucille-product-field w-full">
            <select name="plan" class="lucille-product-field w-full">
                <option value="">Todos los planes</option>
                @foreach ($plans as $key => $plan)
                    <option value="{{ $key }}" @selected($selectedPlan === $key)>{{ $plan['label'] ?? ucfirst($key) }}</option>
                @endforeach
            </select>
            <button type="submit" class="lucille-button-solid">Filtrar</button>
        </form>

        <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($talents as $talent)
                <a href="{{ route('talents.show', ['bandName' => $talent->band_name]) }}" class="group border border-white/10 bg-[#10161b] p-5 transition hover:border-white/25 hover:translate-y-[-2px]">
                    <div class="flex items-start gap-4">
                        <div class="h-20 w-20 shrink-0 overflow-hidden border border-white/10 bg-[#151515]">
                            @if ($talent->logoUrl())
                                <img src="{{ $talent->logoUrl() }}" alt="{{ $talent->band_name }}" class="h-full w-full object-cover" loading="lazy">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Sin logo</div>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-display text-xl uppercase tracking-[.12em] text-white">{{ $talent->band_name }}</h2>
                                @if ($talent->is_featured)
                                    <span class="border border-[#d4af37] bg-[#d4af37] px-2 py-1 text-[10px] font-bold uppercase tracking-[.18em] text-black">Destacado</span>
                                @endif
                            </div>
                            <div class="mt-1 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ ucfirst($talent->plan) }} · {{ ucfirst($talent->subscription_status) }}</div>
                            <p class="mt-3 line-clamp-3 text-sm text-[#8b8b8b]">{{ $talent->bio ?: 'Sin biografía aún.' }}</p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center justify-between text-xs uppercase tracking-[.18em] text-[#9d9d9d]">
                        <span>{{ $talent->media_count }} archivos</span>
                        <span>Interacciones {{ $talent->interacts }}</span>
                    </div>
                </a>
            @empty
                <div class="col-span-full border border-white/10 bg-[#10161b] p-8 text-sm text-[#8b8b8b]">No hay talentos publicados todavía.</div>
            @endforelse
        </div>

        <div class="mt-8">
            {{ $talents->links() }}
        </div>
    </section>
</x-layouts.site>
