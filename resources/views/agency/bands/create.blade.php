<x-layouts.agency :title="'Agencia - Registrar Banda'">
    <section class="space-y-6">
        <div class="border border-white/10 bg-[#10161b] p-8 rounded-[8px] shadow-xl">
            <h1 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Registrar Nueva Banda</h1>
            <p class="mt-2 text-sm text-[#7b7b7b]">Crea el perfil público de una banda musical y vincúlala a tu agencia.</p>

            <form action="{{ route('agency.bands.store') }}" method="POST" class="mt-8">
                @csrf
                @include('agency.bands._form')
            </form>
        </div>
    </section>
</x-layouts.agency>
