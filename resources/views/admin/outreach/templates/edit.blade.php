<x-layouts.admin title="Editar plantilla outreach">
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Editar plantilla</h1>
    </div>

    @include('admin.outreach.templates._form', [
        'template' => $template,
        'availableVariables' => $availableVariables,
        'action' => route('admin.outreach.templates.update', $template),
        'method' => 'PUT',
        'buttonLabel' => 'Actualizar plantilla',
    ])
</x-layouts.admin>
