<x-layouts.admin title="Editar plantilla de invitación">
    <div class="mx-auto max-w-6xl space-y-5 font-sans">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Contactos e invitaciones · Plantillas</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">Editar plantilla: {{ $template->name }}</h1>
                <p class="mt-1 text-xs text-gray-400">Actualiza el contenido de la invitación conservando los marcadores necesarios.</p>
            </div>
            <a href="{{ route('admin.outreach.templates.index') }}" class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                Volver a plantillas
            </a>
        </header>

        @include('admin.outreach.templates._form', [
            'template' => $template,
            'availableVariables' => $availableVariables,
            'action' => route('admin.outreach.templates.update', $template),
            'method' => 'PUT',
            'buttonLabel' => 'Actualizar plantilla',
        ])
    </div>
</x-layouts.admin>
