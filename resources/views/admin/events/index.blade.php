@php $admin = $themeAppearance['admin_texts']; @endphp
<x-layouts.admin :title="$admin['events_heading'].' - '.$themeSettings->site_name">
    @if (session('status'))
        <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['events_heading'] }}</h1>
            <p class="mt-2 text-[#7b7b7b]">{{ $admin['events_copy'] ?? 'Public schedule used by the home page and upcoming shows block.' }}</p>
        </div>
        <a href="{{ route('admin.events.create') }}" class="lucille-button-solid">{{ $admin['new_event'] }}</a>
    </div>

    <div class="overflow-hidden border border-[#2b2b2b] bg-[rgba(16,16,18,.88)]">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-[#2b2b2b] text-[#dcdcdc]">
                <tr>
                    <th class="px-5 py-4">{{ $admin['table_title'] }}</th>
                    <th class="px-5 py-4">{{ $admin['table_starts_at'] }}</th>
                    <th class="px-5 py-4">{{ $admin['table_location'] }}</th>
                    <th class="px-5 py-4">{{ $admin['table_venue'] }}</th>
                    <th class="px-5 py-4">{{ $admin['table_actions'] }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#2b2b2b] text-[#7b7b7b]">
                @forelse ($events as $event)
                    <tr class="hover:bg-[rgba(255,255,255,.02)]">
                        <td class="px-5 py-4 font-display text-[15px] uppercase tracking-[.08em] text-[#dcdcdc]">{{ $event->title }}</td>
                        <td class="px-5 py-4">{{ $event->starts_at?->format('d M Y H:i') }}</td>
                        <td class="px-5 py-4">{{ $event->location }}</td>
                        <td class="px-5 py-4">{{ $event->venue }}</td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('admin.events.edit', $event) }}" class="lucille-button">{{ $admin['edit'] }}</a>
                                <form
                                    action="{{ route('admin.events.destroy', $event) }}"
                                    method="POST"
                                    data-confirm="{{ $admin['delete_confirm_event'] }}"
                                    data-confirm-title="{{ $admin['delete'] }}"
                                    data-confirm-action="{{ $admin['delete'] }}"
                                    data-confirm-tone="danger"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="lucille-button-solid">{{ $admin['delete'] }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-[#7b7b7b]">{{ $admin['no_events'] }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
