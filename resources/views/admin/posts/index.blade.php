@php $admin = $themeAppearance['admin_texts']; @endphp
<x-layouts.admin :title="$admin['posts_heading'] . ' - ' . $themeSettings->site_name">
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
                        <td class="px-5 py-4">
                            @php
                                $isScheduled = !$post->is_published && $post->published_at && $post->published_at->isFuture();
                            @endphp
                            @if ($isScheduled)
                                <span class="inline-flex items-center gap-1 rounded border border-[#b8860b] bg-[rgba(184,134,11,.15)] px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[.12em] text-[#d4a843]">
                                    ⏳ {{ $admin['status_scheduled'] ?? 'Scheduled' }}
                                </span>
                            @elseif ($post->is_published)
                                <span class="text-[#b8e6c3]">{{ $admin['status_published'] }}</span>
                            @else
                                <span class="text-[#7b7b7b]">{{ $admin['status_draft'] }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('admin.posts.edit', $post) }}" class="lucille-button">{{ $admin['edit'] }}</a>
                                <form
                                    action="{{ route('admin.posts.destroy', $post) }}"
                                    method="POST"
                                    data-confirm="{{ $admin['delete_confirm_post'] }}"
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
                        <td colspan="5" class="px-5 py-10 text-center text-[#7b7b7b]">{{ $admin['no_posts'] }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($posts->hasPages())
        <div class="mt-6">
            {{ $posts->links('vendor.pagination.tailwind') }}
        </div>
    @endif
</x-layouts.admin>
