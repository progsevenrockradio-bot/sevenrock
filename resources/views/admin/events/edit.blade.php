<x-layouts.admin :title="($themeAppearance['admin_texts']['edit_event'] ?? 'Edit event').' - '.$themeSettings->site_name">
    @php $admin = $themeAppearance['admin_texts']; @endphp

    <form action="{{ route('admin.events.update', $event) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- Sticky Header Action Bar -->
        <div class="sticky top-0 z-50 -mx-6 mb-6 border-b border-[#2b2b2b] bg-[rgba(16,16,18,.96)] px-6 py-4 backdrop-blur-md flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ !empty($admin['edit_event']) ? $admin['edit_event'] : 'Editar Evento' }}</h1>
                <p class="mt-1 text-xs text-[#7b7b7b]">{{ !empty($admin['update_event_copy']) ? $admin['update_event_copy'] : 'Actualiza la información del evento.' }}</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.events.index') }}" class="lucille-button">
                    {{ !empty($admin['back_to_events']) ? $admin['back_to_events'] : 'Volver a Eventos' }}
                </a>
                <button type="submit" class="lucille-button-solid">
                    {{ !empty($admin['edit_event']) ? $admin['edit_event'] : 'Guardar Evento' }}
                </button>
            </div>
        </div>

        @if ($errors->any())
            <div x-data="{ showErrors: true }" x-show="showErrors" style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 16px; display: none;" x-transition>
                <!-- Backdrop -->
                <div style="position: absolute; inset: 0; background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(4px);" @click="showErrors = false"></div>
                <!-- Content -->
                <div style="position: relative; width: 100%; max-width: 460px; border: 1px solid rgba(195, 39, 32, 0.4); background: rgba(12, 12, 13, 0.98); padding: 24px; box-shadow: 0 30px 60px rgba(0,0,0,0.6);">
                    <div style="height: 4px; width: 100%; background: #c32720; position: absolute; top: 0; left: 0;"></div>
                    <div style="display: flex; align-items: start; gap: 12px; margin-top: 8px;">
                        <div style="display: flex; height: 40px; width: 40px; flex-shrink: 0; align-items: center; justify-content: center; border: 1px solid #c32720; background: rgba(195,39,32,0.12); font-size: 14px; font-weight: bold; color: #ffd0d0;">!</div>
                        <div style="flex: 1; min-width: 0;">
                            <h3 style="font-family: var(--font-display); font-size: 14px; text-transform: uppercase; letter-spacing: 0.1em; color: #ffaaaa; margin: 0 0 8px 0;">Errores de Validación</h3>
                            <ul style="list-style-type: disc; padding-left: 16px; margin: 0; font-size: 12px; color: rgba(220,220,220,0.85); line-height: 1.6;">
                                @foreach ($errors->all() as $error)
                                    <li style="margin-bottom: 6px;">{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
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
