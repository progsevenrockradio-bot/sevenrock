<x-layouts.admin :title="'Nuevo perfil de artista'">
    <div class="mx-auto max-w-5xl space-y-5 font-sans">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Bandas y comunidad · Catálogo editorial del reproductor</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">Nuevo perfil de artista</h1>
                <p class="mt-1 text-xs text-gray-400">Registra resúmenes biográficos, país, género e imágenes para la ficha del reproductor de radio.</p>
            </div>
            <a href="{{ route('admin.radio-artists.index') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                <span>Volver a perfiles</span>
            </a>
        </header>

        <form action="{{ route('admin.radio-artists.store') }}" method="POST" class="space-y-5">
            @csrf
            @include('admin.radio-artists._form')
        </form>
    </div>
</x-layouts.admin>
