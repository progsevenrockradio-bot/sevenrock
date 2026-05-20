@php $admin = $themeAppearance['admin_texts']; @endphp
<x-layouts.admin :title="$admin['new_image'].' - '.$themeSettings->site_name">
    <div class="mb-6">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['new_image'] }}</h1>
        <p class="mt-2 text-[#7b7b7b]">{{ $admin['create_gallery_copy'] }}</p>
    </div>

    <form action="{{ route('admin.gallery.store') }}" method="POST" enctype="multipart/form-data" class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        @csrf
        @include('admin.gallery._form', ['image' => $image])
    </form>
</x-layouts.admin>
