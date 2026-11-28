<x-layouts.admin :title="'Entregas y emisiones - '.config('app.name')">
    @include('admin.podcast-uploads.partials.deliveries-content', [
        'isAdminView' => true,
    ])
</x-layouts.admin>

