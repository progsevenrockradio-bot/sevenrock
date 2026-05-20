<x-layouts.admin :title="'Editar programa maestro - '.$themeSettings->site_name">
    @include('admin.master-programs._form', [
        'formAction' => route('admin.master-programs.update', $masterProgram),
        'formMethod' => 'PUT',
        'buttonLabel' => 'Guardar cambios',
        'masterProgram' => $masterProgram,
        'defaultNewsIdsText' => $defaultNewsIdsText,
        'liveNewsIdsText' => $liveNewsIdsText,
        'previewNewsIdsText' => $previewNewsIdsText,
    ])
</x-layouts.admin>
