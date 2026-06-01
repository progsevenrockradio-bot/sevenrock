<x-layouts.admin :title="'Publicados - '.config('app.name')">
    <section class="border border-[#2b2b2b] bg-[linear-gradient(180deg,rgba(16,16,18,.96),rgba(12,12,13,.92))] p-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-[10px] uppercase tracking-[.28em] text-[#8b8b8b]">Consulta rápida</p>
                <h1 class="mt-3 font-display text-4xl uppercase tracking-[.12em] text-[#f0f0f0]">Últimos publicados</h1>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-[#b4b4b4]">
                    Esta vista muestra solo los episodios ya publicados. Sirve para consulta rápida y también para imprimir.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.podcast-uploads.index') }}" class="lucille-button">Volver a uploads</a>
                <a href="{{ route('admin.podcast-uploads.published.print') }}" target="_blank" class="lucille-button-solid">Imprimir publicados</a>
            </div>
        </div>
    </section>

    <section class="mt-8 border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Últimos 6 publicados</h2>
                <p class="mt-2 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Solo episodios con entrega verificada.</p>
            </div>
            <div class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">
                Total visible: {{ $recentPublishedUploads->count() }}
            </div>
        </div>

        <div class="mt-6">
            @include('admin.podcast-uploads.partials.recent-uploads', ['recentUploads' => $recentPublishedUploads])
        </div>
    </section>
</x-layouts.admin>
