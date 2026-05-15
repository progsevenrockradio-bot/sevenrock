<x-layouts.admin :title="($themeAppearance['admin_texts']['edit_event'] ?? 'Edit event').' - '.$themeSettings->site_name">
    @php $admin = $themeAppearance['admin_texts']; @endphp
    <div class="mb-6">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['edit_event'] }}</h1>
        <p class="mt-2 text-[#7b7b7b]">{{ $admin['update_event_copy'] }}</p>
    </div>

    <form action="{{ route('admin.events.update', $event) }}" method="POST" class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        @csrf
        @method('PUT')
        @include('admin.events._form', ['event' => $event])
    </form>
</x-layouts.admin>
