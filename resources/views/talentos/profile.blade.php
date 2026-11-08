<x-layouts.talent :title="'Talentos - Perfil'">
    <section class="space-y-6">
        @if (session('status'))
            <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 border border-red-500 bg-red-500/20 px-4 py-3 text-sm text-red-200">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="border border-white/10 bg-[#10161b] p-4 sm:p-8">
            <h1 class="font-display text-2xl sm:text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Editar perfil</h1>

            <form action="{{ route('talents.profile.update') }}" method="POST" enctype="multipart/form-data" class="mt-8 space-y-5">
                @csrf
                @method('PUT')
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Nombre de banda</label>
                    <input name="name" value="{{ old('name', $talent->band_name) }}" class="lucille-product-field w-full">
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Bio</label>
                    <textarea name="bio" rows="6" class="lucille-product-field w-full">{{ old('bio', $talent->bio) }}</textarea>
                </div>
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Instagram</label>
                        <input name="instagram_url" value="{{ old('instagram_url', $talent->instagram_url) }}" class="lucille-product-field w-full">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">YouTube</label>
                        <input name="youtube_url" value="{{ old('youtube_url', $talent->youtube_url) }}" class="lucille-product-field w-full">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">TikTok</label>
                        <input name="tiktok_url" value="{{ old('tiktok_url', $talent->tiktok_url) }}" class="lucille-product-field w-full">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Spotify</label>
                        <input name="spotify_url" value="{{ old('spotify_url', $talent->spotify_url) }}" class="lucille-product-field w-full">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Sitio web</label>
                        <input name="website_url" value="{{ old('website_url', $talent->website_url) }}" class="lucille-product-field w-full">
                    </div>
                </div>

                {{-- Enlaces externos propios de apoyo y compra directa --}}
                <div class="border-t border-white/10 pt-6 space-y-4">
                    <div>
                        <h2 class="font-display text-lg uppercase tracking-[.12em] text-[#dcdcdc]">Enlaces de apoyo y venta directa</h2>
                        <p class="mt-1 text-xs text-[#8b8b8b] leading-relaxed">
                            Configura enlaces directos de tu banda para que tus seguidores puedan apoyarte económicamente o adquirir tu música y merchandising oficial en plataformas externas (PayPal.Me, Ko-fi, Bandcamp, tienda oficial, etc.).
                        </p>
                        <div class="mt-2.5 rounded-sm border border-white/5 bg-[#141417] p-3 text-[11px] text-[#a0a0a0] leading-relaxed space-y-1">
                            <p class="font-semibold text-white">ℹ️ Plataforma externa y pagos directos:</p>
                            <p>El pago se realiza directamente en la plataforma externa. Seven Rock Radio no intermedia, no gestiona ni recibe estos fondos.</p>
                            <p class="text-[#7b7b7b]">Los campos son opcionales: deja en blanco cualquier enlace que no desees mostrar en tu perfil público.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#dcdcdc]">
                                Apoyar a la banda <span class="text-[#7b7b7b] font-normal lowercase">(PayPal.Me, Ko-fi...)</span>
                            </label>
                            <input
                                type="url"
                                name="payment_links[support]"
                                value="{{ old('payment_links.support', $talent->supportPaymentUrl()) }}"
                                placeholder="https://paypal.me/tubanda o https://ko-fi.com/tubanda"
                                class="lucille-product-field w-full"
                            >
                            <p class="mt-1 text-[11px] text-[#7b7b7b]">Botón público: «Apoyar a la banda». Requiere HTTPS.</p>
                        </div>

                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#dcdcdc]">
                                Comprar música / Merch <span class="text-[#7b7b7b] font-normal lowercase">(Bandcamp, tienda oficial...)</span>
                            </label>
                            <input
                                type="url"
                                name="payment_links[merch]"
                                value="{{ old('payment_links.merch', $talent->merchPaymentUrl()) }}"
                                placeholder="https://tubanda.bandcamp.com o https://tiendaoficial.com"
                                class="lucille-product-field w-full"
                            >
                            <p class="mt-1 text-[11px] text-[#7b7b7b]">Botón público: «Comprar música / Merch». Requiere HTTPS.</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Logo</label>
                    <input type="file" name="logo" class="lucille-product-field w-full">
                    @if ($talent->logoUrl())
                        <div class="mt-3 text-sm text-[#7b7b7b]">Logo actual: <a href="{{ $talent->logoUrl() }}" target="_blank" rel="noreferrer" class="text-[#dcdcdc] underline break-words">abrir archivo</a></div>
                    @endif
                </div>
                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="submit" class="lucille-button-solid w-full sm:w-auto text-center">Guardar</button>
                    <a href="{{ route('talents.dashboard') }}" class="lucille-button w-full sm:w-auto text-center">Volver</a>
                </div>
            </form>
        </div>
    </section>
</x-layouts.talent>
