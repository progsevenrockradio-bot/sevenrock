<x-layouts.admin :title="($themeAppearance['admin_texts']['new_post'] ?? 'New post').' - '.$themeSettings->site_name">
    @php $admin = $themeAppearance['admin_texts']; @endphp
    <div x-data="{ showHelp: false }">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['new_post'] }}</h1>
                <p class="mt-2 text-[#7b7b7b]">{{ $admin['create_post_copy'] }}</p>
            </div>
            <div class="shrink-0">
                <button
                    type="button"
                    @click="showHelp = true"
                    class="lucille-button flex items-center gap-2 border border-[rgba(255,255,255,.12)] hover:border-white py-2 px-4 text-xs font-semibold uppercase tracking-wider text-white bg-[rgba(255,255,255,.02)] transition-colors"
                >
                    <span>💡</span>
                    <span>Manual de Uso</span>
                </button>
            </div>
        </div>

        <form action="{{ route('admin.posts.store') }}" method="POST" enctype="multipart/form-data" class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            @csrf
            @include('admin.posts._form', ['post' => $post])
        </form>

        @include('admin.posts._help_modal')
    </div>
</x-layouts.admin>
