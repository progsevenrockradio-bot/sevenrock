<x-layouts.admin :title="($themeAppearance['admin_texts']['edit_post'] ?? 'Edit post').' - '.$themeSettings->site_name">
    @php $admin = $themeAppearance['admin_texts']; @endphp
    <div class="mb-6">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['edit_post'] }}</h1>
        <p class="mt-2 text-[#7b7b7b]">{{ $admin['update_post_copy'] }}</p>
    </div>

    <form action="{{ route('admin.posts.update', $post) }}" method="POST" enctype="multipart/form-data" class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        @csrf
        @method('PUT')
        @if ($errors->any())
            <div class="mb-6 border border-[#5a1d1a] bg-[rgba(195,39,32,.08)] px-4 py-3 text-sm text-[#f3b6b1]">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @include('admin.posts._form', ['post' => $post])
    </form>
</x-layouts.admin>
