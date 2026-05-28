<x-layouts.admin title="Nueva plantilla outreach">
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Nueva plantilla</h1>
    </div>

    @include('admin.outreach.templates._form', [
        'template' => $template,
        'availableVariables' => $availableVariables,
        'action' => route('admin.outreach.templates.store'),
        'method' => 'POST',
        'buttonLabel' => 'Guardar plantilla',
    ])
</x-layouts.admin>
