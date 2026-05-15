<x-layouts.admin :title="($themeAppearance['admin_texts']['new_video'] ?? 'New video').' - '.$themeSettings->site_name">
    @php $admin = $themeAppearance['admin_texts']; @endphp
    <div class="mb-6">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['new_video'] }}</h1>
        <p class="mt-2 text-[#7b7b7b]">{{ $admin['create_video_copy'] }}</p>
    </div>

    <form action="{{ route('admin.videos.store') }}" method="POST" enctype="multipart/form-data" class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        @csrf
        @include('admin.videos._form', ['video' => $video])
    </form>
</x-layouts.admin>
