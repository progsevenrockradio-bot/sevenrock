@php $admin = $themeAppearance['admin_texts']; @endphp
<x-layouts.admin :title="'Editar comentario - '.$themeSettings->site_name">
    @if (session('status'))
        <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-6">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Editar comentario</h1>
        <p class="mt-2 text-[#7b7b7b]">
            Comentario de <strong class="text-[#dcdcdc]">{{ $comment->author_name ?: 'Anónimo' }}</strong>
            en <strong class="text-[#dcdcdc]">{{ $comment->post?->title ?: '—' }}</strong>
        </p>
    </div>

    <form action="{{ route('admin.comments.update', $comment) }}" method="POST" class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        @csrf
        @method('PUT')

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <label class="mb-2 block font-display text-xs uppercase tracking-[.18em] text-[#dcdcdc]">Nombre del autor</label>
                <input type="text" name="author_name" value="{{ old('author_name', $comment->author_name) }}" class="lucille-product-field w-full">
                @error('author_name') <p class="mt-1 text-xs text-[#c32720]">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-2 block font-display text-xs uppercase tracking-[.18em] text-[#dcdcdc]">Email del autor</label>
                <input type="email" name="author_email" value="{{ old('author_email', $comment->author_email) }}" class="lucille-product-field w-full">
                @error('author_email') <p class="mt-1 text-xs text-[#c32720]">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-6">
            <label class="mb-2 block font-display text-xs uppercase tracking-[.18em] text-[#dcdcdc]">Contenido del comentario</label>
            <textarea name="content" rows="6" class="lucille-product-field w-full">{{ old('content', $comment->content) }}</textarea>
            @error('content') <p class="mt-1 text-xs text-[#c32720]">{{ $message }}</p> @enderror
        </div>

        <div class="mt-6 flex items-center gap-3">
            <input type="hidden" name="approved" value="0">
            <input type="checkbox" name="approved" id="approved" value="1" {{ old('approved', $comment->approved) ? 'checked' : '' }} class="h-4 w-4 rounded border-[#2b2b2b] bg-[#151515] text-[#c32720] focus:ring-[#c32720]">
            <label for="approved" class="font-display text-xs uppercase tracking-[.18em] text-[#dcdcdc]">Comentario aprobado</label>
        </div>

        <div class="mt-8 flex flex-wrap gap-3">
            <button type="submit" class="lucille-button-solid">Guardar cambios</button>
            <a href="{{ route('admin.comments.index') }}" class="lucille-button">Cancelar</a>
        </div>
    </form>
</x-layouts.admin>
