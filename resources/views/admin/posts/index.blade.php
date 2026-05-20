@php $admin = $themeAppearance['admin_texts']; @endphp
<x-layouts.admin :title="$admin['posts_heading'].' - '.$themeSettings->site_name">
    @if (session('status'))
        <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['posts_heading'] }}</h1>
            <p class="mt-2 text-[#7b7b7b]">{{ $admin['posts_copy'] ?? 'Blog articles used by the public blog sections.' }}</p>
        </div>
        <a href="{{ route('admin.posts.create') }}" class="lucille-button-solid">{{ $admin['new_post'] }}</a>
    </div>

    <div class="overflow-hidden border border-[#2b2b2b] bg-[rgba(16,16,18,.88)]">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-[#2b2b2b] text-[#dcdcdc]">
                <tr>
                    <th class="px-5 py-4">{{ $admin['table_title'] }}</th>
                    <th class="px-5 py-4">{{ $admin['table_published'] }}</th>
                    <th class="px-5 py-4">{{ $admin['table_categories'] }}</th>
                    <th class="px-5 py-4">{{ $admin['table_status'] }}</th>
                    <th class="px-5 py-4">{{ $admin['table_actions'] }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#2b2b2b] text-[#7b7b7b]">
                @forelse ($posts as $post)
                    <tr class="hover:bg-[rgba(255,255,255,.02)]">
                        <td class="px-5 py-4 font-display text-[15px] uppercase tracking-[.08em] text-[#dcdcdc]">{{ $post->title }}</td>
                        <td class="px-5 py-4">{{ $post->published_at?->format('d M Y') }}</td>
                        <td class="px-5 py-4">{{ implode(', ', $post->categoryNames()) }}</td>
                        <td class="px-5 py-4">{{ $post->is_published ? $admin['status_published'] : $admin['status_draft'] }}</td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('admin.posts.edit', $post) }}" class="lucille-button">{{ $admin['edit'] }}</a>
                                <form action="{{ route('admin.posts.destroy', $post) }}" method="POST" onsubmit="return confirm('{{ $admin['delete_confirm_post'] }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="lucille-button-solid">{{ $admin['delete'] }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-[#7b7b7b]">{{ $admin['no_posts'] }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
