<x-layouts.admin :title="'Nuevo Lanzamiento - '.$themeSettings->site_name">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <a href="{{ route('admin.new-releases.index') }}" class="inline-flex items-center gap-1.5 text-xs text-gray-400 hover:text-white transition-colors mb-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Volver a lanzamientos</span>
            </a>
            <h1 class="font-display text-2xl sm:text-3xl uppercase tracking-wider text-[#dcdcdc]">Nuevo Lanzamiento</h1>
            <p class="mt-1 text-sm text-gray-400">Crea un nuevo lanzamiento destacado para mostrarlo en la web.</p>
        </div>
    </div>

    <div class="rounded-xl border border-white/10 bg-[#101012] p-6 sm:p-8 shadow-xl">
        <form action="{{ route('admin.new-releases.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @include('admin.new-releases._form', ['newRelease' => $newRelease])
        </form>
    </div>
</x-layouts.admin>
