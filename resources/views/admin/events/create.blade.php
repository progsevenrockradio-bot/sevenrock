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
            <div x-data="{ showErrors: true }" x-show="showErrors" class="fixed inset-0 z-[300] flex items-center justify-center p-4" style="display: none;" x-transition>
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/85 backdrop-blur-sm" @click="showErrors = false"></div>
                <!-- Content -->
                <div class="relative w-full max-w-md border border-[#c32720]/40 bg-[rgba(12,12,13,.98)] p-6 shadow-2xl">
                    <div class="h-1 w-full bg-[#c32720] absolute top-0 left-0"></div>
                    <div class="flex items-start gap-3 mt-2">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center border border-[#c32720] bg-[rgba(195,39,32,.12)] text-[14px] font-bold text-[#ffd0d0]">!</div>
                        <div class="flex-1">
                            <h3 class="font-display text-base uppercase tracking-wider text-[#ffaaaa]">Errores de Validación</h3>
                            <ul class="mt-3 list-disc list-inside space-y-1.5 text-xs text-[#dcdcdc]/80 leading-relaxed">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button type="button" @click="showErrors = false" class="lucille-button-solid">Cerrar</button>
                    </div>
                </div>
            </div>
        @endif

        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            @include('admin.events._form', ['event' => $event])
        </div>
    </form>
</x-layouts.admin>
