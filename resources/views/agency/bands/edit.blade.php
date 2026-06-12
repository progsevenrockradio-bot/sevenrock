<x-layouts.agency :title="'Agencia - Editar Banda'">
    <section class="space-y-6">
        <div class="border border-white/10 bg-[#10161b] p-8 rounded-[8px] shadow-xl">
            <h1 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Editar Perfil de Banda</h1>
            <p class="mt-2 text-sm text-[#7b7b7b]">Modifica la información, enlaces y biografía de la banda representadas.</p>

            <form action="{{ route('agency.bands.update', $bandProfile->id) }}" method="POST" class="mt-8">
                @csrf
                @method('PUT')
                @include('agency.bands._form')
            </form>
        </div>
    </section>
</x-layouts.agency>
