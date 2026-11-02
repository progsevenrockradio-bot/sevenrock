@php
    $isEdit = $image->exists;
    $admin = $themeAppearance['admin_texts'];
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Orden de posición</label>
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $image->sort_order ?? 0) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="0">
    </div>

    <div>
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Pie de foto / Descripción</label>
        <input name="caption" value="{{ old('caption', $image->caption) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="Ej. Concierto en La Riviera, Madrid">
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Ruta o URL de la fotografía</label>
        <input name="image" value="{{ old('image', $image->image) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="https://... o ruta relativa">
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Subir archivo de fotografía</label>
        <input type="file" name="image_file" class="block w-full text-xs text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-white/10 file:text-gray-200 hover:file:bg-white/20">
    </div>
</div>

<div class="mt-6 pt-5 border-t border-white/10 flex flex-wrap gap-3">
    <button type="submit" class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold tracking-wide shadow-md shadow-red-950/40 transition-all">
        {{ $isEdit ? 'Guardar Fotografía' : 'Añadir Fotografía' }}
    </button>
    <a href="{{ route('admin.gallery.index') }}" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all">
        Volver a Galería
    </a>
</div>
