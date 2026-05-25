<x-layouts.site :title="'Talentos - Media'">
    <section class="mx-auto max-w-6xl px-5 pt-10">
        @if (session('status'))
            <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[.9fr_1.1fr]">
            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Media</h1>
                <p class="mt-2 text-sm text-[#7b7b7b]">Sube fotos, MP3 o documentos al storage de Backblaze B2.</p>

                <form action="{{ route('talents.media.store') }}" method="POST" enctype="multipart/form-data" class="mt-8 space-y-5">
                    @csrf
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Type</label>
                        <select name="type" class="lucille-product-field w-full">
                            <option value="photo" @selected(old('type') === 'photo')>Photo</option>
                            <option value="mp3" @selected(old('type') === 'mp3')>MP3</option>
                            <option value="document" @selected(old('type') === 'document')>Document</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Title</label>
                        <input name="title" value="{{ old('title') }}" class="lucille-product-field w-full">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Description</label>
                        <textarea name="description" rows="4" class="lucille-product-field w-full">{{ old('description') }}</textarea>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">File</label>
                        <input type="file" name="file" class="lucille-product-field w-full">
                    </div>
                    <button type="submit" class="lucille-button-solid">Upload</button>
                </form>
            </div>

            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Library</h2>
                    <a href="{{ route('talents.dashboard') }}" class="lucille-button">Dashboard</a>
                </div>
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    @forelse ($media as $item)
                        <div class="border border-[#2b2b2b] bg-[#151515] p-4">
                            <div class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">{{ $item->title ?: $item->filename }}</div>
                            <div class="mt-1 text-xs uppercase tracking-[.12em] text-[#7b7b7b]">{{ $item->type }} · {{ number_format($item->size / 1024, 1) }} KB</div>
                            @if ($item->description)
                                <p class="mt-3 text-sm text-[#7b7b7b]">{{ $item->description }}</p>
                            @endif
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a href="{{ $item->url }}" target="_blank" rel="noreferrer" class="lucille-button">Open</a>
                                <form action="{{ route('talents.media.destroy', $item) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="lucille-button-solid">Delete</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-[#7b7b7b]">No media uploaded yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
</x-layouts.site>
