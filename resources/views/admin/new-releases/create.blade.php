<x-layouts.admin :title="'Nuevo Lanzamiento - '.$themeSettings->site_name">
    <div class="mb-6">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Nuevo Lanzamiento</h1>
        <p class="mt-2 text-[#7b7b7b]">Crea un nuevo lanzamiento destacado para mostrarlo en la web.</p>
    </div>

    <form action="{{ route('admin.new-releases.store') }}" method="POST" enctype="multipart/form-data" class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        @csrf
        @include('admin.new-releases._form', ['newRelease' => $newRelease])
    </form>
</x-layouts.admin>
