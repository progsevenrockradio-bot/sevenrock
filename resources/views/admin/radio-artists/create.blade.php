<x-layouts.admin :title="'New Radio Artist - '.$themeSettings->site_name">
    <div class="mb-6">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">New Radio Artist</h1>
        <p class="mt-2 text-[#7b7b7b]">Store editorial summaries and local fallback data for the player.</p>
    </div>

    <form action="{{ route('admin.radio-artists.store') }}" method="POST" class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
        @csrf
        @include('admin.radio-artists._form')
    </form>
</x-layouts.admin>
