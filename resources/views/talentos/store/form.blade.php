@php
    $action = $action ?? '#';
    $method = $method ?? 'POST';
    $submitLabel = $submitLabel ?? 'Guardar';
    $product = $product ?? new \App\Models\Product();
    $isEdit = (bool) ($isEdit ?? false);
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif

    <div class="rounded border border-white/10 bg-[rgba(255,255,255,.03)] px-4 py-3 text-sm text-[#c9c9c9]">
        <strong class="text-white">Aviso legal:</strong>
        Seven Rock Radio no procesa pagos ni gestiona transacciones. Las ventas se realizan directamente entre el comprador y la banda a través de su enlace de pago externo.
    </div>

    <div class="grid gap-6 lg:grid-cols-[1.2fr_.8fr]">
        <div class="space-y-5 border border-white/10 bg-[#10161b] p-6">
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Nombre del producto</label>
                <input name="title" value="{{ old('title', $product->title) }}" class="lucille-product-field w-full" required>
            </div>

            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Descripción</label>
                <textarea name="description" rows="6" class="lucille-product-field w-full">{{ old('description', $product->description) }}</textarea>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Precio</label>
                    <input type="number" name="price" step="0.01" min="0" value="{{ old('price', $product->price ?? 0) }}" class="lucille-product-field w-full" required>
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Stock</label>
                    <input type="number" name="stock" min="0" value="{{ old('stock', $product->stock) }}" class="lucille-product-field w-full" placeholder="Opcional">
                </div>
            </div>

            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Imagen</label>
                <input type="file" name="image_file" accept="image/*" class="lucille-product-field w-full">
                @if (! empty($product->image_url))
                    <p class="mt-2 text-xs text-[#8b8b8b]">Imagen actual: <a href="{{ $product->image_url }}" target="_blank" rel="noreferrer" class="text-white">ver archivo</a></p>
                @endif
            </div>
        </div>

        <div class="space-y-5 border border-white/10 bg-[#10161b] p-6">
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Enlace de pago externo</label>
                <input type="url" name="external_payment_url" value="{{ old('external_payment_url', $product->external_payment_url) }}" class="lucille-product-field w-full" placeholder="https://paypal.me/tubanda" required>
            </div>

            <div x-data="{ dropdownOpen: false, selectedLabelVal: '{{ old('external_payment_label', $product->external_payment_label) }}', selectedLabelText: '{{ old('external_payment_label', $product->external_payment_label) ?: 'Usar texto por defecto' }}' }">
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Etiqueta del botón</label>
                <div class="relative w-full">
                    <input type="hidden" name="external_payment_label" :value="selectedLabelVal">
                    
                    <button type="button" @click="dropdownOpen = !dropdownOpen" @click.away="dropdownOpen = false" 
                        class="lucille-product-field w-full flex items-center justify-between text-left rounded-[8px]" 
                        style="color: #dcdcdc; background-color: rgba(0, 0, 0, .22); cursor: pointer; height: 50px; border: 1px solid rgba(255,255,255,0.06); padding: 0 16px; font-size: 14px;">
                        <span x-text="selectedLabelText"></span>
                        <svg class="w-4 h-4 ml-2 transition-transform duration-200" :class="dropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="14" height="14">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    
                    <ul x-show="dropdownOpen" x-cloak x-transition.opacity 
                        class="absolute left-0 z-30 mt-1 w-full border border-white/10 bg-[#141416] rounded-[8px] py-1 shadow-2xl" 
                        style="max-height: 250px; overflow-y: auto; list-style: none; padding: 0; margin: 0;">
                        <li>
                            <button type="button" @click="selectedLabelVal = ''; selectedLabelText = 'Usar texto por defecto'; dropdownOpen = false" 
                                class="w-full text-left px-4 py-3 text-sm transition-colors duration-150"
                                :class="selectedLabelVal === '' ? 'bg-[var(--lucille-accent)] text-white' : 'text-[#dcdcdc] hover:bg-white/5'">
                                Usar texto por defecto
                            </button>
                        </li>
                        @php
                            $labels = [
                                'Comprar con PayPal',
                                'Pagar con MercadoPago',
                                'Contratar',
                                'Donar',
                                'Comprar ahora',
                            ];
                        @endphp
                        @foreach ($labels as $label)
                            <li>
                                <button type="button" @click="selectedLabelVal = '{{ $label }}'; selectedLabelText = '{{ $label }}'; dropdownOpen = false" 
                                    class="w-full text-left px-4 py-3 text-sm transition-colors duration-150"
                                    :class="selectedLabelVal === '{{ $label }}' ? 'bg-[var(--lucille-accent)] text-white' : 'text-[#dcdcdc] hover:bg-white/5'">
                                    {{ $label }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="rounded border border-white/10 bg-[rgba(255,255,255,.03)] p-4 text-sm text-[#c9c9c9]">
                <strong class="text-white">Importante:</strong>
                el botón abre en una nueva pestaña y la gestión de la venta queda fuera de Seven Rock Radio.
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="lucille-button-solid">{{ $submitLabel }}</button>
                <a href="{{ route('talentos.store.index') }}" class="lucille-button">Volver</a>
            </div>
        </div>
    </div>
</form>
