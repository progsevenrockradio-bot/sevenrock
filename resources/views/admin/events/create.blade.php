<x-layouts.admin :title="($themeAppearance['admin_texts']['new_event'] ?? 'New event').' - '.$themeSettings->site_name">
    @php $admin = $themeAppearance['admin_texts']; @endphp

    <form action="{{ route('admin.events.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <!-- Sticky Header Action Bar -->
        <div class="sticky top-0 z-50 -mx-6 mb-6 border-b border-[#2b2b2b] bg-[rgba(16,16,18,.96)] px-6 py-4 backdrop-blur-md flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ !empty($admin['new_event']) ? $admin['new_event'] : 'Nuevo Evento' }}</h1>
                <p class="mt-1 text-xs text-[#7b7b7b]">{{ !empty($admin['create_event_copy']) ? $admin['create_event_copy'] : 'Crea un nuevo evento para la programación.' }}</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.events.index') }}" class="lucille-button">
                    {{ !empty($admin['back_to_events']) ? $admin['back_to_events'] : 'Volver a Eventos' }}
                </a>
                <button type="submit" class="lucille-button-solid">
                    {{ !empty($admin['new_event']) ? $admin['new_event'] : 'Crear Evento' }}
                </button>
            </div>
        </div>

        @if ($errors->any())
            <div class="border border-[#c32720]/40 bg-[rgba(195,39,32,.08)] p-5 text-sm text-[#ffd0d0] mb-6">
                <div class="flex items-center gap-2 mb-3">
                    <span class="flex h-5 w-5 items-center justify-center border border-[#c32720] bg-[rgba(195,39,32,.2)] text-[10px] font-bold text-white">!</span>
                    <span class="font-display text-xs uppercase tracking-[.18em] font-semibold text-[#ffaaaa]">Por favor corrige los siguientes errores:</span>
                </div>
                <ul class="list-disc list-inside space-y-1 text-xs text-[#dcdcdc]/80">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            @include('admin.events._form', ['event' => $event])
        </div>
    </form>
</x-layouts.admin>
