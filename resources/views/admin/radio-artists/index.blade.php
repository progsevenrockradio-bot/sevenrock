<x-layouts.admin :title="'Radio Artists - '.$themeSettings->site_name">
    @if (session('status'))
        <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Radio Artists</h1>
            <p class="mt-2 text-[#7b7b7b]">Local editorial profile cache used by the player modal and drawer.</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
            <form action="{{ route('admin.radio-artists.index') }}" method="GET" class="flex">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por nombre..." class="px-3 py-2 bg-[rgba(16,16,18,.88)] border border-[#2b2b2b] text-[#dcdcdc] text-sm focus:outline-none focus:border-lucille-accent placeholder-[#4a4a4a] rounded-l-md w-full sm:w-64">
                <button type="submit" class="bg-[#2b2b2b] hover:bg-lucille-accent text-[#dcdcdc] hover:text-white px-4 py-2 text-sm border border-[#2b2b2b] border-l-0 rounded-r-md transition-colors">Buscar</button>
                @if(request('search'))
                    <a href="{{ route('admin.radio-artists.index') }}" class="ml-2 text-xs text-[#7b7b7b] hover:text-white self-center transition-colors">Limpiar</a>
                @endif
            </form>
            <a href="{{ route('admin.radio-artists.create') }}" class="lucille-button-solid whitespace-nowrap">New Radio Artist</a>
        </div>
    </div>

    <div class="overflow-hidden border border-[#2b2b2b] bg-[rgba(16,16,18,.88)]">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-[#2b2b2b] text-[#dcdcdc]">
                <tr>
                    <th class="px-5 py-4">Name</th>
                    <th class="px-5 py-4">Summary</th>
                    <th class="px-5 py-4">Image</th>
                    <th class="px-5 py-4">Logo</th>
                    <th class="px-5 py-4">Country</th>
                    <th class="px-5 py-4">Genre</th>
                    <th class="px-5 py-4">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#2b2b2b] text-[#7b7b7b]">
                @forelse ($bandProfiles as $profile)
                    <tr class="hover:bg-[rgba(255,255,255,.02)]">
                        <td class="px-5 py-4 font-display text-[15px] uppercase tracking-[.08em] text-[#dcdcdc]">{{ $profile->name }}</td>
                        <td class="px-5 py-4">{{ \Illuminate\Support\Str::limit($profile->editorial_summary ?: $profile->biography, 120) }}</td>
                        <td class="px-5 py-4">{{ $profile->image_path }}</td>
                        <td class="px-5 py-4">{{ $profile->logo_path ?: '—' }}</td>
                        <td class="px-5 py-4">{{ $profile->country ?: '—' }}</td>
                        <td class="px-5 py-4">{{ $profile->genre ?: '—' }}</td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('admin.radio-artists.edit', $profile) }}" class="lucille-button">Edit</a>
                                <form
                                    action="{{ route('admin.radio-artists.destroy', $profile) }}"
                                    method="POST"
                                    data-confirm="Delete this radio artist?"
                                    data-confirm-title="Delete radio artist"
                                    data-confirm-action="Delete"
                                    data-confirm-tone="danger"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="lucille-button-solid">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-10 text-center text-[#7b7b7b]">No radio artists yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($bandProfiles->hasPages())
        <div class="mt-6">
            {{ $bandProfiles->links() }}
        </div>
    @endif
</x-layouts.admin>
