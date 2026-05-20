<x-layouts.admin :title="'Edit Song - '.(optional($themeSettings)->site_name ?? config('app.name'))">
    <div class="mb-6">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Edit Song</h1>
        <p class="mt-2 text-[#7b7b7b]">Update the song used by the player and content pages.</p>
    </div>

    <form action="{{ route('admin.songs.update', $song) }}" method="POST" class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
        @csrf
        @method('PUT')
        @include('admin.songs._form')
    </form>
</x-layouts.admin>
