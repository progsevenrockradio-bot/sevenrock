<x-layouts.admin :title="($themeAppearance['admin_texts']['new_event'] ?? 'New event').' - '.$themeSettings->site_name">
    @php $admin = $themeAppearance['admin_texts']; @endphp
    <div class="mb-6">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['new_event'] }}</h1>
        <p class="mt-2 text-[#7b7b7b]">{{ $admin['create_event_copy'] }}</p>
    </div>

    <form action="{{ route('admin.events.store') }}" method="POST" class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        @csrf
        @include('admin.events._form', ['event' => $event])
    </form>
</x-layouts.admin>
