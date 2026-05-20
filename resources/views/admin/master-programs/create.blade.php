<x-layouts.admin :title="'Nuevo programa maestro - '.$themeSettings->site_name">
    @include('admin.master-programs._form', [
        'formAction' => route('admin.master-programs.store'),
        'formMethod' => 'POST',
        'buttonLabel' => 'Crear programa',
        'masterProgram' => $masterProgram,
        'defaultNewsIdsText' => $defaultNewsIdsText,
        'liveNewsIdsText' => $liveNewsIdsText,
        'previewNewsIdsText' => $previewNewsIdsText,
    ])
</x-layouts.admin>
