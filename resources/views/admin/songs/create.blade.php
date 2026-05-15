<x-layouts.admin :title="'New Song - '.$themeSettings->site_name">
    <div class="mb-6">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">New Song</h1>
        <p class="mt-2 text-[#7b7b7b]">Add lyrics, band info and metadata for the player.</p>
    </div>

    <form action="{{ route('admin.songs.store') }}" method="POST" class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
        @csrf
        @include('admin.songs._form')
    </form>
</x-layouts.admin>
