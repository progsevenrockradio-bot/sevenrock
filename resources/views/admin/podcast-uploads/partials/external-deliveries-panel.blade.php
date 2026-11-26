@php
    $pendingExternalDeliveries = $pendingExternalDeliveries ?? collect();
    $resolvedExternalDeliveries = $resolvedExternalDeliveries ?? collect();
    $pendingExternalDeliveriesCount = $pendingExternalDeliveriesCount ?? $pendingExternalDeliveries->count();
    $allMasterPrograms = $allMasterPrograms ?? collect();
@endphp

{{-- Sección plegable en móvil (no estrecha el formulario en escritorio) --}}
<section
    class="mt-4 lg:hidden rounded-2xl border border-white/10 bg-[#101012] shadow-lg overflow-hidden"
    x-show="externalPanelOpen"
    x-cloak
>
    <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
        <div class="flex items-center gap-2">
            <h2 class="font-display text-base font-bold uppercase tracking-wide text-white">Programas recibidos</h2>
            <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-[11px] font-bold {{ $pendingExternalDeliveriesCount > 0 ? 'bg-[#c32720] text-white' : 'bg-white/10 text-gray-300' }}">
                {{ $pendingExternalDeliveriesCount }}
            </span>
        </div>
        <button
            type="button"
            @click="externalPanelOpen = false"
            class="h-8 w-8 rounded-lg text-gray-400 hover:bg-white/10 hover:text-white flex items-center justify-center"
            aria-label="Cerrar panel de programas recibidos"
        >
            ✕
        </button>
    </div>

    <div class="p-4">
        @include('admin.podcast-uploads.partials.external-deliveries-content', [
            'pendingExternalDeliveries' => $pendingExternalDeliveries,
            'resolvedExternalDeliveries' => $resolvedExternalDeliveries,
            'pendingExternalDeliveriesCount' => $pendingExternalDeliveriesCount,
            'allMasterPrograms' => $allMasterPrograms,
        ])
    </div>
</section>

{{-- Panel lateral deslizable en escritorio (no tapa ni estrecha el formulario cuando está cerrado) --}}
<div
    x-show="externalPanelOpen"
    x-cloak
    class="hidden lg:fixed lg:inset-0 lg:z-[140] lg:flex lg:justify-end"
    role="dialog"
    aria-modal="true"
    aria-labelledby="external-deliveries-drawer-title"
    @keydown.escape.window="externalPanelOpen = false"
>
    <div
        class="fixed inset-0 bg-black/70 backdrop-blur-xs"
        @click="externalPanelOpen = false"
    ></div>

    <aside class="relative z-10 flex h-full w-full max-w-2xl flex-col border-l border-white/10 bg-[#101012] shadow-2xl">
        <div class="flex items-center justify-between border-b border-white/10 px-6 py-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <h2 id="external-deliveries-drawer-title" class="font-display text-xl font-bold uppercase tracking-wide text-white">
                        Programas recibidos
                    </h2>
                    <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full px-2 text-xs font-bold {{ $pendingExternalDeliveriesCount > 0 ? 'bg-[#c32720] text-white' : 'bg-white/10 text-gray-300' }}">
                        {{ $pendingExternalDeliveriesCount }}
                    </span>
                </div>
                <p class="mt-0.5 text-xs text-gray-400">
                    Avisos de entregas externas por correo (solo detección y aviso; sin descarga ni emisión automática).
                </p>
            </div>
            <button
                type="button"
                @click="externalPanelOpen = false"
                class="h-8 w-8 rounded-lg text-gray-400 hover:bg-white/10 hover:text-white flex items-center justify-center transition-colors"
                aria-label="Cerrar panel de programas recibidos"
            >
                ✕
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            @include('admin.podcast-uploads.partials.external-deliveries-content', [
                'pendingExternalDeliveries' => $pendingExternalDeliveries,
                'resolvedExternalDeliveries' => $resolvedExternalDeliveries,
                'pendingExternalDeliveriesCount' => $pendingExternalDeliveriesCount,
                'allMasterPrograms' => $allMasterPrograms,
            ])
        </div>
    </aside>
</div>

