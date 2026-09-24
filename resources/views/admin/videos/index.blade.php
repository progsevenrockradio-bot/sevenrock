@php $admin = $themeAppearance['admin_texts']; @endphp
<x-layouts.admin :title="$admin['videos_heading'].' - '.$themeSettings->site_name">
    @if (session('status'))
        <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['videos_heading'] }}</h1>
            <p class="mt-2 text-[#9a9a9a]">{{ $admin['videos_copy'] ?? 'Featured videos and video listings.' }}</p>
        </div>
        <a href="{{ route('admin.videos.create') }}" class="lucille-button-solid">{{ $admin['new_video'] }}</a>
    </div>

    <div class="overflow-hidden border border-[#2b2b2b] bg-[rgba(16,16,18,.88)]">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-[#2b2b2b] text-[#dcdcdc]">
                <tr>
                    <th class="px-5 py-4">{{ $admin['table_title'] }}</th>
                    <th class="px-5 py-4">{{ $admin['table_slug'] }}</th>
                    <th class="px-5 py-4">{{ $admin['table_youtube_url'] }}</th>
                    <th class="px-5 py-4">{{ $admin['table_featured'] ?? 'Destacado' }}</th>
                    <th class="px-5 py-4">{{ $admin['table_actions'] }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#2b2b2b] text-[#9a9a9a]">
                @forelse ($videos as $video)
                    <tr class="hover:bg-[rgba(255,255,255,.02)]">
                        <td class="px-5 py-4 font-display text-[15px] uppercase tracking-[.08em] text-[#dcdcdc]">{{ $video->title }}</td>
                        <td class="px-5 py-4">{{ $video->slug }}</td>
                        <td class="px-5 py-4">{{ $video->youtube_url }}</td>
                        <td class="px-5 py-4">
                            <div class="flex flex-col gap-1 items-start">
                                @if ($video->is_featured)
                                    <span class="rounded border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-2 py-1 text-xs text-[#b8e6c3]">Destacado</span>
                                @else
                                    <span class="rounded border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] px-2 py-1 text-xs text-[#9a9a9a]">Normal</span>
                                @endif
                                
                                @if ($video->is_manual)
                                    <span class="rounded border border-[#c32720] bg-[rgba(195,39,32,.2)] px-2 py-1 text-[10px] text-[#ff8c88]">Fijado a mano</span>
                                @else
                                    <span class="rounded border border-[#1a3b5c] bg-[rgba(26,59,92,.2)] px-2 py-1 text-[10px] text-[#8cb4e2]">Automático</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('admin.videos.edit', $video) }}" class="lucille-button">{{ $admin['edit'] }}</a>
                                <form action="{{ route('admin.videos.toggle-manual', $video) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="lucille-button">
                                        {{ $video->is_manual ? 'Devolver al automático' : 'Fijar a mano' }}
                                    </button>
                                </form>
                                <form
                                    action="{{ route('admin.videos.destroy', $video) }}"
                                    method="POST"
                                    data-confirm="{{ $admin['delete_confirm_video'] }}"
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
                        <td colspan="5" class="px-5 py-10 text-center text-[#9a9a9a]">{{ $admin['no_videos'] }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
