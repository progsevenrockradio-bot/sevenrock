<x-layouts.talent :title="'Talentos - Editar producto'" :talent="$talent">
    <section class="space-y-6">
        <div class="border border-white/10 bg-[#10161b] p-6">
            <div class="font-display text-xs uppercase tracking-[.18em] text-[#8f9aa3]">Mi tienda</div>
            <h1 class="mt-2 font-display text-3xl uppercase tracking-[.12em] text-white">Editar producto</h1>
        </div>

        @include('talentos.store.form', [
            'action' => route('talentos.store.update', $product->id),
            'method' => 'POST',
            'submitLabel' => 'Actualizar producto',
            'product' => $product,
            'isEdit' => true,
        ])
    </section>
</x-layouts.talent>
