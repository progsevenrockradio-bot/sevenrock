<x-layouts.site :title="'Explorar Talentos'">
    <section class="mx-auto max-w-6xl px-5 pt-10">
        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Explorar Talentos</h1>
            <p class="mt-3 max-w-3xl text-sm text-[#7b7b7b]">Bandas independientes con perfil propio y planes mensuales.</p>
        </div>

        <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($talents as $talent)
                <article class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-5">
                    <div class="flex items-start gap-4">
                        <div class="h-16 w-16 shrink-0 overflow-hidden border border-[#2b2b2b] bg-[#151515]">
                            @if ($talent->logoUrl())
                                <img src="{{ $talent->logoUrl() }}" alt="{{ $talent->band_name }}" class="h-full w-full object-cover">
                            @endif
                        </div>
                        <div class="min-w-0">
                            <div class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">{{ $talent->band_name }}</div>
                            <div class="mt-1 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ ucfirst($talent->plan) }} · {{ ucfirst($talent->subscription_status) }}</div>
                        </div>
                    </div>
                    <p class="mt-4 text-sm text-[#7b7b7b]">{{ $talent->bio ?: 'No biography yet.' }}</p>
                    <div class="mt-4 text-xs uppercase tracking-[.18em] text-[#9d9d9d]">{{ $talent->media_count }} media items</div>
                </article>
            @empty
                <div class="text-sm text-[#7b7b7b]">No talents published yet.</div>
            @endforelse
        </div>

        <div class="mt-8">
            {{ $talents->links() }}
        </div>
    </section>
</x-layouts.site>
