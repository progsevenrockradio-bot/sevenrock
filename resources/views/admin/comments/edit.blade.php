@php $admin = $themeAppearance['admin_texts']; @endphp
<x-layouts.admin :title="'Editar comentario - '.$themeSettings->site_name">
    @if (session('status'))
        <div class="mb-6 flex items-center justify-between rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            <span>{{ session('status') }}</span>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-400 hover:text-emerald-200 text-xs">✕</button>
        </div>
    @endif

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <a href="{{ route('admin.comments.index') }}" class="inline-flex items-center gap-1.5 text-xs text-gray-400 hover:text-white transition-colors mb-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Volver a comentarios</span>
            </a>
            <h1 class="font-display text-2xl sm:text-3xl uppercase tracking-wider text-[#dcdcdc]">Editar comentario</h1>
            <p class="mt-1 text-sm text-gray-400">
                Comentario de <strong class="text-white">{{ $comment->author_name ?: 'Anónimo' }}</strong>
                en <strong class="text-white">{{ $comment->post?->title ?: '—' }}</strong>
            </p>
        </div>
    </div>

    <form action="{{ route('admin.comments.update', $comment) }}" method="POST" class="rounded-xl border border-white/10 bg-[#101012] p-6 shadow-xl space-y-6">
        @csrf
        @method('PUT')

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Nombre del autor</label>
                <input type="text" name="author_name" value="{{ old('author_name', $comment->author_name) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
                @error('author_name') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Email del autor</label>
                <input type="email" name="author_email" value="{{ old('author_email', $comment->author_email) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
                @error('author_email') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Contenido del comentario</label>
            <textarea name="content" rows="6" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">{{ old('content', $comment->content) }}</textarea>
            @error('content') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3 pt-2">
            <input type="hidden" name="approved" value="0">
            <input type="checkbox" name="approved" id="approved" value="1" {{ old('approved', $comment->approved) ? 'checked' : '' }} class="h-4 w-4 rounded border-white/20 bg-black/40 text-[#c32720] focus:ring-[#c32720]">
            <label for="approved" class="text-sm font-medium text-gray-200 cursor-pointer">Comentario aprobado (Visible públicamente)</label>
        </div>

        <div class="pt-4 border-t border-white/10 flex flex-wrap gap-3">
            <button type="submit" class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold tracking-wide shadow-md shadow-red-950/40 transition-all">
                Guardar cambios
            </button>
            <a href="{{ route('admin.comments.index') }}" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all">
                Cancelar
            </a>
        </div>
    </form>
</x-layouts.admin>
