<x-layouts.talent :title="'Talentos - Mi Tienda'" :talent="$talent">
    <section class="space-y-6">
        @if (session('status'))
            <div class="border border-[#1e4d2b] bg-[rgba(16,64,30,.18)] px-4 py-3 text-sm text-[#b8e6c3]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3 border border-white/10 bg-[#10161b] p-6">
            <div>
                <div class="font-display text-xs uppercase tracking-[.18em] text-[#8f9aa3]">Mi escaparate</div>
                <h1 class="mt-2 font-display text-3xl uppercase tracking-[.12em] text-white">Productos de {{ $talent->band_name }}</h1>
                <p class="mt-2 text-sm text-[#9aa7b1]">Gestiona aquí los productos que se muestran en tu perfil público.</p>
            </div>

            <a href="{{ route('talentos.store.create') }}" class="lucille-button-solid">Nuevo producto</a>
        </div>

        <div class="rounded border border-white/10 bg-[rgba(255,255,255,.03)] px-4 py-3 text-sm text-[#c9c9c9]">
            <strong class="text-white">Aviso legal:</strong>
            Seven Rock Radio no procesa pagos ni gestiona transacciones. Las ventas se realizan directamente entre el comprador y la banda a través de su enlace de pago externo.
        </div>

        <div class="overflow-x-auto border border-white/10 bg-[#10161b]">
            <table class="min-w-full divide-y divide-white/10 text-left text-sm">
                <thead class="bg-black/20 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">
                    <tr>
                        <th class="px-4 py-3">Producto</th>
                        <th class="px-4 py-3">Precio</th>
                        <th class="px-4 py-3">Enlace externo</th>
                        <th class="px-4 py-3">Stock</th>
                        <th class="px-4 py-3">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @forelse ($products as $product)
                        <tr class="text-[#d8d8d8]">
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $product->image_url }}" alt="{{ $product->title }}" loading="lazy" class="h-14 w-14 border border-white/10 object-cover">
                                    <div>
                                        <div class="font-semibold text-white">{{ $product->title }}</div>
                                        <div class="text-xs text-[#8b8b8b]">{{ $product->external_payment_label ?: 'Comprar' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">€{{ number_format((float) $product->price, 2) }}</td>
                            <td class="px-4 py-4">
                                <a href="{{ $product->external_payment_url }}" target="_blank" rel="nofollow noopener" class="text-[#dcdcdc] transition hover:text-lucille-accent">
                                    {{ $product->external_payment_url }}
                                </a>
                            </td>
                            <td class="px-4 py-4">{{ $product->stock ?? 'N/D' }}</td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('talentos.store.edit', $product->id) }}" class="lucille-button">Editar</a>
                                    <form method="POST" action="{{ route('talentos.store.destroy', $product->id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="lucille-button-solid" data-confirm="¿Eliminar este producto?" data-confirm-title="Eliminar producto" data-confirm-action="Eliminar" data-confirm-tone="danger">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-[#8b8b8b]">Todavía no has creado productos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.talent>
