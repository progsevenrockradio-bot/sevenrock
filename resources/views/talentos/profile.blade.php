<x-layouts.site :title="'Talentos - Perfil'">
    <section class="mx-auto max-w-4xl px-5 pt-10">
        @if (session('status'))
            <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
                {{ session('status') }}
            </div>
        @endif

        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Editar perfil</h1>

            <form action="{{ route('talents.profile.update') }}" method="POST" enctype="multipart/form-data" class="mt-8 space-y-5">
                @csrf
                @method('PUT')
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Band name</label>
                    <input name="band_name" value="{{ old('band_name', $talent->band_name) }}" class="lucille-product-field w-full">
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Bio</label>
                    <textarea name="bio" rows="6" class="lucille-product-field w-full">{{ old('bio', $talent->bio) }}</textarea>
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Logo</label>
                    <input type="file" name="logo" class="lucille-product-field w-full">
                    @if ($talent->logoUrl())
                        <div class="mt-3 text-sm text-[#7b7b7b]">Current logo: <a href="{{ $talent->logoUrl() }}" target="_blank" rel="noreferrer" class="text-[#dcdcdc] underline">open file</a></div>
                    @endif
                </div>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="lucille-button-solid">Guardar</button>
                    <a href="{{ route('talents.dashboard') }}" class="lucille-button">Volver</a>
                </div>
            </form>
        </div>
    </section>
</x-layouts.site>
