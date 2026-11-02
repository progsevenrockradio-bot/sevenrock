@php $admin = $themeAppearance['admin_texts']; @endphp
<x-layouts.admin :title="'Nueva noticia - '.$themeSettings->site_name">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <a href="{{ route('admin.posts.index') }}" class="inline-flex items-center gap-1.5 text-xs text-gray-400 hover:text-white transition-colors mb-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Volver a noticias</span>
            </a>
            <h1 class="font-display text-2xl sm:text-3xl uppercase tracking-wider text-[#dcdcdc]">Nueva noticia</h1>
            <p class="mt-1 text-sm text-gray-400">Crea un nuevo artículo o noticia para la web.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-rose-500/20 bg-rose-500/10 p-4 text-sm text-rose-300">
            <div class="font-medium mb-1">Por favor revisa los siguientes errores:</div>
            <ul class="list-disc list-inside space-y-1 text-xs text-rose-200">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.posts.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @include('admin.posts._form', ['post' => $post])
    </form>
</x-layouts.admin>
