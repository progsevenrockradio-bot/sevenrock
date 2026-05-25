<x-layouts.admin :title="'Edit Radio Artist - '.$themeSettings->site_name">
    <div class="mb-6">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Edit Radio Artist</h1>
        <p class="mt-2 text-[#7b7b7b]">Update the local profile used before external lookup.</p>
    </div>

    <form action="{{ route('admin.radio-artists.update', $bandProfile) }}" method="POST" class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
        @csrf
        @method('PUT')
        @include('admin.radio-artists._form')
    </form>
</x-layouts.admin>
