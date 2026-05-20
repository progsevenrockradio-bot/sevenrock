<x-layouts.admin :title="'Songs - '.(optional($themeSettings)->site_name ?? config('app.name'))">
    @if (session('status'))
        <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Songs</h1>
            <p class="mt-2 text-[#7b7b7b]">Metadata, lyrics and fallback info for the player.</p>
        </div>
        <a href="{{ route('admin.songs.create') }}" class="lucille-button-solid">New Song</a>
    </div>

    <div class="overflow-hidden border border-[#2b2b2b] bg-[rgba(16,16,18,.88)]">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-[#2b2b2b] text-[#dcdcdc]">
                <tr>
                    <th class="px-5 py-4">Title</th>
                    <th class="px-5 py-4">Artist</th>
                    <th class="px-5 py-4">Program</th>
                    <th class="px-5 py-4">Published</th>
                    <th class="px-5 py-4">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#2b2b2b] text-[#7b7b7b]">
                @forelse ($songs as $song)
                    <tr class="hover:bg-[rgba(255,255,255,.02)]">
                        <td class="px-5 py-4 font-display text-[15px] uppercase tracking-[.08em] text-[#dcdcdc]">{{ $song->title }}</td>
                        <td class="px-5 py-4">{{ $song->artist }}</td>
                        <td class="px-5 py-4">{{ $song->program?->name }}</td>
                        <td class="px-5 py-4">{{ optional($song->published_at)->format('d M Y') }}</td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('admin.songs.edit', $song) }}" class="lucille-button">Edit</a>
                                <form action="{{ route('admin.songs.destroy', $song) }}" method="POST" onsubmit="return confirm('Delete this song?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="lucille-button-solid">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-[#7b7b7b]">No songs yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
