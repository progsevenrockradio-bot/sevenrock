@php $admin = $themeAppearance['admin_texts']; @endphp
<x-layouts.admin :title="'Editar evento'.' - '.$themeSettings->site_name">
    <form action="{{ route('admin.events.update', $event) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a href="{{ route('admin.events.index') }}" class="inline-flex items-center gap-1.5 text-xs text-gray-400 hover:text-white transition-colors mb-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>Volver a eventos</span>
                </a>
                <h1 class="font-display text-2xl sm:text-3xl uppercase tracking-wider text-[#dcdcdc]">Editar Evento</h1>
                <p class="mt-1 text-sm text-gray-400">Actualiza la información del evento, cartel y enlace de venta de entradas.</p>
            </div>
            <div class="flex items-center gap-3">
                <button type="submit" class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold tracking-wide shadow-md shadow-red-950/40 transition-all focus:outline-none focus:ring-2 focus:ring-[#c32720]/50">
                    Guardar Cambios
                </button>
            </div>
        </div>

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-rose-500/20 bg-rose-500/10 p-4 text-sm text-rose-300">
                <div class="font-medium mb-1">Por favor revisa los errores en el formulario:</div>
                <ul class="list-disc list-inside space-y-1 text-xs text-rose-200">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-xl border border-white/10 bg-[#101012] p-5 sm:p-6 shadow-xl">
            @include('admin.events._form', ['event' => $event])
        </div>
    </form>
</x-layouts.admin>
