<x-layouts.admin :title="'Edit taxonomy - '.$themeSettings->site_name">
    @php $admin = $themeAppearance['admin_texts']; @endphp

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Edit taxonomy</h1>
            <p class="mt-2 text-[#7b7b7b]">Update the name or switch between category and tag.</p>
        </div>
        <a href="{{ route('admin.dashboard') }}#taxonomias" class="lucille-button">Back to dashboard</a>
    </div>

    @if (session('status'))
        <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
            {{ session('status') }}
        </div>
    @endif

    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <form action="{{ route('admin.taxonomies.update', $taxonomy) }}" method="POST" class="grid gap-5 md:grid-cols-2">
            @csrf
            @method('PUT')

            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Type</label>
                <select name="type" class="lucille-product-field w-full">
                    <option value="category" @selected(old('type', $taxonomy->type) === 'category')>Category</option>
                    <option value="tag" @selected(old('type', $taxonomy->type) === 'tag')>Tag</option>
                </select>
            </div>

            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Name</label>
                <input
                    name="name"
                    value="{{ old('name', $taxonomy->name) }}"
                    class="lucille-product-field w-full"
                    placeholder="Music"
                >
            </div>

            <div class="md:col-span-2 flex flex-wrap gap-3 pt-2">
                <button type="submit" class="lucille-button-solid">Save changes</button>
                <a href="{{ route('admin.dashboard') }}#taxonomias" class="lucille-button">Cancel</a>
            </div>
        </form>
    </div>
</x-layouts.admin>
