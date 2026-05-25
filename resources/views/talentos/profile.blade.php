<x-layouts.talent :title="'Talentos - Perfil'">
    <section class="space-y-6">
        @if (session('status'))
            <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
                {{ session('status') }}
            </div>
        @endif

        <div class="border border-white/10 bg-[#10161b] p-8">
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Editar perfil</h1>

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
                <div class="grid gap-5 md:grid-cols-2">
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
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Logo</label>
                    <input type="file" name="logo" class="lucille-product-field w-full">
                    @if ($talent->logoUrl())
                        <div class="mt-3 text-sm text-[#7b7b7b]">Logo actual: <a href="{{ $talent->logoUrl() }}" target="_blank" rel="noreferrer" class="text-[#dcdcdc] underline">abrir archivo</a></div>
                    @endif
                </div>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="lucille-button-solid">Guardar</button>
                    <a href="{{ route('talents.dashboard') }}" class="lucille-button">Volver</a>
                </div>
            </form>
        </div>
    </section>
</x-layouts.talent>
