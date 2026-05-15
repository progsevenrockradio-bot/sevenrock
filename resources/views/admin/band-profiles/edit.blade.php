<x-layouts.admin :title="'Edit Band Profile - '.$themeSettings->site_name">
    <div class="mb-6">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Edit Band Profile</h1>
        <p class="mt-2 text-[#7b7b7b]">Update the local profile used before external lookup.</p>
    </div>

    <form action="{{ route('admin.band-profiles.update', $bandProfile) }}" method="POST" class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
        @csrf
        @method('PUT')
        @include('admin.band-profiles._form')
    </form>
</x-layouts.admin>
